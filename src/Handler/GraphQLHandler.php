<?php

namespace Fawaz\Handler;

use Fawaz\GraphQLSchemaBuilder;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Throwable;

class GraphQLHandler implements RequestHandlerInterface
{
    public function __construct(
        protected LoggerInterface $logger,
        protected GraphQLSchemaBuilder $schemaBuilder,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->info("GraphQLHandler.handle started");

        $authorizationHeader = $request->getHeader('Authorization');
        $bearerToken = null;

        if (!empty($authorizationHeader)) {
            $parts = explode(' ', $authorizationHeader[0]);
            if (count($parts) === 2 && strtolower($parts[0]) === 'bearer') {
                $bearerToken = $parts[1];
            }
        }

        $this->schemaBuilder->setCurrentUserId($bearerToken);

        $schema = $this->schemaBuilder->build();

        $context = [
            'request' => $request,
            'bearerToken' => $bearerToken
        ];

        $errorFormatter = function (Throwable $e) use ($request): array {
            $this->logger->error('GraphQL Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'ResponseCode' => 'Input Required to Proceed.',
                'exceptionMessage' => $e->getMessage(),
            ];
        };

        $config = ServerConfig::create()
            ->setSchema($schema)
            ->setContext($context)
            ->setErrorFormatter($errorFormatter);

        $server = new StandardServer($config);
        $response = new Response();
        
        $response = $server->processPsrRequest($request, $response, $response->getBody());

        if (!is_null($bearerToken)) {
            $response = $response->withHeader('Authorization', 'Bearer ' . $bearerToken);
        }

        return $response->withHeader('Content-Type', 'application/json');
    }
}
