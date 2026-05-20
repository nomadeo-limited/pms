<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_api_base_route_returns_404(): void
    {
        $this->getJson('/')->assertNotFound();
    }
}
