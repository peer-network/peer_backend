<?php

namespace Fawaz\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Stream;

class HtmlentityDecoderMiddleware implements MiddlewareInterface
{
    /**
     * Recursively decode HTML entities columns
     * 
     * Add more columns here for decode html entity
     */
    protected const DECODE_ENTITY_COLUMNS = [
        'title', 
        'mediadescription', 
        'content', 
        'comments', 
        'message', 
        'name'
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Get response body content as a string
        $body = (string) $response->getBody();

        // Decode JSON
        $data = json_decode($body, true);

        // If valid JSON, decode HTML entities in all strings
        if ($data !== null) {
            $data = $this->decodeHtmlEntities($data);

            $decodedBody = json_encode($data);

            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $decodedBody);
            rewind($stream);

            return $response->withBody(new Stream($stream));
        }

        return $response;
    }

    /**
     * Recursively decode HTML entities in all string values
     */
    private function decodeHtmlEntities($data, $key = '')
    {
        if (is_array($data)) {
            foreach ($data as $key => &$value) {
                $value = $this->decodeHtmlEntities($value, $key);
            }
        } elseif (is_string($data)) {
            if(in_array($key, static::DECODE_ENTITY_COLUMNS)){
                $data = html_entity_decode(htmlspecialchars_decode($data), ENT_QUOTES, 'UTF-8');
            }
        }

        return $data;
    }
}
