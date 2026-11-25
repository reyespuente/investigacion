<?php

namespace Tests\Feature;

use App\Models\Researcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ResearcherRecognitionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_snii_preview_is_read_only_and_apply_matches_unique_full_names_without_overwriting(): void
    {
        $matched = $this->researcher('Ana Belén Pérez López', 'PÉREZ LÓPEZ ANA BELÉN', ['prodep' => ['start' => '2025-01-01', 'until' => '2028-12-31']]);
        $renamed = $this->researcher('María Laura García Soto', 'MARÍA GARCÍA SOTO');
        $conflict = $this->researcher('Rosa Isabel Torres Ruiz', recognitions: ['snii' => 'NIVEL 3']);
        $unknown = $this->researcher('Elena Adriana Ramos Cruz');
        $file = $this->workbook([
            ['No.', 'NOMBRE', 'DISTINCION'],
            [1, 'PEREZ LOPEZ, ANA BELEN', 2],
            [2, 'GARCIA SOTO, MARIA', 'C'],
            [3, 'TORRES RUIZ, ROSA ISABEL', 1],
            [4, 'RAMOS CRUZ, ELENA ADRIANA', 'H'],
            [5, 'Agustín Campos Castro', 3],
        ]);
        try {
            $this->artisan('catalog:recognitions', ['type' => 'snii', 'file' => $file, '--show-matches' => true])
                ->expectsOutput('Vínculos propuestos (sin guardar): 2.')
                ->expectsOutput('Distinción SNII sin equivalencia (1). Filas de Excel: 5.')
                ->expectsOutput('Sin coincidencia de nombre completo (1). Filas de Excel: 6.')
                ->expectsOutput('Reconocimiento distinto ya registrado (1). Filas de Excel: 4.')
                ->expectsTable(['Fila de Excel', 'ID del perfil'], [[2, $matched->id], [3, $renamed->id]])
                ->assertSuccessful();
            $this->assertArrayNotHasKey('snii', $matched->fresh()->recognitions);
            $this->assertDatabaseCount('audit_events', 0);

            $arguments = ['type' => 'snii', 'file' => $file, '--apply' => true];
            $this->artisan('catalog:recognitions', $arguments)->expectsOutput('Vínculos guardados: 2.')->assertSuccessful();
            $this->assertSame('NIVEL 2', $matched->fresh()->recognitions['snii']);
            $this->assertSame('2028-12-31', $matched->fresh()->recognitions['prodep']['until']);
            $this->assertSame('CANDIDATO', $renamed->fresh()->recognitions['snii']);
            $this->assertSame('NIVEL 3', $conflict->fresh()->recognitions['snii']);
            $this->assertNull($unknown->fresh()->recognitions);
            $this->artisan('catalog:recognitions', $arguments)->expectsOutput('Reconocimientos iguales, sin cambios: 2.')->assertSuccessful();
            $this->assertDatabaseCount('audit_events', 2);
        } finally {
            unlink($file);
        }
    }

    public function test_ambiguous_duplicate_and_abbreviated_names_are_not_linked(): void
    {
        $this->researcher('Paula Adriana Cano Silva');
        $this->researcher('Paula Adriana Cano Silva');
        $this->researcher('Ana Elisa Gil Vela', 'Ana Eliza Gil Vela');
        $this->researcher('J. Jesús Nieto Ruiz');
        $file = $this->workbook([
            ['No.', 'NOMBRE', 'DISTINCIÓN'],
            [1, 'CANO SILVA, PAULA ADRIANA', 1],
            [2, 'Ana Elisa Gil Vela', 2],
            [3, 'Ana Eliza Gil Vela', 3],
            [4, 'Nieto Ruiz, J. Jesús', 2],
        ]);
        try {
            $this->artisan('catalog:recognitions', ['type' => 'snii', 'file' => $file, '--apply' => true])
                ->expectsOutput('Vínculos guardados: 0.')
                ->expectsOutput('Sin coincidencia de nombre completo (1). Filas de Excel: 5.')
                ->expectsOutput('Nombre ambiguo o repetido (3). Filas de Excel: 2, 3, 4.')
                ->assertSuccessful();
            $this->assertDatabaseCount('audit_events', 0);
        } finally {
            unlink($file);
        }
    }

    public function test_prodep_import_handles_the_workbook_date_formats_and_ignores_private_columns(): void
    {
        $first = $this->researcher('Ana Lucía Pérez López', recognitions: ['snii' => 'NIVEL 1']);
        $second = $this->researcher('Rosa Isabel Torres Ruiz');
        $third = $this->researcher('Elsa Elena Ramos Cruz');
        $invalid = $this->researcher('Clara Teresa Sosa Ríos');
        $missing = $this->researcher('Eva Itzel Lara Rosas');
        $file = $this->workbook([
            [], [], [], [null, 'PROFESORES CON PERFIL DESEABLE VIGENTE'], [null, 'PRODEP'],
            [null, 'NOMBRE', 'RFC', 'SEXO', 'VIGENCIA'],
            [1, $first->name, 'PRIVATE-ID-ONE', 'F', '23 oct  2024 -23 oct 2027'],
            [2, $second->name, 'PRIVATE-ID-TWO', 'F', 'julio 15 2024 - julio 14 2027'],
            [3, $third->name, 'PRIVATE-ID-THREE', 'F', '19/12/2025-18/12/2028'],
            [4, $invalid->name, 'PRIVATE-ID-FOUR', 'F', '31/02/2026-18/12/2028'],
            [5, $missing->name, 'PRIVATE-ID-FIVE', 'F', null],
        ]);
        try {
            $this->artisan('catalog:recognitions', ['type' => 'prodep', 'file' => $file, '--apply' => true])
                ->expectsOutput('Vínculos guardados: 3.')
                ->expectsOutput('Vigencia PRODEP ausente o inválida (2). Filas de Excel: 10, 11.')
                ->assertSuccessful();
            $this->assertSame(['snii' => 'NIVEL 1', 'prodep' => ['start' => '2024-10-23', 'until' => '2027-10-23']], $first->fresh()->recognitions);
            $this->assertSame(['start' => '2024-07-15', 'until' => '2027-07-14'], $second->fresh()->recognitions['prodep']);
            $this->assertSame(['start' => '2025-12-19', 'until' => '2028-12-18'], $third->fresh()->recognitions['prodep']);
            $this->assertNull($invalid->fresh()->recognitions);
            $this->assertNull($missing->fresh()->recognitions);
            $this->assertDatabaseCount('audit_events', 3);
            $this->assertStringNotContainsString('PRIVATE-ID', DB::table('audit_events')->pluck('after')->implode(' '));
        } finally {
            unlink($file);
        }
    }

    private function researcher(string $name, ?string $originalName = null, ?array $recognitions = null): Researcher
    {
        return Researcher::create(['name' => $name, 'original_name' => $originalName ?? $name, 'recognitions' => $recognitions]);
    }

    private function workbook(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'recognitions-test-');
        $writer = new Writer;
        $writer->openToFile($file);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $file;
    }
}
