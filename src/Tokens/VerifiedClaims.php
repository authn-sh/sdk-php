<?php

declare(strict_types=1);

namespace Authn\Sdk\Tokens;

final class VerifiedClaims
{
    /**
     * @param  array{iss: string, sub: string, sid: string}|null  $actor  impersonation chain
     * @param  array{id: string, slg?: string, rol?: string, per?: array<int, string>}|null  $org  deprecated: use $organization
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $sub,
        public readonly string $sid,
        public readonly string $iss,
        public readonly ?string $azp,
        public readonly int $exp,
        public readonly int $iat,
        public readonly ?int $nbf,
        public readonly ?array $actor,
        public readonly ?array $org,
        public readonly bool $wasTest,
        public readonly array $raw,
        public readonly ?Organization $organization = null,
        public readonly bool $twoFactorVerified = false,
        public readonly ?int $secondFactorAgeSeconds = null,
        public readonly ?int $firstFactorAgeSeconds = null,
    ) {}

    public function hasRole(string $key): bool
    {
        return $this->organization !== null && $this->organization->hasRole($key);
    }

    public function hasPermission(string $key): bool
    {
        return $this->organization !== null && $this->organization->hasPermission($key);
    }
}
