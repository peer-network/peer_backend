<?php

namespace Fawaz\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Psr\Log\LoggerInterface;

class JWTService
{
    private string $privateKey;
    private string $publicKey;
    private string $refreshPrivateKey;
    private string $refreshPublicKey;
    private int $accessTokenValidity;
    private int $refreshTokenValidity;
    private LoggerInterface $logger;

    public function __construct(
        string $privateKey,
        string $publicKey,
        string $refreshPrivateKey,
        string $refreshPublicKey,
        int $accessTokenValidity,
        int $refreshTokenValidity,
        LoggerInterface $logger
    ) {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
        $this->refreshPrivateKey = $refreshPrivateKey;
        $this->refreshPublicKey = $refreshPublicKey;
        $this->accessTokenValidity = $accessTokenValidity;
        $this->refreshTokenValidity = $refreshTokenValidity;
        $this->logger = $logger;
    }

    public function createAccessToken(array $data): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->accessTokenValidity;
        $payload = array_merge($data, [
            'iat' => $issuedAt,
            'exp' => $expirationTime
        ]);

        $this->logger->info('Creating access token', ['data' => $data]);

        return JWT::encode($payload, $this->privateKey, 'RS256');
    }

    public function createRefreshToken(array $data): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->refreshTokenValidity;
        $payload = array_merge($data, [
            'iat' => $issuedAt,
            'exp' => $expirationTime
        ]);

        $this->logger->info('Creating refresh token.', ['data' => $data]);

        return JWT::encode($payload, $this->refreshPrivateKey, 'RS256');
    }

    public function validateToken(string $token, bool $isRefreshToken = false): ?object
    {
        try {
            $key = $isRefreshToken ? $this->refreshPublicKey : $this->publicKey;
            $decodedToken = JWT::decode($token, new Key($key, 'RS256'));

            if (isset($decodedToken->iss) && $decodedToken->iss !== 'peerapp.de') {
                $this->logger->warning('Invalid token issuer', ['token' => $token]);
                throw new \Exception('Invalid token issuer');
            }

            if (isset($decodedToken->aud) && $decodedToken->aud !== 'peerapp.de') {
                $this->logger->warning('Invalid token audience', ['token' => $token]);
                throw new \Exception('Invalid token audience');
            }

            return $decodedToken;

        } catch (ExpiredException $e) {
            $this->logger->info('Token has expired', ['exception' => $e->getMessage(), 'token' => $token]);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('Token validation failed', ['exception' => $e->getMessage(), 'token' => $token]);
            return null;
        }
    }
}
