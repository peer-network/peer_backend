<?php

namespace Fawaz\Handler;

use Fawaz\App\MultipartPostService;
use Fawaz\App\PostService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

class MultipartPostHandler implements RequestHandlerInterface
{
    public function __construct(
        protected LoggerInterface $logger,
        protected MultipartPostService $multipartPostService
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
        $contentType = $request->getHeader('Content-Type');
        $contentLength = $request->getHeader('Content-Length');

        $responseBody = $this->multipartPostService->checkForBasicValidation(['contentType' => $contentType, 'contentLength' => $contentLength]);

        $bearerToken = null;
        if(isset($responseBody['status']) && $responseBody['status'] != 'error'){
            if (!empty($authorizationHeader)) {
                $parts = explode(' ', $authorizationHeader[0]);
                if (count($parts) === 2 && strtolower($parts[0]) === 'bearer') {
                    $bearerToken = $parts[1];
                }
            }

            $mediaFiles = $request->getUploadedFiles();
            $rawBody = $request->getParsedBody();

            $requestObj = [
                'eligibilityToken' => isset($rawBody['eligibilityToken']) ? $rawBody['eligibilityToken'] : '',
                'media' => isset($mediaFiles['media']) && is_array($mediaFiles['media']) ? $mediaFiles['media'] : [],
            ];
            $this->multipartPostService->setCurrentUserId($bearerToken);

            $responseBody = $this->multipartPostService->handleFileUpload($requestObj);
        }
        

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
