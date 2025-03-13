<?php

namespace Fawaz\Handler;

use Fawaz\GraphQLSchemaBuilder;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\DocumentValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

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

        $PeerFormatter = function ($error) {
            return [
                'message' => $error->getMessage(),
                'locations' => $error->getLocations(),
                'path' => $error->getPath(),
            ];
        };

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

        //$rule = new QueryComplexity(100);
        //DocumentValidator::addRule($rule);

        $context = [
            'request' => $request,
            'bearerToken' => $bearerToken
        ];

        $config = ServerConfig::create()
            ->setSchema($schema)
            ->setContext($context)
            ->setErrorFormatter($PeerFormatter)
            ->setQueryBatching(true)
            ->setDebugFlag(true);

        $server = new StandardServer($config);
        $response = new Response();
        
        $response = $server->processPsrRequest($request, $response, $response->getBody());

        if (!is_null($bearerToken)) {
            $response = $response->withHeader('Authorization', 'Bearer ' . $bearerToken);
        }

        return $response->withHeader('Content-Type', 'application/json', 'charset=UTF-8');
    }
}
