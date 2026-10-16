<?php

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use Tests\Contract\OpenApiContract;
use Tests\TestCase;

class ContractHarnessTest extends TestCase
{
    #[Test]
    public function every_component_schema_compiles(): void
    {
        $spec = Yaml::parseFile(base_path('openapi.yaml'));

        $this->assertSame('3.1.0', $spec['openapi']);

        foreach (array_keys($spec['components']['schemas']) as $name) {
            // Validating any value forces the schema and all its $refs to be parsed.
            OpenApiContract::instance()->schemaError(null, "#/components/schemas/{$name}");
        }

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_reports_undocumented_statuses_and_shape_mismatches(): void
    {
        $contract = OpenApiContract::instance();

        $this->assertStringContainsString('not documented', $contract->responseError('GET', '/api/v1/me', 418, '{}'));
        $this->assertStringContainsString('No operation', $contract->responseError('GET', '/api/v1/nope', 200, '{}'));
        $this->assertStringContainsString('Does not match', $contract->responseError('GET', '/api/v1/me', 401, '{"message":"x"}'));
        $this->assertNull($contract->responseError('GET', '/api/v1/me', 401, '{"error":{"code":"unauthenticated","message":"x"}}'));
        $this->assertNull($contract->responseError('GET', '/api/v1/backups/0192f0a0-0000-7000-8000-000000000000', 404, '{"error":{"code":"not_found","message":"x"}}'));
        $this->assertStringContainsString('empty body', $contract->responseError('POST', '/api/v1/auth/logout', 204, '{}'));
    }

    #[Test]
    public function unknown_api_routes_use_the_error_shape(): void
    {
        $this->validateContract = false;

        $response = $this->getJson('/api/v1/does-not-exist')->assertNotFound();

        $this->assertMatchesSchema($response->json(), '#/components/schemas/Error');
        $response->assertJsonPath('error.code', 'not_found');
    }
}
