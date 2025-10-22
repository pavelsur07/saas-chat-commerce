<?php

declare(strict_types=1);

namespace App\Service\WebChat;

use DateInterval;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class WebChatTokenService
{
    private string $secret;
    private string $issuer;
    private int $defaultTtlSeconds;

    public function __construct(?string $secret = null, string $issuer = 'saas-chat-commerce', int $defaultTtlSeconds = 3600)
    {
        $this->secret = trim($secret ?? ($_ENV['WEBCHAT_JWT_SECRET'] ?? ($_ENV['APP_SECRET'] ?? '')));
        if ($this->secret === '') {
            throw new RuntimeException('WebChat JWT secret is not configured');
        }

        $this->issuer = $issuer !== '' ? $issuer : 'saas-chat-commerce';
        $this->defaultTtlSeconds = max(300, $defaultTtlSeconds);
    }

    public function issue(string $siteKey, string $visitorId, string $threadId, ?int $ttlSeconds = null): WebChatToken
    {
        $now = new DateTimeImmutable();
        $ttl = $ttlSeconds ?? $this->defaultTtlSeconds;
        $expires = $now->add(new DateInterval(sprintf('PT%dS', $ttl)));

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => $this->issuer,
            'aud' => $siteKey,
            'sub' => $visitorId,
            'thread' => $threadId,
            'iat' => $now->getTimestamp(),
            'exp' => $expires->getTimestamp(),
            'jti' => $this->generateJti($siteKey, $threadId, $now),
        ];

        $token = $this->encode($header, $payload);

        return new WebChatToken($token, $payload, $expires);
    }

    public function parse(string $token): WebChatToken
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed token');
        }

        [$header64, $payload64, $signature] = $parts;
        $expectedSignature = $this->sign("{$header64}.{$payload64}");

        if (!hash_equals($expectedSignature, $signature)) {
            throw new RuntimeException('Invalid token signature');
        }

        $header = json_decode($this->base64UrlDecode($header64), true, 512, JSON_THROW_ON_ERROR);
        $payload = json_decode($this->base64UrlDecode($payload64), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
            throw new RuntimeException('Invalid payload');
        }

        $exp = $payload['exp'] ?? null;
        if (!is_numeric($exp)) {
            throw new RuntimeException('Token expiration missing');
        }

        $expiresAt = (new DateTimeImmutable())->setTimestamp((int) $exp);
        if ($expiresAt < new DateTimeImmutable('-1 minute')) {
            throw new RuntimeException('Token expired');
        }

        return new WebChatToken($token, $payload, $expiresAt, $header);
    }

    private function encode(array $header, array $payload): string
    {
        $headerJson = json_encode($header, JSON_THROW_ON_ERROR);
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $header64 = $this->base64UrlEncode($headerJson);
        $payload64 = $this->base64UrlEncode($payloadJson);
        $signature = $this->sign("{$header64}.{$payload64}");

        return sprintf('%s.%s.%s', $header64, $payload64, $signature);
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }

    private function generateJti(string $siteKey, string $threadId, DateTimeImmutable $now): string
    {
        $slugger = new AsciiSlugger();
        $slug = $slugger->slug($siteKey.'-'.$threadId)->lower();

        return sprintf('%s-%d', $slug, $now->getTimestamp());
    }
}
