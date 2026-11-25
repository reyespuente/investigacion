<?php

namespace Tests\Feature;

use App\Models\Researcher;
use App\Models\ResearchLine;
use App\Models\ResearchProject;
use App\Models\User;
use Database\Seeders\ResearchLineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchLineManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_seeder_loads_official_lines_without_merging_namesakes_or_overwriting_edits(): void
    {
        $custom = ResearchLine::create(['name' => 'Línea local']);
        $this->seed(ResearchLineSeeder::class);

        $this->assertDatabaseCount('research_lines', 40);
        $this->assertSame(13, ResearchLine::whereNotNull('code')->distinct()->count('program'));
        $this->assertSame(2, ResearchLine::where('name', 'Bioingeniería')->count());
        $official = ResearchLine::where('code', 'LGAC-2025-CELA-DIBQ-31')->firstOrFail();
        $official->update(['name' => 'Bioingeniería aplicada']);
        $this->seed(ResearchLineSeeder::class);

        $this->assertDatabaseCount('research_lines', 40);
        $this->assertModelExists($custom);
        $this->assertSame('Bioingeniería aplicada', $official->fresh()->name);
        $this->get(route('lines.index', ['q' => $official->code]))->assertOk()
            ->assertSee('Bioingeniería aplicada')->assertDontSee('Línea local');
        $this->get(route('lines.show', $official))->assertOk()
            ->assertSee($official->code)->assertSee('Doctorado en Ciencias en Ingeniería Bioquímica');
    }

    public function test_admin_manages_lines_with_unique_codes_and_names_per_program(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = ['name' => 'Robótica', 'program' => 'Maestría', 'code' => 'lgac-001'];
        $this->post(route('admin.lines.store'), $data)->assertSessionHas('success');
        $line = ResearchLine::where('code', 'LGAC-001')->firstOrFail();
        $this->post(route('admin.lines.store'), [...$data, 'code' => 'LGAC-002'])->assertSessionHasErrors('name');
        $this->post(route('admin.lines.store'), [...$data, 'program' => 'Doctorado'])->assertSessionHasErrors('code');
        $this->post(route('admin.lines.store'), [...$data, 'program' => 'Doctorado', 'code' => 'LGAC-002'])->assertSessionHas('success');
        $this->post(route('admin.lines.update', $line), [...$data, 'name' => 'Robótica aplicada'])->assertSessionHas('success');

        $this->assertSame('Robótica aplicada', $line->fresh()->name);
        $this->assertDatabaseCount('research_lines', 2);
    }

    public function test_admin_can_assign_multiple_lines_and_edit_an_unclaimed_public_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = Researcher::create(['name' => 'Ana Pérez', 'original_name' => 'Ana Pérez', 'contacts' => ['website' => 'https://example.edu/ana']]);
        $line = ResearchLine::create(['name' => 'Robótica', 'program' => 'Maestría']);
        $otherLine = ResearchLine::create(['name' => 'Robótica', 'program' => 'Doctorado']);

        $this->actingAs($admin)->get(route('admin.researchers.edit', $profile))
            ->assertOk()->assertSee('Editar perfil de Ana Pérez')->assertSee('Doctorado');
        $this->post(route('admin.researchers.update', $profile), [
            'name' => 'Ana Pérez', 'department' => 'Posgrado', 'bio' => 'Investigadora en robótica.',
            'orcid' => 'https://orcid.org/0000-0002-1825-0097',
            'research_lines_present' => 1, 'research_lines' => [$line->id, $otherLine->id],
            'user_id' => $admin->id,
        ])->assertRedirect(route('researchers.show', $profile));

        $this->assertNull($profile->fresh()->user_id);
        $this->assertSame('https://example.edu/ana', $profile->fresh()->contacts['website']);
        $this->assertEqualsCanonicalizing([$line->id, $otherLine->id], $profile->researchLines()->pluck('research_lines.id')->all());
        auth()->logout();
        $this->get(route('researchers.show', $profile))->assertOk()
            ->assertSee('Investigadora en robótica.')->assertSee('ORCID')->assertSee('Robótica · Maestría')->assertSee('Robótica · Doctorado');
        $this->get(route('lines.show', $line))->assertOk()->assertSee('Ana Pérez');

        $this->actingAs($admin)->post(route('admin.researchers.update', $profile), [
            'name' => $profile->name, 'research_lines_present' => 1,
        ])->assertSessionHas('success');
        $this->assertCount(0, $profile->researchLines()->get());
    }

    public function test_members_cannot_edit_other_profiles_but_can_assign_their_own_lines(): void
    {
        $member = User::factory()->create();
        $own = Researcher::create(['name' => 'Ana Pérez', 'original_name' => 'Ana Pérez', 'user_id' => $member->id]);
        $other = Researcher::create(['name' => 'Otro investigador', 'original_name' => 'Otro investigador']);
        $first = ResearchLine::create(['name' => 'Sistemas']);
        $second = ResearchLine::create(['name' => 'Control']);
        $data = ['name' => $own->name, 'research_lines_present' => 1, 'research_lines' => [$first->id, $second->id], 'researcher_id' => $other->id];

        $this->actingAs($member)->get(route('admin.researchers.edit', $other))->assertForbidden();
        $this->post(route('admin.researchers.update', $other), $data)->assertForbidden();
        $this->delete(route('admin.lines.destroy', $first), ['confirm_delete' => 1])->assertForbidden();
        $this->post(route('profile.update'), $data)->assertSessionHas('success');
        $this->assertCount(2, $own->researchLines()->get());
        $this->assertCount(0, $other->researchLines()->get());
    }

    public function test_admin_must_confirm_line_deletion_and_only_links_are_removed(): void
    {
        $line = ResearchLine::create(['name' => 'Robótica']);
        $profile = Researcher::create(['name' => 'Ana Pérez', 'original_name' => 'Ana Pérez']);
        $project = ResearchProject::create(['name' => 'Proyecto', 'description' => 'Descripción']);
        $line->researchers()->attach($profile);
        $line->projects()->attach($project);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->delete(route('admin.lines.destroy', $line))->assertSessionHasErrors('confirm_delete');
        $this->assertModelExists($line);
        $this->delete(route('admin.lines.destroy', $line), ['confirm_delete' => 1])->assertRedirect(route('admin.lines'));

        $this->assertModelMissing($line);
        $this->assertModelExists($profile);
        $this->assertModelExists($project);
        $this->assertDatabaseCount('research_line_researcher', 0);
        $this->assertDatabaseCount('research_line_research_project', 0);
        $this->assertDatabaseHas('audit_events', ['entity' => 'research_line', 'entity_id' => $line->id, 'action' => 'deleted']);
    }
}
