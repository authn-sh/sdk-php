<?php

declare(strict_types=1);

use Authn\Sdk\Resources\EnterpriseAccount;

it('parses an EnterpriseAccount payload', function (): void {
    $account = EnterpriseAccount::fromPayload([
        'id' => 'entacc_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        'object' => 'enterprise_account',
        'enterprise_connection_id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        'provider_user_id' => 'alice@acme.example',
        'email_address' => 'alice@acme.example',
        'verified' => true,
        'public_metadata' => [
            'groups' => ['engineering', 'sso-admins'],
            'department' => 'Platform',
        ],
        'linked_at' => 1_714_723_000_000,
        'last_signed_in_at' => 1_714_896_500_000,
        'created_at' => 1_714_723_000_000,
        'updated_at' => 1_714_896_500_000,
    ]);

    expect($account->id)->toBe('entacc_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($account->enterpriseConnectionId)->toBe('entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($account->providerUserId)->toBe('alice@acme.example');
    expect($account->emailAddress)->toBe('alice@acme.example');
    expect($account->verified)->toBeTrue();
    expect($account->publicMetadata)->toHaveKey('groups');
    expect($account->publicMetadata['department'])->toBe('Platform');
    expect($account->lastSignedInAt)->toBe(1_714_896_500_000);
});

it('handles null last_signed_in_at on first link', function (): void {
    $account = EnterpriseAccount::fromPayload([
        'id' => 'entacc_01HKX9SY9V7H7TF8C8K7J9X4ZB',
        'object' => 'enterprise_account',
        'enterprise_connection_id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZC',
        'provider_user_id' => '00uabcdEFGHIJKLM2x7',
        'email_address' => 'bob@acme.example',
        'verified' => true,
        'public_metadata' => [],
        'linked_at' => 1_714_724_000_000,
        'last_signed_in_at' => null,
        'created_at' => 1_714_724_000_000,
        'updated_at' => 1_714_724_000_000,
    ]);

    expect($account->lastSignedInAt)->toBeNull();
});

it('handles null email_address when the IdP omitted it', function (): void {
    $account = EnterpriseAccount::fromPayload([
        'id' => 'entacc_x',
        'enterprise_connection_id' => 'entcon_x',
        'provider_user_id' => 'subj',
        'email_address' => null,
        'verified' => false,
        'public_metadata' => [],
        'linked_at' => 1_700_000_000_000,
        'last_signed_in_at' => null,
        'created_at' => 1_700_000_000_000,
        'updated_at' => 1_700_000_000_000,
    ]);

    expect($account->emailAddress)->toBeNull();
    expect($account->verified)->toBeFalse();
});
