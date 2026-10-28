<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Publication;
use App\Models\Researcher;
use App\Models\User;
use App\Services\CatalogueImport;
use App\Services\GoogleIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['catalogue.turnstile_enabled' => false, 'catalogue.domains' => ['staff.example.edu'], 'filesystems.default' => 'local']);
    }

    private function paper(array $values = []): Publication
    {
        return Publication::create(array_replace(['title' => 'Energía y procesos sostenibles', 'year' => 2025, 'type' => 'article', 'venue' => 'Revista de prueba', 'external_url' => 'https://example.org/paper', 'authors' => [['posicion' => 1, 'nombre' => 'Ana Pérez', 'orcid' => 'https://orcid.org/0000-0002-1825-0097', 'afiliaciones' => []]], 'metadata' => [], 'search_text' => 'energía y procesos sostenibles ana pérez', 'status' => 'published'], $values));
    }

    private function owner(): array
    {
        $u = User::factory()->create(['email' => 'ana@staff.example.edu', 'google_id' => 'google-123']);
        $p = Researcher::create(['name' => 'Ana Pérez', 'original_name' => 'Ana Pérez', 'legacy_id' => 1, 'user_id' => $u->id]);

        return [$u, $p];
    }

    public function test_public_search_never_exposes_review_items(): void
    {
        $this->paper();
        $hidden = $this->paper(['title' => 'Referencia oculta', 'search_text' => 'referencia oculta', 'status' => 'review']);
        $this->get('/')->assertOk()->assertSee('Energía y procesos sostenibles');
        $this->getJson('/publicaciones?q=energía')->assertOk()->assertJsonPath('total', 1)->assertDontSee('Referencia oculta');
        $this->get('/publicaciones/'.$hidden->id)->assertNotFound();
        $this->getJson('/publicaciones?year=2024')->assertJsonPath('total', 0);
    }

    public function test_institutional_google_identity_requires_verified_exact_domain(): void
    {
        $identity = new class
        {
            public array $user = ['email_verified' => true];

            public function getEmail()
            {
                return 'ana@staff.example.edu';
            }

            public function getId()
            {
                return 'abc';
            }

            public function getName()
            {
                return 'Ana';
            }
        };
        $u = app(GoogleIdentity::class)->resolve($identity);
        $this->assertSame('member', $u->role);
        $this->assertSame($u->id, app(GoogleIdentity::class)->resolve($identity)->id);
        $bad = new class
        {
            public array $user = ['email_verified' => true];

            public function getEmail()
            {
                return 'evil@staff.example.edu.attacker.test';
            }

            public function getId()
            {
                return 'evil';
            }
        };
        $this->expectException(HttpException::class);
        app(GoogleIdentity::class)->resolve($bad);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $identity = new class
        {
            public array $user = ['email_verified' => false];

            public function getEmail()
            {
                return 'ana@staff.example.edu';
            }

            public function getId()
            {
                return 'unverified';
            }
        };
        $this->expectException(HttpException::class);
        app(GoogleIdentity::class)->resolve($identity);
    }

    public function test_claim_requires_matching_private_email_and_google(): void
    {
        $u = User::factory()->create(['email' => 'ana@staff.example.edu', 'google_id' => 'g1']);
        $p = Researcher::create(['name' => 'Ana', 'original_name' => 'Ana', 'claim_email_hash' => Researcher::emailHash($u->email)]);
        $this->actingAs($u)->post('/investigadores/'.$p->id.'/reclamar')->assertRedirect('/cuenta');
        $this->assertSame($u->id, $p->fresh()->user_id);
        $other = User::factory()->create(['email' => 'other@staff.example.edu', 'google_id' => 'g2']);
        $this->actingAs($other)->post('/investigadores/'.$p->id.'/reclamar')->assertForbidden();
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_domain_account_alone_cannot_claim_another_profile(): void
    {
        $u = User::factory()->create(['email' => 'ana@staff.example.edu', 'google_id' => 'g1']);
        $p = Researcher::create(['name' => 'Otra persona', 'original_name' => 'Otra persona', 'claim_email_hash' => Researcher::emailHash('someone@staff.example.edu')]);
        $this->actingAs($u)->post('/investigadores/'.$p->id.'/reclamar')->assertForbidden();
        $this->assertNull($p->fresh()->user_id);
    }

    public function test_member_cannot_edit_others_or_open_admin(): void
    {
        [$u,$p] = $this->owner();
        $paper = $this->paper();
        $this->actingAs($u)->get('/cuenta/publicaciones/'.$paper->id.'/editar')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->post('/admin/configuracion', ['primary_color' => '#000000'])->assertForbidden();
    }

    public function test_editor_preserves_orcid_and_detects_version_conflict(): void
    {
        [$u,$profile] = $this->owner();
        $paper = $this->paper();
        $paper->researchers()->attach($profile->id, ['state' => 'confirmed']);
        $data = ['title' => 'Referencia corregida', 'year' => 2025, 'type' => 'article', 'venue' => 'Revista', 'external_url' => 'https://example.org/paper', 'authors_text' => 'Ana Pérez', 'version' => 1];
        $this->actingAs($u)->post('/cuenta/publicaciones/'.$paper->id, $data)->assertRedirect();
        $this->assertSame('https://orcid.org/0000-0002-1825-0097', $paper->fresh()->authors[0]['orcid']);
        $this->assertSame('researcher', $paper->fresh()->origin);
        $this->post('/cuenta/publicaciones/'.$paper->id, $data)->assertStatus(409);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_exports_include_only_filtered_public_rows_and_escape_formulas(): void
    {
        $p = $this->paper(['title' => '=HYPERLINK("bad")', 'search_text' => 'formula', 'year' => 2025]);
        $this->paper(['status' => 'review', 'title' => 'Secreto']);
        $response = $this->post('/exportar', ['format' => 'csv', 'year' => 2025]);
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Secreto', $csv);
        $this->post('/exportar', ['format' => 'json', 'year' => 2024])->assertOk()->assertExactJson([]);
        foreach (['xml', 'bib', 'ris', 'xlsx'] as $format) {
            $this->post('/exportar', ['format' => $format, 'year' => 2025])->assertOk();
        }
    }

    public function test_turnstile_is_validated_server_side_and_action_is_bound(): void
    {
        $this->paper();
        config(['catalogue.turnstile_enabled' => true, 'catalogue.turnstile_secret_key' => 'test-secret', 'catalogue.turnstile_hostname' => 'localhost']);
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => true, 'hostname' => 'localhost', 'action' => 'login'])->push(['success' => true, 'hostname' => 'localhost', 'action' => 'export'])]);
        $this->postJson('/exportar', ['format' => 'json', 'cf-turnstile-response' => 'token'])->assertStatus(422);
        $this->postJson('/exportar', ['format' => 'json', 'cf-turnstile-response' => 'fresh-token'])->assertOk();
    }

    public function test_private_documents_are_not_public_and_upload_does_not_publish_by_default(): void
    {
        Storage::fake('local');
        [$u,$profile] = $this->owner();
        $this->actingAs($u)->post('/cuenta/documentos', ['title' => 'Mi certificado', 'kind' => 'certification', 'license' => 'author_permission', 'rights' => '1', 'document' => UploadedFile::fake()->createWithContent('certificado.pdf', "%PDF-1.4\nTest document")])->assertRedirect();
        $doc = Document::firstOrFail();
        $this->assertFalse($doc->is_public);
        Storage::assertExists($doc->path);
        $this->post('/salir');
        $this->get('/documentos/'.$doc->id)->assertNotFound();
        $this->get('/documentos/'.$doc->id.'/archivo')->assertNotFound();
    }

    public function test_uploaded_file_must_be_pdf_and_have_rights_confirmation(): void
    {
        Storage::fake('local');
        [$u] = $this->owner();
        $this->actingAs($u)->post('/cuenta/documentos', ['title' => 'Documento', 'kind' => 'other', 'license' => 'cc_by', 'document' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain')])->assertSessionHasErrors(['document', 'rights']);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_admin_can_create_outside_domain_but_password_change_is_enforced(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/usuarios', ['name' => 'Invitado', 'email' => 'guest@external.test', 'role' => 'member', 'password' => 'TemporaryPass123!', 'password_confirmation' => 'TemporaryPass123!'])->assertRedirect();
        $u = User::where('email', 'guest@external.test')->firstOrFail();
        $this->assertTrue($u->must_change_password);
        $this->actingAs($u)->get('/cuenta')->assertRedirect('/cuenta/contrasena');
    }

    public function test_admin_can_change_branding_but_invalid_css_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $d = ['institution' => 'Universidad de prueba', 'short_name' => 'UP', 'portal_name' => 'Repositorio UP', 'primary_color' => '#123456', 'accent_color' => '#126633'];
        $this->actingAs($admin)->post('/admin/configuracion', $d)->assertRedirect();
        $this->get('/')->assertOk()->assertSee('Repositorio UP');
        $this->post('/admin/configuracion', array_replace($d, ['primary_color' => 'red;display:none']))->assertSessionHasErrors('primary_color');
    }

    public function test_import_is_idempotent_and_keeps_user_changes(): void
    {
        $profile = Researcher::create(['legacy_id' => 10, 'name' => 'Ana', 'original_name' => 'Ana']);
        $file = tempnam(sys_get_temp_dir(), 'catalog-test-');
        $row = ['publicacion_id' => 77, 'titulo' => 'Fuente inicial', 'anio' => 2025, 'fecha' => '2025', 'tipo' => 'article', 'revista' => 'Revista', 'doi' => null, 'enlace' => 'https://example.org/paper', 'autores' => [['nombre' => 'Ana', 'posicion' => 1]], 'estado' => 'lista', 'personas_confirmadas' => [['id' => 10]], 'bibliografia' => []];
        file_put_contents($file, json_encode($row)."\n");
        try {
            $service = app(CatalogueImport::class);
            $service->import($file);
            $service->import($file);
            $this->assertDatabaseCount('publications', 1);
            $this->assertDatabaseCount('publication_researcher', 1);
            $p = Publication::first();
            $p->update(['title' => 'Edición del investigador', 'user_modified_at' => now()]);
            $service->import($file);
            $this->assertSame('Edición del investigador', $p->fresh()->title);
        } finally {
            unlink($file);
        }
    }

    public function test_pdf_ficha_and_html_escaping(): void
    {
        $p = $this->paper(['title' => '<script>alert(1)</script>']);
        $this->get('/publicaciones/'.$p->id)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->post('/publicaciones/'.$p->id.'/ficha')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_cookie_choice_and_all_primary_pages_render(): void
    {
        $this->paper();
        foreach (['/', '/publicaciones', '/investigadores', '/estadisticas', '/privacidad', '/cookies', '/entrar', '/olvide-contrasena'] as $url) {
            $this->get($url)->assertOk();
        }$this->postJson('/cookies', ['choice' => 'essential'])->assertOk()->assertCookie('cookie_preferences');
        $this->postJson('/cookies', ['choice' => 'invalid'])->assertStatus(422);
        [$u] = $this->owner();
        $this->actingAs($u)->get('/cuenta')->assertOk();
        $this->get('/cuenta/perfil')->assertOk();
        $this->get('/cuenta/publicaciones/nueva')->assertOk();
        $u->update(['role' => 'admin']);
        foreach (['/admin', '/admin/configuracion', '/admin/historial'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_uploaded_logo_is_processed_and_served_without_exposing_storage_path(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/configuracion', ['institution' => 'Universidad', 'short_name' => 'UNI', 'portal_name' => 'Repositorio', 'primary_color' => '#123456', 'accent_color' => '#126633', 'institution_logo' => UploadedFile::fake()->image('logo.png', 300, 150)])->assertRedirect();
        $this->get('/branding/institution')->assertOk()->assertHeader('content-type', 'image/webp');
    }

    public function test_administrator_can_review_attribution_with_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = Researcher::create(['name' => 'Ana', 'original_name' => 'Ana']);
        $paper = $this->paper();
        $this->actingAs($admin)->post('/admin/publicaciones/'.$paper->id.'/autoria', ['researcher_id' => $profile->id, 'state' => 'confirmed', 'reason' => 'ORCID de la autoría cotejado con el perfil.'])->assertRedirect();
        $this->assertDatabaseHas('publication_researcher', ['publication_id' => $paper->id, 'researcher_id' => $profile->id, 'state' => 'confirmed']);
        $this->assertDatabaseHas('audit_events', ['action' => 'attribution_review']);
    }

    public function test_csl_keeps_literal_names_when_a_structured_name_is_empty(): void
    {
        $p = $this->paper(['authors' => [['nombre' => 'Instituto de investigación', 'nombre_estructurado' => []]]]);
        $this->assertSame(['literal' => 'Instituto de investigación'],$p->csl()['author'][0]);
    }
}
