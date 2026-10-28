<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_installation_renders_without_institution_data(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk()->assertSee('Aún no hay publicaciones disponibles.');
    }
}
