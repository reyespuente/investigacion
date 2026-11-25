<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\Researcher;
use App\Models\ResearchProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearcherDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_directory_ranks_by_public_publications_and_counts_each_project_once(): void
    {
        $first = $this->researcher('Zoe Juárez');
        $second = $this->researcher('Abril García');
        $third = $this->researcher('César Torres');
        $this->publication($first, 'Trabajo uno');
        $this->publication($first, 'Trabajo dos');
        $this->publication($second, 'Trabajo público');
        $this->publication($second, 'Trabajo privado', 'review');
        $this->publication($second, 'Atribución pendiente', state: 'pending');
        $this->publication($third, 'Otra atribución pendiente', state: 'pending');

        $ownProject = ResearchProject::create(['name' => 'Proyecto propio', 'description' => '', 'responsible_researcher_id' => $first->id]);
        $teamProject = ResearchProject::create(['name' => 'Proyecto compartido', 'description' => '', 'responsible_researcher_id' => $second->id]);
        $first->projects()->attach($ownProject, ['name' => $first->name, 'position' => 1]);
        $first->projects()->attach($teamProject, ['name' => $first->name, 'position' => 1]);

        $this->get(route('researchers.index'))->assertOk()
            ->assertSeeInOrder([$first->name, '2 publicaciones', '2 proyectos', $second->name, '1 publicación', '1 proyecto', $third->name, '0 publicaciones', '0 proyectos'])
            ->assertDontSee('publicaciones confirmadas');
        $this->get(route('researchers.index', ['q' => 'Abril']))->assertOk()
            ->assertSee($second->name)->assertDontSee($first->name);
    }

    public function test_profile_shows_only_the_selected_section_and_keeps_publication_pagination_in_its_tab(): void
    {
        $profile = $this->researcher('Zoe Juárez');
        $profile->update(['bio' => 'Biografía académica de prueba']);
        $project = ResearchProject::create(['name' => 'Proyecto exclusivo de prueba', 'description' => '', 'responsible_researcher_id' => $profile->id]);
        foreach (range(1, 11) as $number) {
            $this->publication($profile, "Artículo público {$number} del perfil");
        }
        $this->publication($profile, 'Borrador no visible', 'review');
        $this->publication($profile, 'Autoría no visible', state: 'pending');

        $this->get(route('researchers.show', $profile))->assertOk()->assertSee('Biografía académica de prueba')
            ->assertDontSee('Artículo público')->assertDontSee($project->name)->assertViewMissing('publications');
        $this->get(route('researchers.show', ['researcher' => $profile, 'seccion' => 'publicaciones']))->assertOk()
            ->assertSee('Artículo público 11 del perfil')->assertDontSee('Artículo público 1 del perfil')
            ->assertDontSee('Biografía académica de prueba')->assertDontSee($project->name)
            ->assertDontSee('Borrador no visible')->assertDontSee('Autoría no visible')
            ->assertViewHas('publications', fn ($publications) => $publications->total() === 11 && str_contains($publications->url(2), 'seccion=publicaciones'));
        $this->get(route('researchers.show', ['researcher' => $profile, 'seccion' => 'publicaciones', 'page' => 2]))->assertOk()
            ->assertSee('Artículo público 1 del perfil')->assertDontSee('Artículo público 11 del perfil');
        $this->get(route('researchers.show', ['researcher' => $profile, 'seccion' => 'proyectos']))->assertOk()
            ->assertSee($project->name)->assertSee('Como responsable técnico')
            ->assertDontSee('Biografía académica de prueba')->assertDontSee('Artículo público');
    }

    private function researcher(string $name): Researcher
    {
        return Researcher::create(['name' => $name, 'original_name' => $name]);
    }

    private function publication(Researcher $researcher, string $title, string $status = 'published', string $state = 'confirmed'): void
    {
        $publication = Publication::create([
            'title' => $title, 'year' => 2025, 'authors' => [], 'metadata' => [], 'search_text' => $title, 'status' => $status,
        ]);
        $researcher->publications()->attach($publication, ['state' => $state]);
    }
}
