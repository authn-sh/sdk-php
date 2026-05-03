<?php

declare(strict_types=1);

namespace Authn\Sdk\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class ThrowingClient implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        throw new class('connect: connection refused') extends RuntimeException implements ClientExceptionInterface {};
    }
}
