<?php

declare(strict_types=1);

namespace Authn\Sdk;

final class Config
{
    public const DEFAULT_API_URL = 'https://api.authn.sh';

    public readonly string $apiUrl;

    public function __construct(
        public readonly string $secretKey,
        ?string $apiUrl = null,
    ) {
        if ($secretKey === '') {
            throw new \InvalidArgumentException('authn.sh secret key must not be empty.');
        }

        $this->apiUrl = $apiUrl !== null && $apiUrl !== ''
            ? rtrim($apiUrl, '/')
            : self::DEFAULT_API_URL;
    }

    public function userAgent(): string
    {
        return 'authn-sdk-php/' . Client::VERSION . ' php/' . PHP_VERSION;
    }
}
