<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\Researcher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['catalogue.turnstile_enabled' => false, 'filesystems.default' => 'local']);
    }

    private function paper(array $values = []): Publication
    {
        return Publication::create(array_replace(['title' => 'Energía solar', 'year' => 2025, 'type' => 'article', 'venue' => 'Revista', 'external_url' => 'https://example.org/article', 'authors' => [['nombre' => 'Ana Pérez', 'afiliaciones' => ['Universidad del Desierto']]], 'metadata' => ['bibliografia' => ['palabras_clave' => ['Fotovoltaica']]], 'search_text' => 'energía solar ana pérez fotovoltaica', 'status' => 'review'], $values));
    }

    private function profile(?User $user = null): Researcher
    {
        return Researcher::create(['name' => 'Ana Pérez', 'original_name' => 'Ana Pérez', 'user_id' => $user?->id]);
    }

    public function test_admin_confirms_selected_profiles_and_publishes_atomically_then_opens_next(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->paper();
        $next = $this->paper();
        $chosen = $this->profile();
        $other = $this->profile();
        $first->researchers()->attach([$chosen->id => ['state' => 'pending'], $other->id => ['state' => 'pending']]);
        $this->actingAs($admin)->post('/admin/publicaciones/'.$first->id, ['version' => 1, 'decision' => 'published', 'confirmed_ids' => [$chosen->id], 'intent' => 'next', 'status' => 'review'])->assertRedirect(route('admin', ['status' => 'review', 'paper' => $next->id]));
        $this->assertSame('published', $first->fresh()->status);
        $this->assertSame(2, $first->fresh()->revision);
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $first->id, 'researcher_id' => $chosen->id, 'state' => 'confirmed']);
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $first->id, 'researcher_id' => $other->id, 'state' => 'pending']);
        $this->assertDatabaseHas('audit_events', ['entity_id' => $first->id, 'action' => 'admin_review']);
    }

    public function test_stale_review_never_overwrites_metadata_or_authorship(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $paper = $this->paper(['revision' => 2]);
        $profile = $this->profile();
        $paper->researchers()->attach($profile, ['state' => 'pending']);
        $this->actingAs($admin)->post('/admin/publicaciones/'.$paper->id, ['version' => 1, 'decision' => 'published', 'title' => 'Cambio obsoleto', 'confirmed_ids' => [$profile->id]])->assertStatus(409);
        $this->assertSame('Energía solar', $paper->fresh()->title);
        $this->assertSame('review', $paper->fresh()->status);
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $paper->id, 'state' => 'pending']);
    }

    public function test_incomplete_publication_cannot_partially_confirm_authors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $paper = $this->paper(['venue' => null]);
        $profile = $this->profile();
        $paper->researchers()->attach($profile, ['state' => 'pending']);
        $this->actingAs($admin)->post('/admin/publicaciones/'.$paper->id, ['version' => 1, 'decision' => 'published', 'confirmed_ids' => [$profile->id]])->assertStatus(422);
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $paper->id, 'state' => 'pending']);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_researcher_can_publish_both_pending_and_confirmed_own_references(): void
    {
        $user = User::factory()->create();
        $profile = $this->profile($user);
        foreach (['pending', 'confirmed'] as $state) {
            $paper = $this->paper();
            $paper->researchers()->attach($profile, ['state' => $state]);
            $this->actingAs($user)->get('/cuenta/publicaciones/'.$paper->id.'/revisar')->assertOk();
            $this->post('/cuenta/publicaciones/'.$paper->id.'/confirmar', ['version' => 1, 'decision' => 'published', 'authorship' => 1])->assertRedirect();
            $this->assertSame('published', $paper->fresh()->status);
            $this->assertSame(2, $paper->fresh()->revision);
        }
    }

    public function test_researcher_cannot_publish_unrelated_rejected_or_disabled_work(): void
    {
        $user = User::factory()->create();
        $profile = $this->profile($user);
        $paper = $this->paper();
        $this->actingAs($user)->get('/cuenta/publicaciones/'.$paper->id.'/revisar')->assertForbidden();
        $this->post('/cuenta/publicaciones/'.$paper->id.'/confirmar', ['version' => 1, 'decision' => 'published', 'authorship' => 1])->assertForbidden();
        $paper->researchers()->attach($profile, ['state' => 'rejected']);
        $this->post('/cuenta/publicaciones/'.$paper->id.'/confirmar', ['version' => 1, 'decision' => 'published', 'authorship' => 1])->assertForbidden();
        $paper->researchers()->updateExistingPivot($profile->id, ['state' => 'confirmed']);
        $paper->update(['status' => 'rejected']);
        $this->post('/cuenta/publicaciones/'.$paper->id.'/confirmar', ['version' => 1, 'decision' => 'published', 'authorship' => 1])->assertForbidden();
        $paper->update(['status' => 'review']);
        $user->update(['active' => false]);
        $this->post('/cuenta/publicaciones/'.$paper->id.'/confirmar', ['version' => 1, 'decision' => 'published', 'authorship' => 1])->assertForbidden();
    }

    public function test_researcher_must_explicitly_declare_authorship_and_cannot_confirm_someone_else(): void
    {
        $user = User::factory()->create();
        $profile = $this->profile($user);
        $other = $this->profile();
        $paper = $this->paper();
        $paper->researchers()->attach([$profile->id => ['state' => 'pending'], $other->id => ['state' => 'pending']]);
        $this->actingAs($user)->post('/cuenta/publicaciones/'.$paper->id.'/confirmar', ['version' => 1, 'decision' => 'published'])->assertSessionHasErrors('authorship');
        $this->post('/cuenta/publicaciones/'.$paper->id.'/confirmar', ['version' => 1, 'decision' => 'published', 'authorship' => 1, 'confirmed_ids' => [$other->id]])->assertRedirect();
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $paper->id, 'researcher_id' => $other->id, 'state' => 'pending']);
    }

    public function test_rejection_needs_a_reason_but_does_not_require_complete_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $paper = $this->paper(['year' => null, 'venue' => null]);
        $this->actingAs($admin)->post('/admin/publicaciones/'.$paper->id, ['version' => 1, 'decision' => 'rejected'])->assertSessionHasErrors('reason');
        $this->post('/admin/publicaciones/'.$paper->id, ['version' => 1, 'decision' => 'rejected', 'reason' => 'No corresponde a la institución.'])->assertRedirect();
        $this->assertSame('rejected', $paper->fresh()->status);
    }

    public function test_specific_search_scopes_do_not_mix_titles_authors_affiliations_and_tags(): void
    {
        $this->paper(['status' => 'published', 'abstract' => 'Análisis con microscopía']);
        $this->paper(['status' => 'review']);
        $this->getJson('/publicaciones?scope=title&q=solar')->assertJsonPath('total', 1);
        $this->getJson('/publicaciones?scope=author&q=Ana')->assertJsonPath('total', 1);
        $this->getJson('/publicaciones?scope=author&q=Desierto')->assertJsonPath('total', 0);
        $this->getJson('/publicaciones?scope=tag&q=Fotovoltaica')->assertJsonPath('total', 1);
        $this->getJson('/publicaciones?scope=tag&q=microscopía')->assertJsonPath('total', 0);
        $this->getJson('/publicaciones?q=microscopía')->assertJsonPath('total', 1);
        $this->getJson('/publicaciones?venue=Otra')->assertJsonPath('total', 0);
    }

    public function test_statistics_are_filterable_and_only_count_public_data(): void
    {
        $this->paper(['status' => 'published', 'doi' => '10.1234/example']);
        $this->paper(['status' => 'published', 'year' => 2024]);
        $this->paper(['year' => 2026]);
        $this->get('/estadisticas')->assertOk()->assertViewHas('total', 2);
        $this->get('/estadisticas?year=2025')->assertOk()->assertViewHas('total', 1)->assertViewHas('withDoi', 1);
        $this->getJson('/estadisticas?year=2026')->assertOk()->assertJsonStructure(['html']);
        $this->get('/')->assertDontSee('resúmenes para explorar');
    }

    public function test_administration_navigation_and_ready_filters_render_without_user_list_in_queue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->paper();
        $this->paper(['venue' => null]);
        $this->actingAs($admin)->get('/admin?ready=1')->assertOk()->assertViewHas('publications', fn ($rows) => $rows->total() === 1)->assertDontSee('Crear cuenta');
        $this->get('/admin?ready=0')->assertOk()->assertViewHas('publications', fn ($rows) => $rows->total() === 1);
        $this->get('/admin/usuarios')->assertOk()->assertSee('Crear cuenta');
        $this->get('/admin/sistema')->assertOk()->assertSee('data-settings-panel', false);
    }

    public function test_batch_publishes_only_explicit_complete_unambiguous_selection(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = $this->profile();
        $paper = $this->paper();
        $untouched = $this->paper();
        $paper->researchers()->attach($profile, ['state' => 'confirmed']);
        $this->actingAs($admin)->post('/admin/revision/lote', ['items' => [$paper->id => 1], 'checked' => 1])->assertRedirect();
        $this->assertSame('published', $paper->fresh()->status);
        $this->assertSame('review', $untouched->fresh()->status);
        $this->assertDatabaseHas('audit_events', ['entity_id' => $paper->id, 'action' => 'admin_review']);
    }

    public function test_stale_or_ambiguous_batch_rolls_back_every_selected_reference(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = $this->profile();
        $first = $this->paper();
        $second = $this->paper(['revision' => 2]);
        foreach ([$first, $second] as $paper) {
            $paper->researchers()->attach($profile, ['state' => 'confirmed']);
        }
        $this->actingAs($admin)->post('/admin/revision/lote', ['items' => [$first->id => 1, $second->id => 1], 'checked' => 1])->assertStatus(409);
        $this->assertSame('review', $first->fresh()->status);
        $this->assertDatabaseCount('audit_events', 0);
        $second->researchers()->updateExistingPivot($profile->id, ['state' => 'pending']);
        $this->post('/admin/revision/lote', ['items' => [$first->id => 1, $second->id => 2], 'checked' => 1])->assertStatus(422);
        $this->assertSame('review', $first->fresh()->status);
    }

    public function test_changed_year_updates_citation_without_claiming_old_date_precision(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $paper = $this->paper(['published_date' => '2025-05-15', 'metadata' => ['csl' => ['issued' => ['date-parts' => [[2025, 5, 15]]]]]]);
        $this->actingAs($admin)->post('/admin/publicaciones/'.$paper->id, ['version' => 1, 'decision' => 'published', 'year' => 2024])->assertRedirect();
        $this->assertNull($paper->fresh()->published_date);
        $this->assertSame([[2024]],$paper->fresh()->csl()['issued']['date-parts']);
    }
}
