<?php

namespace Tests\Feature;

use App\Models\Researcher;
use App\Models\ResearchLine;
use App\Models\ResearchProject;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class FundedProjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_private_workbook_import_preserves_repeated_titles_and_does_not_assign_accounts(): void
    {
        $profile = $this->researcher('Ana Pérez', User::factory()->create());
        $file = $this->workbook([
            [1, 'Estudio de prueba', 'Ana Pérez', 2020, new DateTimeImmutable('2022-02-19'), 'Sistemas', 'Convocatoria A', 'Institución A', 'No publicar'],
            [2, 'Estudio de prueba', 'Persona externa', null, 2023, null, 'Convocatoria B', 'Institución B'],
        ]);
        try {
            $this->artisan('catalog:projects', ['file' => $file, '--dry-run' => true])
                ->expectsOutput('2 proyectos válidos; no se guardaron cambios.')->assertSuccessful();
            $this->assertDatabaseCount('research_projects', 0);
            $this->artisan('catalog:projects', ['file' => $file])->assertSuccessful();
            $project = ResearchProject::where('import_reference', 'proyectos-financiados:1')->firstOrFail();
            $this->assertSame('2020', $project->period_start);
            $this->assertSame('2022-02-19', $project->period_end);
            $this->assertNull($project->responsible_researcher_id);
            $this->get(route('projects.index', ['q' => 'Ana Pérez']))->assertOk()->assertSee('Estudio de prueba');
            $this->get(route('projects.show', $project))->assertOk()->assertSee('Ana Pérez')->assertSee('Institución A')->assertDontSee('No publicar');
            $this->actingAs($profile->user)->get(route('projects.edit', $project))->assertForbidden();
            $project->update(['description' => 'Descripción corregida']);
            $this->artisan('catalog:projects', ['file' => $file])->expectsOutput('Proyectos nuevos: 0. Fichas existentes sin cambios: 2.')->assertSuccessful();
            $this->assertDatabaseCount('research_projects', 2);
            $this->assertDatabaseCount('project_participants', 0);
            $this->assertSame('Descripción corregida', $project->fresh()->description);
        } finally {
            unlink($file);
        }
    }

    public function test_invalid_workbook_leaves_no_partially_imported_projects(): void
    {
        $file = $this->workbook([
            [1, 'Estudio válido', 'Persona válida', 2020, 2023],
            [1, 'Otro estudio', 'Otra persona', 2020, 2023],
        ]);
        try {
            $this->artisan('catalog:projects', ['file' => $file])->expectsOutput('Fila 3: número de proyecto repetido.')->assertFailed();
            $this->assertDatabaseCount('research_projects', 0);
        } finally {
            unlink($file);
        }
    }

    public function test_only_the_assigned_responsible_can_edit_public_details_without_changing_the_team(): void
    {
        $owner = $this->researcher('Ana Pérez', User::factory()->create());
        $colleague = $this->researcher('Luis Díaz', User::factory()->create());
        $admin = User::factory()->create(['role' => 'admin']);
        $line = ResearchLine::create(['name' => 'Línea de prueba']);
        $this->actingAs($admin)->post(route('admin.projects.store'), [
            'name' => 'Estudio de prueba', 'responsible_researcher_id' => $owner->id,
            'participants' => [['name' => $colleague->name, 'researcher_id' => $colleague->id]],
            'research_lines' => [$line->id],
        ])->assertSessionHas('success');
        $project = ResearchProject::firstOrFail();
        $this->get(route('researchers.show', $owner))->assertOk()->assertSee('Estudio de prueba');

        $data = ['name' => 'Nuevo título', 'description' => 'Texto nuevo', 'version' => 1, 'period_start' => '2020', 'period_end' => '2022-02-19'];
        $this->actingAs($colleague->user)->get(route('projects.edit', $project))->assertForbidden();
        $this->post(route('projects.update', $project), $data)->assertForbidden();
        $this->actingAs($owner->user)->get(route('projects.show', $project))->assertOk()->assertSee(route('projects.edit', $project));
        $this->get(route('projects.edit', $project))->assertOk()->assertDontSee('responsible_researcher_id');
        $this->post(route('admin.projects.store'), $data)->assertForbidden();
        $this->post(route('admin.projects.update', $project), $data)->assertForbidden();
        $this->delete(route('admin.projects.destroy', $project), ['confirm_delete' => 1, 'version' => 1])->assertForbidden();
        $this->post(route('projects.update', $project), $data + [
            'responsible_researcher_id' => $colleague->id, 'participants' => [], 'external_participants' => 'Alguien nuevo',
        ])->assertSessionHas('success');
        $project->refresh();
        $this->assertSame('Nuevo título', $project->name);
        $this->assertSame($owner->id, $project->responsible_researcher_id);
        $this->assertSame([$colleague->id], $project->participants()->pluck('researcher_id')->all());
        $this->assertSame([$line->id], $project->researchLines()->pluck('research_lines.id')->all());
        $this->post(route('projects.update', $project), $data)->assertStatus(409);

        $this->actingAs($admin)->post(route('admin.projects.update', $project), [
            'name' => $project->name, 'version' => 2, 'responsible_researcher_id' => $colleague->id,
        ])->assertSessionHasErrors('participants');
        $this->post(route('admin.projects.update', $project), [
            'name' => $project->name, 'version' => 2, 'responsible_researcher_id' => $colleague->id, 'participants_present' => 1,
        ])->assertSessionHas('success');
        $this->actingAs($owner->user)->post(route('projects.update', $project), [...$data, 'version' => 3])->assertForbidden();
    }

    public function test_admin_cannot_add_responsible_again_as_participant_and_must_confirm_deletion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = $this->researcher('Ana Pérez', User::factory()->create());
        $data = ['name' => 'Estudio', 'responsible_researcher_id' => $profile->id];
        $this->actingAs($admin)->post(route('admin.projects.store'), $data + [
            'participants' => [['name' => $profile->name, 'researcher_id' => $profile->id]],
        ])->assertSessionHasErrors('participants');
        $this->assertDatabaseCount('research_projects', 0);
        $this->post(route('admin.projects.store'), $data)->assertSessionHas('success');
        $project = ResearchProject::firstOrFail();
        $this->delete(route('admin.projects.destroy', $project), ['version' => 1])->assertSessionHasErrors('confirm_delete');
        $this->delete(route('admin.projects.destroy', $project), ['version' => 1, 'confirm_delete' => 1])->assertRedirect(route('admin.projects'));
        $this->assertModelMissing($project);
        $this->assertModelExists($profile);
    }

    private function researcher(string $name, User $user): Researcher
    {
        return Researcher::create(['name' => $name, 'original_name' => $name, 'user_id' => $user->id]);
    }

    private function workbook(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'projects-test-');
        $writer = new Writer;
        $writer->openToFile($file);
        $writer->getCurrentSheet()->setName('Proyectos Fin');
        $writer->addRow(Row::fromValues(['No.', 'NOMBRE DEL PROYECTO', 'RESPONSABLE TÉCNICO ', 'VIGENCIA', null, 'DEPARTAMENTO', 'CONVOCATORIA', 'INSTITUCIÓN']));
        foreach ($rows as $row) {
            $styles = array_map(fn () => (new Style)->setFormat('yyyy-mm-dd'), array_filter($row, fn ($value) => $value instanceof DateTimeImmutable));
            $writer->addRow(Row::fromValuesWithStyles($row, columnStyles: $styles));
        }
        $writer->close();

        return $file;
    }
}
