<?php

namespace App\Console\Commands;

use App\Models\ResearchProject;
use DateTimeInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

#[Signature('catalog:projects {file : Ruta privada del XLSX} {--dry-run : Valida el archivo sin guardar proyectos}')]
#[Description('Carga los proyectos financiados sin modificar fichas existentes ni vincular perfiles')]
class ImportFundedProjects extends Command
{
    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file) || ! is_readable($file)) {
            $this->error('El archivo de proyectos no existe o no se puede leer.');

            return self::FAILURE;
        }
        try {
            $projects = $this->readProjects($file);
        } catch (OpenSpoutException) {
            $this->error('No se pudo leer el Excel de proyectos.');

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->info(count($projects).' proyectos válidos; no se guardaron cambios.');

            return self::SUCCESS;
        }

        $created = DB::transaction(function () use ($projects) {
            $created = 0;
            foreach ($projects as $reference => $project) {
                $record = ResearchProject::firstOrCreate(['import_reference' => $reference], $project);
                $created += (int) $record->wasRecentlyCreated;
            }

            return $created;
        });
        $this->info('Proyectos nuevos: '.$created.'. Fichas existentes sin cambios: '.(count($projects) - $created).'.');

        return self::SUCCESS;
    }

    /** @return array<string, array<string, string|null>> */
    private function readProjects(string $file): array
    {
        $reader = new Reader;
        $reader->open($file);
        $projects = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== 'Proyectos Fin') {
                    continue;
                }
                foreach ($sheet->getRowIterator() as $number => $row) {
                    $cells = $row->toArray();
                    if ($number === 1) {
                        $header = array_map(fn ($cell) => is_string($cell) ? Str::upper(Str::squish($cell)) : '', array_slice($cells, 0, 8));
                        if (array_diff_assoc([0 => 'NO.', 1 => 'NOMBRE DEL PROYECTO', 2 => 'RESPONSABLE TÉCNICO', 3 => 'VIGENCIA', 5 => 'DEPARTAMENTO', 6 => 'CONVOCATORIA', 7 => 'INSTITUCIÓN'], $header)) {
                            throw new RuntimeException('Los encabezados de la hoja «Proyectos Fin» no coinciden con el formato esperado.');
                        }

                        continue;
                    }
                    if (! array_filter(array_slice($cells, 0, 8), fn ($cell) => $cell !== null && trim(is_scalar($cell) ? (string) $cell : 'x') !== '')) {
                        continue;
                    }
                    $numberCell = $row->getCells()[0] ?? null;
                    $sourceId = $this->text($numberCell instanceof FormulaCell ? $numberCell->getComputedValue() : ($cells[0] ?? null), 20, $number);
                    $name = $this->text($cells[1] ?? null, 500, $number);
                    $responsible = $this->text($cells[2] ?? null, 500, $number);
                    if (! $sourceId || ! ctype_digit($sourceId) || ltrim($sourceId, '0') === '' || ! $name || ! $responsible) {
                        throw new RuntimeException("Fila {$number}: faltan el número, el título o el responsable.");
                    }
                    $reference = 'proyectos-financiados:'.ltrim($sourceId, '0');
                    if (isset($projects[$reference])) {
                        throw new RuntimeException("Fila {$number}: número de proyecto repetido.");
                    }
                    $projects[$reference] = [
                        'name' => $name,
                        'description' => '',
                        'responsible_name' => $responsible,
                        'period_start' => $this->period($cells[3] ?? null, $number),
                        'period_end' => $this->period($cells[4] ?? null, $number),
                        'department' => $this->text($cells[5] ?? null, 250, $number),
                        'funding_call' => $this->text($cells[6] ?? null, 250, $number),
                        'funding_institution' => $this->text($cells[7] ?? null, 250, $number),
                    ];
                }
                break;
            }
        } finally {
            $reader->close();
        }
        if ($projects === []) {
            throw new RuntimeException('No se encontraron proyectos en la hoja «Proyectos Fin».');
        }

        return $projects;
    }

    private function text(mixed $value, int $max, int $row): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) && ! is_int($value)) {
            throw new RuntimeException("Fila {$row}: se esperaba texto o un número entero.");
        }
        $text = Str::squish((string) $value);
        if (mb_strlen($text) > $max) {
            throw new RuntimeException("Fila {$row}: un campo supera {$max} caracteres.");
        }

        return $text === '' ? null : $text;
    }

    private function period(mixed $value, int $row): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $period = $this->text($value, 10, $row);
        if ($period !== null && ! preg_match('/\A[0-9]{4}\z/', $period)) {
            throw new RuntimeException("Fila {$row}: la vigencia debe contener un año o una fecha de Excel.");
        }

        return $period;
    }
}
