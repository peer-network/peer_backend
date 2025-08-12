<?php

namespace Fawaz\Handler;

use Fawaz\App\MultipartPostService;
use Fawaz\App\PostService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Psr7\UploadedFile;

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

            $rawBody = $request->getParsedBody();
            $rawFiles = $_FILES;

            $filesArray = [];
            if (isset($rawFiles['file'])) {
                $filesArray = $this->normalizeFilesArray($rawFiles['file']);
            }
            
            $requestObj = [
                'eligibilityToken' => isset($rawBody['eligibilityToken']) ? $rawBody['eligibilityToken'] : '',
                'media' => is_array($filesArray) && !empty($filesArray) ? $filesArray : [],
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
     * Normalize PHP's $_FILES array into a per-file format
     */
    function normalizeFilesArray(array $files): array
    {
        try{

            $normalized = [];

            if (is_array($files['name']) && isset($files['name'][0]) && empty($files['name'][0])) {
                return [];
            }

            if (is_array($files['name']) && isset($files['name'][0]) && !empty($files['name'][0])) {
                foreach ($files['name'] as $index => $name) {
                    $normalized[] = [
                        'name'     => $name,
                        'type'     => $files['type'][$index],
                        'tmp_name' => $files['tmp_name'][$index],
                        'error'    => $files['error'][$index],
                        'size'     => $files['size'][$index],
                    ];
                }
            } else {
                // Single file
                $normalized[] = $files;
            }

            $uploadedFilesObj = [];
            foreach ($normalized as $index => $fileObj) {
                $uploadedFilesObj[] = new UploadedFile(
                    $fileObj['tmp_name'],
                    $fileObj['name'],
                    $fileObj['type'],
                    $fileObj['size'],
                    $fileObj['error']
                );
            }

            return $uploadedFilesObj;
        }
        catch (\Exception $e) {
            $this->logger->error("Error normalizing files array: " . $e->getMessage());
            return [];
        }
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
