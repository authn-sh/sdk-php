<?php

declare(strict_types=1);

use Authn\Sdk\Webhooks\SignatureInvalidException;
use Authn\Sdk\Webhooks\SignatureVerifier;

function makeWhSecret(string $rawSecret = 'this-is-a-32-byte-random-secret!'): string
{
    return 'whsec_' . base64_encode($rawSecret);
}

function signWebhook(
    string $rawSecret,
    string $messageId,
    int $timestamp,
    string $body,
): string {
    $signedPayload = "{$messageId}.{$timestamp}.{$body}";
    $signature = base64_encode(hash_hmac('sha256', $signedPayload, $rawSecret, true));

    return "v1,{$signature}";
}

/**
 * @return array<string, string>
 */
function webhookHeaders(string $messageId, int $timestamp, string $signature): array
{
    return [
        'svix-id' => $messageId,
        'svix-timestamp' => (string) $timestamp,
        'svix-signature' => $signature,
    ];
}

it('verifies a valid signature and returns a parsed event', function (): void {
    $rawSecret = 'r4ndom-secret-bytes';
    $verifier = new SignatureVerifier(makeWhSecret($rawSecret));

    $now = time();
    $body = json_encode([
        'type' => 'user.created',
        'data' => ['id' => 'user_1', 'email_address' => 'a@b.com'],
        'instance_id' => 'env_acme_test',
        'was_test' => true,
    ], JSON_THROW_ON_ERROR);

    $headers = webhookHeaders('msg_1', $now, signWebhook($rawSecret, 'msg_1', $now, $body));

    $event = $verifier->verify($body, $headers);

    expect($event->type)->toBe('user.created');
    expect($event->data)->toMatchArray(['id' => 'user_1']);
    expect($event->instanceId)->toBe('env_acme_test');
    expect($event->wasTest)->toBeTrue();
    expect($event->messageId)->toBe('msg_1');
    expect($event->timestamp)->toBe($now * 1000);
});

it('rejects a tampered body', function (): void {
    $rawSecret = 'shared';
    $verifier = new SignatureVerifier(makeWhSecret($rawSecret));

    $now = time();
    $body = '{"type":"user.created","data":{"id":"user_1"}}';
    $headers = webhookHeaders('msg_1', $now, signWebhook($rawSecret, 'msg_1', $now, $body));

    $tampered = str_replace('user_1', 'user_2', $body);

    expect(fn () => $verifier->verify($tampered, $headers))->toThrow(SignatureInvalidException::class);
});

it('rejects timestamps outside the tolerance window (replay protection)', function (): void {
    $rawSecret = 'shared';
    $verifier = new SignatureVerifier(makeWhSecret($rawSecret), toleranceSeconds: 60);

    $oldTs = time() - 600;
    $body = '{"type":"x","data":{}}';
    $headers = webhookHeaders('msg_1', $oldTs, signWebhook($rawSecret, 'msg_1', $oldTs, $body));

    expect(fn () => $verifier->verify($body, $headers))->toThrow(SignatureInvalidException::class);
});

it('accepts either secret during a rotation overlap window', function (): void {
    $oldSecret = 'old-secret';
    $newSecret = 'new-secret';
    $verifier = new SignatureVerifier([makeWhSecret($oldSecret), makeWhSecret($newSecret)]);

    $now = time();
    $body = '{"type":"x","data":{}}';

    $signedByOld = signWebhook($oldSecret, 'msg_1', $now, $body);
    $signedByNew = signWebhook($newSecret, 'msg_2', $now, $body);

    expect($verifier->verify($body, webhookHeaders('msg_1', $now, $signedByOld))->messageId)->toBe('msg_1');
    expect($verifier->verify($body, webhookHeaders('msg_2', $now, $signedByNew))->messageId)->toBe('msg_2');
});

it('accepts a multi-version signature header (rotation)', function (): void {
    $oldSecret = 'old-secret';
    $newSecret = 'new-secret';
    $verifier = new SignatureVerifier(makeWhSecret($newSecret));

    $now = time();
    $body = '{"type":"x","data":{}}';
    $oldSig = signWebhook($oldSecret, 'msg_3', $now, $body);
    $newSig = signWebhook($newSecret, 'msg_3', $now, $body);
    $combined = "{$oldSig} {$newSig}";

    expect($verifier->verify($body, webhookHeaders('msg_3', $now, $combined))->type)->toBe('x');
});

it('rejects requests with missing or empty headers', function (): void {
    $verifier = new SignatureVerifier(makeWhSecret('s'));

    expect(fn () => $verifier->verify('{}', []))->toThrow(SignatureInvalidException::class);
    expect(fn () => $verifier->verify('{}', ['svix-id' => 'x', 'svix-timestamp' => '1']))
        ->toThrow(SignatureInvalidException::class);
});

it('rejects non-numeric timestamps', function (): void {
    $verifier = new SignatureVerifier(makeWhSecret('s'));

    expect(fn () => $verifier->verify('{}', webhookHeaders('msg', 0, 'v1,xx') + ['svix-timestamp' => 'not-a-time']))
        ->toThrow(SignatureInvalidException::class);
});

it('rejects empty / non-v1 signatures', function (): void {
    $verifier = new SignatureVerifier(makeWhSecret('s'));

    expect(fn () => $verifier->verify('{}', webhookHeaders('msg', time(), 'v2,abc')))
        ->toThrow(SignatureInvalidException::class);
});

it('tryVerify swallows SignatureInvalidException and returns null', function (): void {
    $verifier = new SignatureVerifier(makeWhSecret('s'));

    expect($verifier->tryVerify('{}', []))->toBeNull();
});

it('reads svix headers case-insensitively and accepts header arrays', function (): void {
    $rawSecret = 'shared';
    $verifier = new SignatureVerifier(makeWhSecret($rawSecret));

    $now = time();
    $body = '{"type":"x","data":{}}';
    $sig = signWebhook($rawSecret, 'msg_1', $now, $body);

    $headers = [
        'Svix-Id' => 'msg_1',
        'Svix-Timestamp' => [(string) $now],
        'Svix-Signature' => $sig,
    ];

    expect($verifier->verify($body, $headers)->messageId)->toBe('msg_1');
});

it('falls back to the raw secret when the input is not whsec-prefixed', function (): void {
    $rawSecret = 'plain-shared-secret';
    $verifier = new SignatureVerifier($rawSecret);

    $now = time();
    $body = '{"type":"x","data":{}}';
    $sig = signWebhook($rawSecret, 'msg_1', $now, $body);

    expect($verifier->verify($body, webhookHeaders('msg_1', $now, $sig))->type)->toBe('x');
});
