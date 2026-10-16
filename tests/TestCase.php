<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Contract\OpenApiContract;

abstract class TestCase extends BaseTestCase
{
    /**
     * Validate every `/api/v1` response against openapi.yaml.
     */
    protected bool $validateContract = true;

    /**
     * The JWT guard caches its user, and the application is shared between the requests of a
     * test, so forget it to make every request authenticate from its own headers.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->guard('api')->forgetUser();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * Every API response produced in a test must match openapi.yaml.
     *
     * @param  Response  $response
     * @param  Request  $request
     */
    protected function createTestResponse($response, $request): TestResponse
    {
        $testResponse = parent::createTestResponse($response, $request);

        if ($this->shouldValidateContract($request)) {
            $error = OpenApiContract::instance()->responseError(
                $request->getMethod(),
                '/'.ltrim($request->getPathInfo(), '/'),
                $response->getStatusCode(),
                (string) $response->getContent(),
            );

            $this->assertNull($error, $error ?? '');
        }

        return $testResponse;
    }

    /**
     * Assert that data matches a schema in openapi.yaml, e.g. `#/components/schemas/Error`.
     */
    protected function assertMatchesSchema(mixed $data, string $ref): void
    {
        $data = json_decode(json_encode($data));
        $error = OpenApiContract::instance()->schemaError($data, $ref);

        $this->assertNull($error, $error ?? '');
    }

    protected function shouldValidateContract($request): bool
    {
        return $this->validateContract && $request->is('api/v1/*');
    }
}
