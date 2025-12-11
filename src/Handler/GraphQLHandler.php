<?php

declare(strict_types=1);

namespace Fawaz\Handler;

use Fawaz\GraphQLSchemaBuilder;
use Fawaz\Utils\PeerLoggerInterface;
use GraphQL\Error\FormattedError;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

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
        $body    = trim($rawBody);

        if ('' === $body || 'null' === $body) {
            // $this->logger->error("GraphQL request body is empty, null, spaces.");
            return $this->errorResponse('Empty or invalid request body. Expected JSON.', 400);
        }

        $decodedBody = json_decode($body, true);

        if (\JSON_ERROR_NONE !== json_last_error() || !\is_array($decodedBody)) {
            // $this->logger->error("Invalid JSON format: " . json_last_error_msg());
            return $this->errorResponse('Invalid JSON format. Expected a valid JSON object.', 400);
        }

        if (!isset($decodedBody['query']) || '' === trim($decodedBody['query'])) {
            // $this->logger->error("GraphQL query is missing or contains only whitespace.");
            return $this->errorResponse('Invalid GraphQL query. Expected a valid query string.', 400);
        }

        $this->logger->debug('GraphQLHandler processing request.');

        // $this->logger->info("Received raw body: " . json_encode($rawBody));
        $authorizationHeader = $request->getHeader('Authorization');
        $bearerToken         = null;

        if (!empty($authorizationHeader)) {
            $parts = explode(' ', $authorizationHeader[0]);

            if (2 === \count($parts) && 'bearer' === strtolower($parts[0])) {
                $bearerToken = $parts[1];
            }
        }

        if (false === $this->schemaBuilder->setCurrentUserId($bearerToken)) {
            return $this->errorResponse(
                'Invalid Access Token',
                401
            );
        }
        $schema = $this->schemaBuilder->build();

        $context = [
            'request'     => $request,
            'bearerToken' => $bearerToken,
        ];

        $config = ServerConfig::create()
            ->setSchema($schema)
            ->setContext($context)
            ->setErrorFormatter(fn ($error) => FormattedError::createFromException($error))
            ->setQueryBatching(true)
            ->setDebugFlag();

        $server   = new StandardServer($config);
        $response = new Response();
        $response = $server->processPsrRequest($request, $response, $response->getBody());

        if (null !== $bearerToken) {
            $response = $response->withHeader('Authorization', 'Bearer '.$bearerToken);
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
