<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Http\Transport;

abstract class Manager
{
    public function __construct(protected readonly Transport $transport) {}
}
