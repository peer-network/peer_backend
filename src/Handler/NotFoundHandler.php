<?php
declare(strict_types=1);

namespace Fawaz\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

class NotFoundHandler
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->info('Route not found: ' . $request->getMethod() . ' ' . $request->getUri());

        $response = new Response(404);
        $response->getBody()->write(json_encode(['error' => 'Not Found.'], JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
