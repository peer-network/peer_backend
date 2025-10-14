<?php

declare(strict_types=1);

namespace Fawaz\Handler;

use Fawaz\GraphQLSchemaBuilder;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Error\FormattedError;
use GraphQL\Validator\Rules\QueryComplexity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fawaz\Utils\PeerLoggerInterface;
use Slim\Psr7\Response;
use GraphQL\Error\DebugFlag;

class GraphQLHandler implements RequestHandlerInterface
{
    public function __construct(
        protected PeerLoggerInterface $logger,
        protected GraphQLSchemaBuilder $schemaBuilder,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $rawBody = (string) $request->getBody();
        $body = trim($rawBody);

        if ($body === '' || $body === 'null') {
            //$this->logger->error("GraphQL request body is empty, null, spaces.");
            return $this->errorResponse("Empty or invalid request body. Expected JSON.", 400);
        }

        $decodedBody = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedBody)) {
            //$this->logger->error("Invalid JSON format: " . json_last_error_msg());
            return $this->errorResponse("Invalid JSON format. Expected a valid JSON object.", 400);
        }

        if (!isset($decodedBody['query']) || trim($decodedBody['query']) === '') {
            //$this->logger->error("GraphQL query is missing or contains only whitespace.");
            return $this->errorResponse("Invalid GraphQL query. Expected a valid query string.", 400);
        }

        $this->logger->info("GraphQLHandler processing request.");

        //$this->logger->info("Received raw body: " . json_encode($rawBody));
        $authorizationHeader = $request->getHeader('Authorization');
        $bearerToken = null;
        if (!empty($authorizationHeader)) {
            $parts = explode(' ', $authorizationHeader[0]);
            if (count($parts) === 2 && strtolower($parts[0]) === 'bearer') {
                $bearerToken = $parts[1];
            }
        }
        
        if ($this->schemaBuilder->setCurrentUserId($bearerToken) === false) {
            return $this->errorResponse(
                "Invalid Access Token", 
                401
            );
        }
        $schema = $this->schemaBuilder->build();

        $context = [
            'request' => $request,
            'bearerToken' => $bearerToken
        ];

        $config = ServerConfig::create()
            ->setSchema($schema)
            ->setContext($context)
            ->setErrorFormatter(fn ($error) => FormattedError::createFromException($error))
            ->setQueryBatching(true)
            ->setDebugFlag();

        $server = new StandardServer($config);
        $response = new Response();
        $response = $server->processPsrRequest($request, $response, $response->getBody());

        if (!is_null($bearerToken)) {
            $response = $response->withHeader('Authorization', 'Bearer ' . $bearerToken);
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(string $message, int $statusCode): ResponseInterface
    {
        $response = new Response($statusCode);
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
