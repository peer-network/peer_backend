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
        $ipAddress = filter_var($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown', FILTER_VALIDATE_IP);

        if (!$ipAddress) {
            $this->logger->error('Invalid IP address detected');
            $ipAddress = 'unknown';
        }

        $this->logger->info("RateLimiterMiddleware: IP $ipAddress");

        if (!$this->rateLimiter->isAllowed($ipAddress)) {
            $this->logger->warning("Rate limit exceeded for IP: $ipAddress");

            $response = new Response();
            $response->getBody()->write(json_encode([
                'errors' => 'Rate limit exceeded',
                'message' => 'You have exceeded the allowed number of requests.',
                'retry_after' => 60 
            ]));
            return $response
                ->withStatus(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('X-RateLimit-Limit', (string) $this->rateLimiter->getLimit())
                ->withHeader('X-RateLimit-Remaining', (string) 0)
                ->withHeader('X-RateLimit-Reset', (string) (time() + 60));
        }

        return $handler->handle($request);
    }
}
