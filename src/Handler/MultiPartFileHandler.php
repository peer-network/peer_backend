<?php

namespace Fawaz\Handler;

use Fawaz\App\MultiPartFileService;
use Fawaz\GraphQLSchemaBuilder;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Error\FormattedError;
use GraphQL\Validator\Rules\QueryComplexity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

class MultiPartFileHandler implements RequestHandlerInterface
{
    public function __construct(
        protected LoggerInterface $logger,
        protected MultiPartFileService $multipartFileService,
    ) {
    }

    /**
     * Handle Requests
     * 
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->info("PostFileHandler processing request.");

        $authorizationHeader = $request->getHeader('Authorization');
        $bearerToken = null;
        if (!empty($authorizationHeader)) {
            $parts = explode(' ', $authorizationHeader[0]);
            if (count($parts) === 2 && strtolower($parts[0]) === 'bearer') {
                $bearerToken = $parts[1];
            }
        }

        $rawBody = $request->getUploadedFiles();

        if (!is_array($rawBody) || empty($rawBody) || !isset($rawBody['media']) || !is_array($rawBody['media'])) {
            return $this->errorResponse("Invalid Request format. Expected a valid Request.", 400);
        }

        $this->multipartFileService->setCurrentUserId($bearerToken);
        $response = $this->multipartFileService->uploadFile($rawBody['media']);

        $responseBody = [
            'success' => true,
            'data' => $response
        ];
        $response = new Response();
        $response->getBody()->write(json_encode($responseBody));

        $response = $response->withHeader('Content-Type', 'application/json');

        if (!is_null($bearerToken)) {
            $response = $response->withHeader('Authorization', 'Bearer ' . $bearerToken);
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Handle Requests
     * 
     * @return $response ResponseInterface
     */
    private function errorResponse(string $message, int $statusCode): ResponseInterface
    {
        $response = new Response($statusCode);
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
