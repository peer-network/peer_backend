<?php

declare(strict_types=1);

namespace Fawaz;

class BaseURL implements \Stringable
{
    public function __construct(private string $baseUrl = '')
    {
        // set from super globals if not provided
        if (empty($this->baseUrl)) {
            // Scheme
            $https = $_SERVER['HTTPS'] ?? false;
            $scheme = ($https === false || $https === 'off') ? 'http' : 'https';

            // Authority: Username and pass
            $username = $_SERVER['PHP_AUTH_USER'] ?? '';
            $password = $_SERVER['PHP_AUTH_PW'] ?? '';
            $userInfo = $username . (!empty($password) ? ":$password" : '');

            // Authority: Host
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

            // Authority: Port
            $port = $_SERVER['SERVER_PORT'] ?? ($scheme === 'https' ? 443 : 80);
            if (preg_match('/^(\[[a-fA-F0-9:.]+])(:\d+)?\z/', (string) $host, $matches)) {
                $host = $matches[1];

                if (isset($matches[2])) {
                    $port = (int) substr($matches[2], 1);
                }
            } else {
                $pos = strpos((string) $host, ':');
                if ($pos !== false) {
                    $port = (int) substr((string) $host, $pos + 1);
                    $host = strstr((string) $host, ':', true);
                }
            }
            $authority = ($userInfo !== '' ? $userInfo . '@' : '') . $host . ($port ? ':' . $port : '');

            // Construct the base URL without unnecessary checks
            $this->baseUrl = $scheme . '://' . $authority;
        }
    }

    public function __toString(): string
    {
        return $this->baseUrl;
    }
}
