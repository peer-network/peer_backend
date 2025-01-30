<?php

namespace Fawaz\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Fawaz\RateLimiter\RateLimiter;
use Psr\Log\LoggerInterface;

class RateLimiterMiddleware implements MiddlewareInterface
{
    private RateLimiter $rateLimiter;
    private LoggerInterface $logger;

    public function __construct(RateLimiter $rateLimiter, LoggerInterface $logger)
    {
        $this->rateLimiter = $rateLimiter;
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ipAddress = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';

        $this->logger->info("RateLimiterMiddleware: IP $ipAddress");

        if (!$this->rateLimiter->isAllowed($ipAddress)) {
            $this->logger->info("Rate limit exceeded for IP: $ipAddress");

            $response = new Response();
            $response->getBody()->write(json_encode(['errors' => 'Rate limit exceeded']));
            return $response->withStatus(429)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
