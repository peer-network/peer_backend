<?php

declare(strict_types=1);

use Fawaz\Handler\GraphQLHandler;
use Fawaz\Handler\NotFoundHandler;
use Fawaz\Handler\MultipartPostHandler;
use Slim\App;

return static function (App $app) {
    $app->add(function ($request, $handler) {
        $response = $handler->handle($request);
        return $response
            // Security Headers
            ->withHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'; object-src 'none';")
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('Permissions-Policy', "geolocation=(), microphone=(), camera=()")

            // CORS & Cache Headers
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache')
            ->withStatus(200);
    });

    // Routes
    $app->post('/graphql', GraphQLHandler::class);
    $app->post('/upload-post', MultipartPostHandler::class);
    $app->any('/{routes:.*}', NotFoundHandler::class);
};
