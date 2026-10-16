<?php

namespace Tests\Contract;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;
use stdClass;
use Symfony\Component\Yaml\Yaml;

/**
 * Validates API responses against openapi.yaml (OpenAPI 3.1 = JSON Schema 2020-12).
 */
class OpenApiContract
{
    private const SPEC_URI = 'https://contract.oodan.test/openapi.json';

    private const SERVER_PREFIX = '/api/v1';

    private static ?self $instance = null;

    private stdClass $spec;

    private Validator $validator;

    private function __construct(string $specPath)
    {
        $this->spec = json_decode(json_encode(Yaml::parseFile($specPath, Yaml::PARSE_OBJECT_FOR_MAP)));

        $this->validator = new Validator;
        $this->validator->setMaxErrors(5);
        $this->validator->parser()->setDefaultDraftVersion('2020-12');
        $this->validator->resolver()->registerRaw($this->spec, self::SPEC_URI);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self(dirname(__DIR__, 2).'/openapi.yaml');
    }

    /**
     * Returns null when the response matches the contract, otherwise a description of the mismatch.
     */
    public function responseError(string $method, string $uriPath, int $status, string $content): ?string
    {
        $method = strtolower($method);
        $operation = $this->findOperation($method, $uriPath);

        if ($operation === null) {
            return "No operation in openapi.yaml for {$method} {$uriPath}.";
        }

        $response = $operation->responses->{(string) $status} ?? null;

        if ($response === null) {
            return "Status {$status} is not documented for {$method} {$uriPath}.";
        }

        $response = $this->resolve($response);
        $schema = $response->content->{'application/json'}->schema ?? null;

        if ($schema === null) {
            return $content === '' ? null : "{$method} {$uriPath} → {$status} must have an empty body.";
        }

        $data = json_decode($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return "{$method} {$uriPath} → {$status} did not return JSON.";
        }

        return $this->schemaError($data, $schema->{'$ref'});
    }

    /**
     * Returns null when the data matches the schema reference (e.g. `#/components/schemas/Error`).
     */
    public function schemaError(mixed $data, string $ref): ?string
    {
        $result = $this->validator->validate($data, self::SPEC_URI.$ref);

        if ($result->isValid()) {
            return null;
        }

        return "Does not match {$ref}:\n".json_encode(
            (new ErrorFormatter)->format($result->error(), false),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function findOperation(string $method, string $uriPath): ?stdClass
    {
        if (! str_starts_with($uriPath, self::SERVER_PREFIX)) {
            return null;
        }

        $path = substr($uriPath, strlen(self::SERVER_PREFIX)) ?: '/';

        foreach ($this->spec->paths as $template => $item) {
            $pattern = '#^'.preg_replace('#\\\{[^/]+\\\}#', '[^/]+', preg_quote($template, '#')).'$#';

            if (preg_match($pattern, $path) && isset($item->{$method})) {
                return $item->{$method};
            }
        }

        return null;
    }

    private function resolve(stdClass $node): stdClass
    {
        while (isset($node->{'$ref'}) && str_starts_with($node->{'$ref'}, '#/components/responses/')) {
            $name = substr($node->{'$ref'}, strlen('#/components/responses/'));
            $node = $this->spec->components->responses->{$name}
                ?? throw new RuntimeException("Unknown response {$node->{'$ref'}}");
        }

        return $node;
    }
}
