<?php

declare(strict_types=1);

namespace Fawaz\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class HealthHandler
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response(200);
        $response->getBody()->write(json_encode(['success' => 'Health check is successfull.']));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
