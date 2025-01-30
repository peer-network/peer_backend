<?php

declare(strict_types=1);

use Fawaz\Handler\GraphQLHandler;
use Fawaz\Handler\NotFoundHandler;
use Slim\App;

return static function (App $app) {
	$app->add(function ($request, $handler) {
		$response = $handler->handle($request);
		return $response
			->withHeader('Access-Control-Allow-Credentials', 'true')
			->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization')
			->withHeader('Access-Control-Allow-Methods', 'POST')
			->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
			->withHeader('Pragma', 'no-cache');
	});
    $app->any('/graphql', GraphQLHandler::class);
    $app->any('/{routes:.*}', NotFoundHandler::class);
};
