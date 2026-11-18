<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Publication;
use App\Models\Researcher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicationInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function profile(): Researcher
    {
        $user = User::factory()->create();

        return Researcher::create(['name' => 'Investigador de prueba', 'original_name' => 'Investigador de prueba', 'user_id' => $user->id]);
    }

    private function paper(array $values = []): Publication
    {
        return Publication::create(array_replace([
            'title' => 'Referencia compartida',
            'year' => 2025,
            'type' => 'article',
            'venue' => 'Revista de prueba',
            'external_url' => 'https://example.org/paper',
            'authors' => [['nombre' => 'Investigador de prueba']],
            'metadata' => [],
            'search_text' => 'referencia compartida',
            'status' => 'review',
        ], $values));
    }

    public function test_dismissal_is_individual_and_preserves_the_publication_and_documents(): void
    {
        Storage::fake('local');
        $owner = $this->profile();
        $coauthor = $this->profile();
        $paper = $this->paper();
        $paper->researchers()->attach([$owner->id => ['state' => 'pending'], $coauthor->id => ['state' => 'confirmed']]);
        Storage::disk('local')->put('repository/documents/test.pdf', '%PDF-1.4 test');
        $document = Document::create([
            'researcher_id' => $coauthor->id,
            'publication_id' => $paper->id,
            'uploaded_by' => $coauthor->user_id,
            'title' => 'Documento del coautor',
            'kind' => 'publication',
            'path' => 'repository/documents/test.pdf',
            'disk' => 'local',
            'size' => 13,
            'license' => 'author_permission',
            'is_public' => true,
        ]);

        $this->actingAs($owner->user)->get(route('account.review', $paper))->assertOk()->assertSee('ELIMINAR PERMANENTEMENTE');
        $this->delete(route('editor.dismiss', $paper), ['version' => 1, 'confirmation' => 1])->assertRedirect(route('account', ['status' => 'pending']));
        foreach (['pending', 'published', 'all'] as $status) {
            $this->get(route('account', ['status' => $status]))
                ->assertOk()
                ->assertViewHas('publications', fn ($rows) => $rows->total() === 0)
                ->assertViewHas('counts', fn ($counts) => $counts['pending'] === 0 && $counts['published'] === 0);
        }
        $this->get(route('account.review', $paper))->assertForbidden();
        $this->post(route('editor.confirm', $paper), ['version' => 1, 'decision' => 'published', 'authorship' => 1])->assertForbidden();
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $paper->id, 'researcher_id' => $coauthor->id, 'state' => 'confirmed', 'dismissed_at' => null]);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists('repository/documents/test.pdf');
        $this->assertSame(1, $paper->fresh()->revision);
        $this->assertSame('review', $paper->fresh()->status);

        $this->actingAs($coauthor->user)->get(route('account'))->assertViewHas('publications', fn ($rows) => $rows->total() === 1);
        $this->post(route('editor.confirm', $paper), ['version' => 1, 'decision' => 'published', 'authorship' => 1])->assertRedirect();
        $this->get(route('publications.show', $paper))->assertOk();
        $this->actingAs($owner->user)->get(route('account', ['status' => 'all']))->assertViewHas('publications', fn ($rows) => $rows->total() === 0);
    }

    public function test_removal_requires_confirmation_and_a_current_revision(): void
    {
        $owner = $this->profile();
        $paper = $this->paper(['revision' => 2]);
        $paper->researchers()->attach($owner, ['state' => 'pending']);

        $this->actingAs($owner->user)->get(route('account.remove', $paper))->assertOk()->assertSee('Confirmo que quiero eliminar');
        $this->delete(route('editor.dismiss', $paper), ['version' => 2])->assertSessionHasErrors('confirmation');
        $this->delete(route('editor.dismiss', $paper), ['version' => 1, 'confirmation' => 1])->assertStatus(409);
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $paper->id, 'researcher_id' => $owner->id, 'dismissed_at' => null]);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_other_accounts_and_rejected_links_cannot_remove_an_inbox_entry(): void
    {
        $owner = $this->profile();
        $other = $this->profile();
        $paper = $this->paper();
        $paper->researchers()->attach($owner, ['state' => 'pending']);
        $data = ['version' => 1, 'confirmation' => 1, 'researcher_id' => $owner->id];

        $this->delete(route('editor.dismiss', $paper), $data)->assertRedirect(route('login'));
        $this->actingAs($other->user)->delete(route('editor.dismiss', $paper), $data)->assertForbidden();
        $this->get(route('account.remove', $paper))->assertForbidden();
        $paper->researchers()->attach($other, ['state' => 'rejected']);
        $this->delete(route('editor.dismiss', $paper), $data)->assertForbidden();
        $owner->user->update(['active' => false]);
        $this->actingAs($owner->user)->delete(route('editor.dismiss', $paper), $data)->assertForbidden();
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_removal_is_idempotent_and_next_review_skips_previously_removed_entries(): void
    {
        $owner = $this->profile();
        $first = $this->paper();
        $removed = $this->paper();
        $next = $this->paper(['status' => 'published']);
        foreach ([$first, $removed, $next] as $paper) {
            $paper->researchers()->attach($owner, ['state' => 'pending']);
        }
        $owner->publications()->updateExistingPivot($removed->id, ['dismissed_at' => now()]);

        $data = ['version' => 1, 'confirmation' => 1, 'intent' => 'next'];
        $this->actingAs($owner->user)->delete(route('editor.dismiss', $first), $data)->assertRedirect(route('account.review', $next));
        $first->update(['revision' => 2]);
        $this->delete(route('editor.dismiss', $first), $data)->assertRedirect(route('account.review', $next));
        $this->assertDatabaseCount('audit_events', 1);
        $this->assertDatabaseHas('audit_events', ['entity_id' => $first->id, 'action' => 'inbox_dismissed']);
    }

    public function test_publishing_another_reference_skips_removed_entries_and_public_profiles_stay_available(): void
    {
        $owner = $this->profile();
        $first = $this->paper();
        $removed = $this->paper(['status' => 'published']);
        $next = $this->paper(['status' => 'published']);
        foreach ([$first, $removed, $next] as $paper) {
            $paper->researchers()->attach($owner, ['state' => 'pending']);
        }
        $owner->publications()->updateExistingPivot($removed->id, ['state' => 'confirmed']);
        $this->actingAs($owner->user)->delete(route('editor.dismiss', $removed), ['version' => 1, 'confirmation' => 1])->assertRedirect();
        $this->post(route('editor.confirm', $first), ['version' => 1, 'decision' => 'published', 'authorship' => 1, 'intent' => 'next'])
            ->assertRedirect(route('account.review', $next));
        $this->get(route('researchers.show', $owner))->assertOk()->assertViewHas('publications', fn ($rows) => $rows->total() === 2);
        $this->assertNotNull(DB::table('publication_researcher')->where('publication_id', $removed->id)->where('researcher_id', $owner->id)->value('dismissed_at'));
    }
}
