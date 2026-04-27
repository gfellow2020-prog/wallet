<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRequestContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_include_x_request_id_header(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertUnauthorized();
        $response->assertHeader('X-Request-Id');
        $id = $response->headers->get('X-Request-Id');
        $this->assertIsString($id);
        $this->assertNotSame('', $id);
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',
            $id
        );
    }

    public function test_client_may_supply_x_request_id_when_safe(): void
    {
        $response = $this->withHeader('X-Request-Id', 'acme-trace-1')
            ->getJson('/api/me');

        $response->assertUnauthorized();
        $response->assertHeader('X-Request-Id', 'acme-trace-1');
    }

    public function test_unsafe_x_request_id_is_replaced(): void
    {
        $response = $this->withHeader('X-Request-Id', 'bad id')
            ->getJson('/api/me');

        $response->assertUnauthorized();
        $id = $response->headers->get('X-Request-Id');
        $this->assertNotSame('bad id', $id);
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',
            $id
        );
    }
}
