<?php

namespace App\Console\Commands;

use App\Models\Researcher;
use App\Services\Audit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

#[Signature('catalog:recognitions {type : snii o prodep} {file : Ruta privada del Excel} {--show-matches : Muestra la fila y el ID del perfil propuesto} {--apply : Guarda los vínculos inequívocos}')]
#[Description('Previsualiza o importa reconocimientos sin guardar RFC ni reemplazar datos existentes')]
class ImportResearcherRecognitions extends Command
{
    private const SNII_LEVELS = [
        'C' => 'CANDIDATO',
        '1' => 'NIVEL 1',
        '2' => 'NIVEL 2',
        '3' => 'NIVEL 3',
    ];

    public function handle(): int
    {
        $type = $this->argument('type');
        $file = $this->argument('file');
        if (! in_array($type, ['snii', 'prodep'], true) || ! is_file($file) || ! is_readable($file)) {
            $this->error('Indica snii o prodep y un archivo Excel privado que se pueda leer.');

            return self::FAILURE;
        }
        try {
            $rows = $this->readRecognitions($type, $file);
        } catch (OpenSpoutException) {
            $this->error('No se pudo leer el Excel de reconocimientos.');

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $report = $apply
            ? DB::transaction(fn () => $this->matchRecognitions($type, $rows, true))
            : $this->matchRecognitions($type, $rows, false);

        $this->info('Registros leídos: '.count($rows).'.');
        $this->info(($apply ? 'Vínculos guardados: ' : 'Vínculos propuestos (sin guardar): ').count($report['ready'] ?? []).'.');
        $this->info('Reconocimientos iguales, sin cambios: '.count($report['unchanged'] ?? []).'.');
        foreach ([
            'unknown_level' => 'Distinción SNII sin equivalencia',
            'invalid_period' => 'Vigencia PRODEP ausente o inválida',
            'no_match' => 'Sin coincidencia de nombre completo',
            'ambiguous' => 'Nombre ambiguo o repetido',
            'conflict' => 'Reconocimiento distinto ya registrado',
        ] as $status => $label) {
            if (isset($report[$status])) {
                $this->warn($label.' ('.count($report[$status]).'). Filas de Excel: '.implode(', ', array_keys($report[$status])).'.');
            }
        }
        if ($this->option('show-matches') && isset($report['ready'])) {
            $matches = [];
            foreach ($report['ready'] as $row => $profileId) {
                $matches[] = [$row, $profileId];
            }
            $this->table(['Fila de Excel', 'ID del perfil'], $matches);
        }

        return self::SUCCESS;
    }

    /** @return array<int, array{key: string|null, value: string|array{start: string, until: string}|null}> */
    private function readRecognitions(string $type, string $file): array
    {
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        $reader->open($file);
        $headingRow = $type === 'snii' ? 1 : 6;
        $valueColumn = $type === 'snii' ? 2 : 4;
        $expectedHeading = $type === 'snii' ? 'distincion' : 'vigencia';
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if ($number < $headingRow) {
                        continue;
                    }
                    $cells = $row->getCells();
                    $name = ($cells[1] ?? null)?->getValue();
                    $value = ($cells[$valueColumn] ?? null)?->getValue();
                    if ($number === $headingRow) {
                        if (! is_string($name) || ! is_string($value)
                            || $this->normalize($name) !== 'nombre' || $this->normalize($value) !== $expectedHeading) {
                            throw new RuntimeException('El encabezado del Excel no coincide con el tipo de reconocimiento indicado.');
                        }

                        continue;
                    }
                    if (! is_string($name) || trim($name) === '') {
                        continue;
                    }
                    $rows[$number] = [
                        'key' => $this->nameKey($name),
                        'value' => $type === 'snii'
                            ? self::SNII_LEVELS[is_string($value) || is_int($value) ? Str::upper(trim((string) $value)) : ''] ?? null
                            : $this->prodepPeriod($value),
                    ];
                }
                break;
            }
        } finally {
            $reader->close();
        }
        if ($rows === []) {
            throw new RuntimeException('La primera hoja no contiene reconocimientos para importar.');
        }

        return $rows;
    }

    /**
     * @param  array<int, array{key: string|null, value: string|array{start: string, until: string}|null}>  $rows
     * @return array<string, array<int, int|null>>
     */
    private function matchRecognitions(string $type, array $rows, bool $apply): array
    {
        $profiles = Researcher::query()->orderBy('id')->when($apply, fn ($query) => $query->lockForUpdate())
            ->get(['id', 'name', 'original_name', 'recognitions'])->keyBy('id');
        $profileIdsByName = [];
        foreach ($profiles as $profile) {
            foreach ([$profile->name, $profile->original_name] as $name) {
                if ($key = $this->nameKey($name)) {
                    $profileIdsByName[$key][$profile->id] = $profile->id;
                }
            }
        }

        $uniqueMatches = [];
        $rowsByProfile = [];
        foreach ($rows as $number => $row) {
            $candidates = $profileIdsByName[$row['key']] ?? [];
            foreach ($candidates as $id) {
                $rowsByProfile[$id][] = $number;
            }
            if (count($candidates) === 1) {
                $uniqueMatches[$number] = reset($candidates);
            }
        }

        $report = [];
        foreach ($rows as $number => $row) {
            $value = $row['value'];
            $id = $uniqueMatches[$number] ?? null;
            $candidateCount = count($profileIdsByName[$row['key']] ?? []);
            $status = match (true) {
                $value === null => $type === 'snii' ? 'unknown_level' : 'invalid_period',
                $candidateCount === 0 => 'no_match',
                $id === null || count($rowsByProfile[$id]) > 1 => 'ambiguous',
                default => $this->storeRecognition($profiles->get($id), $type, $value, $apply),
            };
            $report[$status][$number] = $status === 'ready' ? $id : null;
        }

        return $report;
    }

    /** @param  string|array{start: string, until: string}  $value */
    private function storeRecognition(Researcher $profile, string $type, string|array $value, bool $apply): string
    {
        $recognitions = $profile->recognitions ?? [];
        if (isset($recognitions[$type])) {
            return $recognitions[$type] == $value ? 'unchanged' : 'conflict';
        }
        if ($apply) {
            $profile->update(['recognitions' => $recognitions + [$type => $value]]);
            Audit::record('researcher', $profile->id, 'recognition_imported', null, [$type => $value]);
        }

        return 'ready';
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii(Str::squish($value)));
    }

    private function nameKey(string $name): ?string
    {
        $parts = preg_split('/[^a-z]+/', $this->normalize($name), -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) < 3 || collect($parts)->contains(fn (string $part) => strlen($part) === 1)) {
            return null;
        }
        sort($parts, SORT_STRING);

        return implode(' ', $parts);
    }

    /** @return array{start: string, until: string}|null */
    private function prodepPeriod(mixed $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }
        $parts = preg_split('/\s*[-–—]\s*/u', $this->normalize($value));
        if (count($parts) !== 2 || ! ($start = $this->prodepDate($parts[0])) || ! ($until = $this->prodepDate($parts[1])) || $start > $until) {
            return null;
        }

        return compact('start', 'until');
    }

    private function prodepDate(string $value): ?string
    {
        $months = [
            'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'ago' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
        ];
        if (preg_match('/\A(\d{1,2})\/(\d{1,2})\/(\d{4})\z/', $value, $parts)) {
            [, $day, $month, $year] = $parts;
        } elseif (preg_match('/\A(\d{1,2}) ([a-z]+) (\d{4})\z/', $value, $parts)) {
            [, $day, $monthName, $year] = $parts;
            $month = $months[substr($monthName, 0, 3)] ?? 0;
        } elseif (preg_match('/\A([a-z]+) (\d{1,2}) (\d{4})\z/', $value, $parts)) {
            [, $monthName, $day, $year] = $parts;
            $month = $months[substr($monthName, 0, 3)] ?? 0;
        } else {
            return null;
        }

        return checkdate((int) $month, (int) $day, (int) $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }
}
