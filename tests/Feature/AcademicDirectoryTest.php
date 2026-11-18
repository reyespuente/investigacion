<?php

namespace Tests\Feature;

use App\Models\Researcher;
use App\Models\ResearchLine;
use App\Models\ResearchProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['catalogue.domains' => ['staff.example.edu']]);
    }

    private function researcher(User $user): Researcher
    {
        return Researcher::create(['name' => 'Ana Pérez', 'original_name' => 'Ana Pérez', 'user_id' => $user->id]);
    }

    public function test_admin_creates_a_project_with_internal_and_external_participants_and_public_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = $this->researcher(User::factory()->create());
        $line = ResearchLine::create(['name' => 'Energía sostenible']);
        $this->actingAs($admin)->post(route('admin.projects.store'), [
            'name' => 'Proyecto solar', 'description' => 'Desarrollo de un prototipo.',
            'participants' => [['name' => 'Nombre manipulado', 'researcher_id' => $profile->id]],
            'external_participants' => 'María Externa, Pedro Externo, Laura Externa, Juan Externo',
            'research_lines' => [$line->id],
        ])->assertRedirect();
        $project = ResearchProject::firstOrFail();
        $this->assertCount(5, $project->participants());
        $this->assertSame($profile->name, $project->participants()->first()->name);
        $this->assertSame([1, 2, 3, 4, 5], $project->participants()->pluck('position')->all());
        $this->assertDatabaseHas('research_line_research_project', ['research_project_id' => $project->id, 'research_line_id' => $line->id]);
        $this->get(route('admin.projects.edit', $project))->assertOk();
        auth()->logout();
        $this->get(route('projects.show', $project))->assertOk()->assertSee('María Externa')->assertSee(route('researchers.show', $profile));
        $this->get(route('projects.index'))->assertOk()->assertSee('Proyecto solar');
        $this->get(route('lines.show', $line))->assertOk()->assertSee('Proyecto solar');
        $this->get(route('researchers.show', $profile))->assertOk()->assertSee('Proyecto solar');
    }

    public function test_project_limit_and_stale_version_never_partially_replace_participants(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $data = ['name' => 'Proyecto', 'description' => 'Descripción', 'external_participants' => 'Uno, Dos, Tres, Cuatro, Cinco, Seis'];
        $this->actingAs($admin)->post(route('admin.projects.store'), $data)->assertSessionHasErrors('participants');
        $this->assertDatabaseCount('research_projects', 0);
        $this->post(route('admin.projects.store'), array_replace($data, ['external_participants' => 'Uno, Dos']))->assertRedirect();
        $project = ResearchProject::firstOrFail();
        $this->post(route('admin.projects.update', $project), array_replace($data, ['version' => 99, 'external_participants' => 'Otro']))->assertStatus(409);
        $this->assertSame(['Uno', 'Dos'], $project->participants()->pluck('name')->all());
        $this->assertSame(1, $project->fresh()->revision);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_members_cannot_manage_projects_or_create_research_lines(): void
    {
        $user = User::factory()->create();
        $this->researcher($user);
        $project = ResearchProject::create(['name' => 'Proyecto', 'description' => 'Descripción']);
        $line = ResearchLine::create(['name' => 'Línea']);
        $this->actingAs($user)->get(route('admin.projects'))->assertForbidden();
        $this->get(route('admin.projects.create'))->assertForbidden();
        $this->get(route('admin.projects.edit', $project))->assertForbidden();
        $this->post(route('admin.projects.store'), ['name' => 'Ajeno'])->assertForbidden();
        $this->post(route('admin.projects.update', $project), ['name' => 'Ajeno'])->assertForbidden();
        $this->get(route('admin.lines'))->assertForbidden();
        $this->get(route('admin.lines.edit', $line))->assertForbidden();
        $this->post(route('admin.lines.store'), ['name' => 'Ajena'])->assertForbidden();
        $this->post(route('admin.lines.update', $line), ['name' => 'Ajena'])->assertForbidden();
        $this->get(route('projects.show', $project))->assertOk();
        $this->get(route('lines.index'))->assertOk();
    }

    public function test_researcher_updates_multiple_degrees_recognitions_and_own_line_membership(): void
    {
        $user = User::factory()->create(['email' => 'ana@staff.example.edu']);
        $profile = $this->researcher($user);
        $other = $this->researcher(User::factory()->create());
        $line = ResearchLine::create(['name' => 'Inteligencia artificial']);
        $data = [
            'name' => $profile->name, 'department' => 'Sistemas y Computación', 'career' => 'Sistemas Computacionales',
            'professional_title' => 'Profesora investigadora', 'degrees_present' => 1,
            'degrees' => [
                ['level' => 'Maestría', 'program' => 'Ciencias', 'institution' => 'Universidad A', 'year' => 2015],
                ['level' => 'Maestría', 'program' => 'Educación', 'institution' => 'Universidad B', 'year' => 2018],
                ['level' => 'Doctorado', 'program' => 'Computación', 'year' => 2024],
            ],
            'recognitions_present' => 1, 'prodep' => 1, 'prodep_start' => '2025-01-01', 'prodep_until' => '2028-12-31', 'snii_level' => 'NIVEL 1',
            'research_lines_present' => 1, 'research_lines' => [$line->id],
            'researcher_id' => $other->id,
        ];
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Agregar grado académico');
        $this->post(route('profile.update'), $data)->assertSessionHas('success');
        $profile->refresh();
        $this->assertCount(3, $profile->degrees);
        $this->assertSame('NIVEL 1', $profile->recognitions['snii']);
        $this->assertSame('2028-12-31', $profile->recognitions['prodep']['until']);
        $this->assertDatabaseHas('research_line_researcher', ['researcher_id' => $profile->id, 'research_line_id' => $line->id]);
        $this->assertDatabaseMissing('research_line_researcher', ['researcher_id' => $other->id, 'research_line_id' => $line->id]);
        $this->get(route('researchers.show', $profile))->assertOk()->assertSee('ana@staff.example.edu')->assertSee('Computación')->assertSee('PRODEP')->assertSee('NIVEL 1')->assertSee(route('lines.show', $line));
        $this->get(route('lines.show', $line))->assertOk()->assertViewHas('researchers', fn ($rows) => $rows->total() === 1);
        $this->post(route('profile.update'), ['name' => $profile->name])->assertSessionHas('success');
        $this->assertCount(3, $profile->fresh()->degrees);
        $this->post(route('profile.update'), ['name' => $profile->name, 'research_lines_present' => 1, 'degrees_present' => 1, 'recognitions_present' => 1])->assertSessionHas('success');
        $this->assertSame([], $profile->fresh()->degrees);
        $this->assertSame([], $profile->fresh()->recognitions);
        $this->assertDatabaseMissing('research_line_researcher', ['researcher_id' => $profile->id]);
    }

    public function test_invalid_degrees_recognitions_and_line_ids_are_rejected_atomically(): void
    {
        $user = User::factory()->create();
        $profile = $this->researcher($user);
        $this->actingAs($user)->post(route('profile.update'), [
            'name' => 'Cambio inválido', 'degrees_present' => 1, 'degrees' => [['level' => 'Inventado']],
            'recognitions_present' => 1, 'prodep' => 1, 'prodep_start' => '2028-01-01', 'prodep_until' => '2027-01-01',
            'snii_level' => 'NIVEL 9', 'research_lines' => [99999],
        ])->assertSessionHasErrors(['degrees.0.level', 'prodep_until', 'snii_level', 'research_lines.0']);
        $this->assertSame('Ana Pérez', $profile->fresh()->name);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_admin_manages_unique_line_catalog_and_journal_link_is_in_navigation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.lines.create'))->assertOk();
        $this->post(route('admin.lines.store'), ['name' => 'Robótica'])->assertRedirect();
        $line = ResearchLine::firstOrFail();
        $this->post(route('admin.lines.store'), ['name' => 'Robótica'])->assertSessionHasErrors('name');
        $this->post(route('admin.lines.update', $line), ['name' => 'Robótica aplicada'])->assertRedirect();
        $this->get(route('admin.lines'))->assertOk()->assertSee('Robótica aplicada');
        config(['catalogue.journals_url' => null]);
        $this->get(route('projects.index'))->assertOk()->assertDontSee('Revistas');
        config(['catalogue.journals_url' => 'https://journals.example.edu/']);
        $this->get(route('projects.index'))->assertOk()->assertSee('Revistas')->assertSee('https://journals.example.edu/');
        $this->assertSame(1, DB::table('research_lines')->count());
    }
}
