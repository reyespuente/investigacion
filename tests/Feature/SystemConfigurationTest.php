<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Researcher;
use App\Models\User;
use App\Services\RuntimeConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_configuration_survives_persistent_cache_on_later_requests(): void
    {
        config(['cache.default' => 'database']);
        RuntimeConfiguration::forget();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/sistema', $this->data(['storage_mode' => 's3', 's3_endpoint' => 'https://objects.example.org', 's3_region' => 'test', 's3_bucket' => 'persistent-bucket', 's3_key' => 'fixture-key', 's3_secret' => 'fixture-secret']))->assertRedirect();
        $this->get('/')->assertOk();
        $this->get('/')->assertOk();
        $this->get('/admin/sistema')->assertOk()->assertDontSee('fixture-secret');
        $this->assertSame('persistent-bucket', config('filesystems.disks.'.config('filesystems.default').'.bucket'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['catalogue.turnstile_enabled' => false, 'filesystems.default' => 'local']);
    }

    private function data(array $extra = []): array
    {
        return array_replace(['current_password' => 'password', 'app_url' => 'http://localhost', 'domains' => 'staff.example.edu', 'mail_mode' => 'smtp', 'mail_host' => 'smtp.example.edu', 'mail_port' => 587, 'mail_scheme' => 'smtp', 'mail_from_address' => 'repo@example.edu', 'mail_from_name' => 'Repositorio', 'storage_mode' => 'local', 'turnstile_enabled' => '0'], $extra);
    }

    public function test_system_settings_require_administrator_and_password(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/admin/sistema')->assertForbidden();
        $this->post('/admin/sistema', $this->data())->assertForbidden();
        $u->update(['role' => 'admin']);
        $this->post('/admin/sistema', $this->data(['current_password' => 'incorrect']))->assertSessionHasErrors('current_password');
        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_service_credentials_are_encrypted_never_rendered_or_audited_as_text(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/sistema', $this->data(['mail_password' => 'smtp-test-secret', 'mail_username' => 'smtp-user', 'google_client_id' => 'example-client', 'google_client_secret' => 'oauth-test-secret']))->assertRedirect();
        $stored = DB::table('site_settings')->where('key', 'runtime.mail_password')->value('value');
        $this->assertNotSame('smtp-test-secret', $stored);
        $this->get('/admin/sistema')->assertOk()->assertDontSee('smtp-test-secret')->assertDontSee('oauth-test-secret');
        $this->assertSame('smtp-test-secret', config('mail.mailers.smtp.password'));
        $this->assertStringNotContainsString('smtp-test-secret', DB::table('audit_events')->pluck('after')->implode(' '));
        $this->post('/admin/sistema', $this->data(['mail_password' => '', 'google_client_secret' => '']))->assertRedirect();
        app(RuntimeConfiguration::class)->apply();
        $this->assertSame('smtp-test-secret', config('mail.mailers.smtp.password'));
        $this->assertSame('oauth-test-secret', config('services.google.client_secret'));
    }

    public function test_storage_changes_keep_existing_document_connection(): void
    {
        $u = User::factory()->create(['role' => 'admin']);
        $profile = Researcher::create(['name' => 'Ana', 'original_name' => 'Ana', 'user_id' => $u->id]);
        $first = $this->data(['storage_mode' => 's3', 's3_endpoint' => 'https://objects.example.org', 's3_region' => 'test-region', 's3_bucket' => 'first-bucket', 's3_key' => 'fixture-key', 's3_secret' => 'fixture-secret', 's3_use_path_style_endpoint' => '1']);
        $this->actingAs($u)->post('/admin/sistema', $first)->assertRedirect();
        app(RuntimeConfiguration::class)->apply();
        $disk = config('filesystems.default');
        $doc = Document::create(['researcher_id' => $profile->id, 'uploaded_by' => $u->id, 'title' => 'Documento', 'kind' => 'other', 'license' => 'private', 'path' => 'repository/documents/example.pdf', 'disk' => $disk, 'size' => 20]);
        $this->post('/admin/sistema', $this->data(['storage_mode' => 's3', 's3_endpoint' => 'https://objects.example.org', 's3_region' => 'test-region', 's3_bucket' => 'second-bucket', 's3_use_path_style_endpoint' => '1']))->assertRedirect();
        $this->post('/admin/sistema', $this->data(['storage_mode' => 'local']))->assertRedirect();
        app(RuntimeConfiguration::class)->apply();
        $this->assertSame('local', config('filesystems.default'));
        $this->assertSame($disk, $doc->fresh()->disk);
        $this->assertSame('first-bucket', config('filesystems.disks.'.$disk.'.bucket'));
        $this->assertDatabaseCount('storage_profiles', 2);
    }

    public function test_domains_are_exact_and_installation_screens_render(): void
    {
        $u = User::factory()->create(['role' => 'admin']);
        $this->actingAs($u)->get('/admin/instalacion')->assertOk();
        $this->get('/admin/sistema')->assertOk();
        $this->post('/admin/sistema', $this->data(['domains' => 'staff.example.edu, faculty.example.edu']))->assertRedirect();
        app(RuntimeConfiguration::class)->apply();
        $this->assertSame(['staff.example.edu', 'faculty.example.edu'], config('catalogue.domains'));
        $this->post('/admin/sistema', $this->data(['domains' => '*.example.edu']))->assertSessionHasErrors('domains');
    }

    public function test_sensitive_fields_are_not_flashed_after_validation_failure(): void
    {
        $u = User::factory()->create(['role' => 'admin']);
        $this->actingAs($u)->post('/admin/sistema', $this->data(['domains' => '*', 'mail_password' => 'smtp-secret', 's3_secret' => 'bucket-secret', 'google_client_secret' => 'google-secret']))->assertSessionHasErrors('domains');
        foreach (['mail_password', 's3_secret', 'google_client_secret', 'current_password'] as $key) {
            $this->assertNull(session('_old_input.'.$key));
        }
    }

    public function test_disabled_owner_cannot_read_private_document_even_with_an_existing_session(): void
    {
        Storage::fake('local');
        $u = User::factory()->create(['active' => false]);
        $profile = Researcher::create(['name' => 'Ana', 'original_name' => 'Ana', 'user_id' => $u->id]);
        Storage::put('private.pdf', '%PDF-1.4');
        $doc = Document::create(['researcher_id' => $profile->id, 'uploaded_by' => $u->id, 'title' => 'Privado', 'kind' => 'other', 'license' => 'private', 'path' => 'private.pdf', 'disk' => 'local', 'size' => 8]);
        $this->actingAs($u)->get('/documentos/'.$doc->id.'/archivo')->assertNotFound();
    }

    public function test_installer_does_not_reset_existing_administrator(): void
    {
        config(['catalogue.admin_email' => 'installer@example.edu', 'catalogue.admin_name' => 'Operador', 'catalogue.admin_password' => 'A-strong-bootstrap-pass-2026']);
        $this->artisan('repository:install', ['--no-interaction' => true])->assertSuccessful();
        $u = User::where('email', 'installer@example.edu')->firstOrFail();
        $u->update(['password' => Hash::make('Changed-password-123')]);
        $this->artisan('repository:install', ['--no-interaction' => true])->assertSuccessful();
        $this->assertTrue(Hash::check('Changed-password-123',$u->fresh()->password));
        $this->assertDatabaseCount('users',1);
    }
}
