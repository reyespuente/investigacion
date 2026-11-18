<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\Researcher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationAuthorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function profile(?User $user = null, string $name = 'Ana Pérez'): Researcher
    {
        return Researcher::create(['name' => $name, 'original_name' => $name, 'user_id' => $user?->id, 'orcid' => $user ? 'https://orcid.org/0000-0002-1825-0097' : null]);
    }

    private function paper(array $values = []): Publication
    {
        return Publication::create(array_replace([
            'title' => 'Referencia para completar',
            'year' => 2025,
            'type' => 'article',
            'venue' => 'Revista de prueba',
            'external_url' => null,
            'authors' => [],
            'metadata' => [],
            'search_text' => 'referencia para completar',
            'status' => 'incomplete',
        ], $values));
    }

    public function test_researcher_completes_missing_authors_and_publishes_without_a_link(): void
    {
        $user = User::factory()->create();
        $self = $this->profile($user);
        $coauthor = $this->profile(name: 'Beatriz López');
        $paper = $this->paper();
        $paper->researchers()->attach($self, ['state' => 'pending']);
        $this->actingAs($user)->get(route('account.review', $paper))
            ->assertOk()->assertSee('Autores en orden de publicación')->assertSee('Ana Pérez')->assertSee('Enlace al trabajo (opcional)');

        $this->post(route('editor.confirm', $paper), [
            'version' => 1, 'decision' => 'published', 'authorship' => 1, 'authors_editor' => 1,
            'authors' => [['name' => 'Nombre manipulado', 'researcher_id' => $coauthor->id]],
            'external_authors' => "María Externa Pérez, Juan Externo Ruiz\nMaría Externa Pérez",
        ])->assertRedirect();
        $paper->refresh();
        $this->assertSame('published', $paper->status);
        $this->assertNull($paper->external_url);
        $this->assertSame(['Beatriz López', 'María Externa Pérez', 'Juan Externo Ruiz', 'Ana Pérez'], array_column($paper->authors, 'nombre'));
        $this->assertSame([1, 2, 3, 4], array_column($paper->authors, 'posicion'));
        $this->assertSame($coauthor->orcid, $paper->authors[0]['orcid']);
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $paper->id, 'researcher_id' => $self->id, 'state' => 'confirmed']);
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $paper->id, 'researcher_id' => $coauthor->id, 'state' => 'pending']);
        $this->get(route('publications.show', $paper))->assertOk()->assertDontSee('Ir a la publicación')->assertSee('Enlace al trabajo original pendiente de agregar.');
        $this->assertSame('Beatriz López', $paper->csl()['author'][0]['literal']);
    }

    public function test_new_article_includes_the_owner_when_only_external_names_are_submitted(): void
    {
        $user = User::factory()->create();
        $self = $this->profile($user);
        $this->actingAs($user)->get(route('editor.create'))->assertOk()->assertSee('data-author-editor', false)->assertSee('Ana Pérez');
        $this->post(route('editor.store'), [
            'title' => 'Nuevo trabajo', 'year' => 2025, 'type' => 'article', 'venue' => 'Revista',
            'authors_editor' => 1, 'external_authors' => 'Laura Externa, Pedro Externo',
        ])->assertRedirect();
        $paper = Publication::firstOrFail();
        $this->assertSame(['Laura Externa', 'Pedro Externo', 'Ana Pérez'], array_column($paper->authors, 'nombre'));
        $this->assertSame($self->id, $paper->authors[2]['researcher_id']);
        $this->assertNull($paper->external_url);
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $paper->id, 'researcher_id' => $self->id, 'state' => 'confirmed']);
    }

    public function test_author_edit_preserves_structured_names_affiliations_and_existing_independent_decisions(): void
    {
        $user = User::factory()->create();
        $self = $this->profile($user);
        $other = $this->profile(name: 'Autor con decisión previa');
        $author = ['nombre' => 'Pérez, Ana', 'nombre_estructurado' => ['family' => 'Pérez', 'given' => 'Ana'], 'orcid' => $self->orcid, 'afiliaciones' => ['Institución; Facultad', 'Otra institución'], 'fuente' => 'original'];
        $paper = $this->paper(['authors' => [$author], 'status' => 'review']);
        $paper->researchers()->attach($self, ['state' => 'confirmed']);
        $dismissed = now()->format('Y-m-d H:i:s');
        $paper->researchers()->attach($other, ['state' => 'rejected', 'dismissed_at' => $dismissed]);
        $this->actingAs($user)->post(route('editor.confirm', $paper), [
            'version' => 1, 'decision' => 'published', 'authorship' => 1, 'authors_editor' => 1,
            'authors' => [
                ['name' => 'Pérez, Ana', 'researcher_id' => $self->id, 'affiliations' => 'Institución; Facultad; Otra institución'],
                ['name' => $other->name, 'researcher_id' => $other->id],
            ],
        ])->assertRedirect();
        $saved = $paper->fresh()->authors[0];
        $this->assertCount(2, $paper->fresh()->authors);
        foreach ($author as $key => $value) {
            $this->assertSame($value, $saved[$key]);
        }
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $paper->id, 'researcher_id' => $other->id, 'state' => 'rejected', 'dismissed_at' => $dismissed]);
    }

    public function test_invalid_authors_urls_and_stale_versions_cannot_partially_publish(): void
    {
        $user = User::factory()->create();
        $self = $this->profile($user);
        $paper = $this->paper();
        $paper->researchers()->attach($self, ['state' => 'pending']);
        $data = ['version' => 1, 'decision' => 'published', 'authorship' => 1, 'authors_editor' => 1];
        $this->actingAs($user)->post(route('editor.confirm', $paper), $data + ['authors' => [['name' => 'Inválido', 'researcher_id' => 999999]]])
            ->assertSessionHasErrors('authors.0.researcher_id');
        $this->post(route('editor.confirm', $paper), $data + ['external_url' => 'javascript:alert(1)'])->assertSessionHasErrors('external_url');
        $paper->update(['external_url' => 'javascript:alert(1)']);
        $this->post(route('editor.confirm', $paper), $data)->assertSessionHasErrors('external_url');
        $paper->update(['external_url' => null]);
        $this->post(route('editor.confirm', $paper), $data + ['external_authors' => str_repeat('a', 501)])->assertSessionHasErrors('authors');
        $this->post(route('editor.confirm', $paper), array_replace($data, ['version' => 2]))->assertStatus(409);
        $this->assertSame([], $paper->fresh()->authors);
        $this->assertSame('incomplete', $paper->fresh()->status);
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $paper->id, 'researcher_id' => $self->id, 'state' => 'pending']);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_author_recommendations_require_login_and_expose_only_public_identity_fields(): void
    {
        $user = User::factory()->create();
        $profile = $this->profile($user);
        $profile->update(['claim_email_hash' => Researcher::emailHash($user->email), 'contacts' => ['contact_email' => 'private@example.org']]);
        $this->getJson(route('authors.suggestions', ['q' => 'Ana']))->assertUnauthorized();
        $this->actingAs($user)->getJson(route('authors.suggestions', ['q' => 'Ana']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $profile->id)->assertDontSee($user->email)->assertDontSee('private@example.org')->assertDontSee('claim_email_hash');
        $this->getJson(route('authors.suggestions', ['q' => 'A']))->assertUnprocessable();
    }

    public function test_admin_can_review_and_batch_publish_complete_records_without_urls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->paper(['status' => 'review']);
        $this->actingAs($admin)->post(route('admin.review', $first), [
            'version' => 1, 'decision' => 'published', 'authors_editor' => 1,
            'external_authors' => 'Autora Externa',
        ])->assertRedirect();
        $profile = $this->profile();
        $second = $this->paper(['status' => 'review', 'authors' => [['nombre' => 'Autora Externa']]]);
        $second->researchers()->attach($profile, ['state' => 'confirmed']);
        $this->get(route('admin', ['ready' => '2']))->assertOk()->assertViewHas('publications', fn ($rows) => $rows->total() === 1);
        $this->post(route('admin.batch'), ['items' => [$second->id => 1], 'checked' => 1])->assertRedirect();
        $this->assertSame('published', $second->fresh()->status);
        $this->assertSame('published', $first->fresh()->status);
    }
}
