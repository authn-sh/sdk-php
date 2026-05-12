<?php

declare(strict_types=1);

namespace Authn\Sdk\Tokens;

final class VerifiedClaims
{
    public const SECOND_FACTOR_TOTP = 'totp';

    public const SECOND_FACTOR_PHONE_CODE = 'phone_code';

    public const SECOND_FACTOR_BACKUP_CODE = 'backup_code';

    /**
     * Reserved JWT registered claims plus the authn.sh-emitted claim names.
     * Anything not in this set is surfaced through {@see VerifiedClaims::$customClaims}.
     */
    public const RESERVED_CLAIMS = [
        'iss', 'sub', 'aud', 'exp', 'nbf', 'iat', 'jti', 'nonce',
        'sid', 'azp', 'act', 'org', 'was_test',
        'fva', 'pnv', 'dsf', 'pkv', 'pkc', 'entcon', 'entacc',
    ];

    /**
     * @param  array{iss: string, sub: string, sid: string}|null  $actor  impersonation chain
     * @param  array{id: string, slg?: string, rol?: string, per?: array<int, string>}|null  $org  deprecated: use $organization
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $customClaims  non-reserved claims rendered by a JwtTemplate
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
        public readonly bool $phoneNumberVerified = false,
        public readonly ?string $defaultSecondFactor = null,
        public readonly bool $passkeyVerified = false,
        public readonly int $passkeyCount = 0,
        public readonly ?string $enterpriseConnectionId = null,
        public readonly ?string $enterpriseAccountId = null,
        public readonly array $customClaims = [],
    ) {}

    public function customClaim(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->customClaims) ? $this->customClaims[$key] : $default;
    }

    public function hasRole(string $key): bool
    {
        return $this->organization !== null && $this->organization->hasRole($key);
    }

    public function hasPermission(string $key): bool
    {
        return $this->organization !== null && $this->organization->hasPermission($key);
    }

    public function hasVerifiedPhoneNumber(): bool
    {
        return $this->phoneNumberVerified;
    }

    public function preferredSecondFactor(): ?string
    {
        return $this->defaultSecondFactor;
    }

    public function wasVerifiedByPasskey(): bool
    {
        return $this->passkeyVerified;
    }

    public function hasPasskey(): bool
    {
        return $this->passkeyCount > 0;
    }

    public function wasVerifiedByEnterpriseSso(): bool
    {
        return $this->enterpriseConnectionId !== null;
    }
}
