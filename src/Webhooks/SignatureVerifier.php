<?php

declare(strict_types=1);

namespace Authn\Sdk\Webhooks;

use Authn\Sdk\Util\Json;

/**
 * Verifies authn.sh webhook signatures (svix-compatible scheme).
 *
 * The transport signs `{svix-id}.{svix-timestamp}.{rawBody}` with HMAC-SHA256
 * keyed by the `whsec_…` signing secret. Multiple secrets can be supplied to
 * tolerate key rotation: a request is accepted if any provided signature
 * matches any configured secret.
 */
final class SignatureVerifier
{
    /** @var list<string> */
    private readonly array $signingSecrets;

    /**
     * @param  string|list<string>  $signingSecret  one or more `whsec_…` secrets (rotation overlap)
     */
    public function __construct(
        string|array $signingSecret,
        private readonly int $toleranceSeconds = 300,
    ) {
        $this->signingSecrets = is_array($signingSecret)
            ? array_values(array_filter($signingSecret, static fn ($s): bool => $s !== ''))
            : [$signingSecret];

        if ($this->signingSecrets === []) {
            throw new \InvalidArgumentException('At least one signing secret must be provided.');
        }
    }

    /**
     * @param  array<string, string|list<string>>  $headers  case-insensitive
     *
     * @throws SignatureInvalidException
     */
    public function verify(string $rawBody, array $headers): WebhookEvent
    {
        $id = $this->headerLine($headers, 'svix-id');
        $timestamp = $this->headerLine($headers, 'svix-timestamp');
        $signature = $this->headerLine($headers, 'svix-signature');

        if ($id === null || $timestamp === null || $signature === null) {
            throw new SignatureInvalidException('Missing svix-id, svix-timestamp, or svix-signature header.');
        }

        if (! ctype_digit($timestamp)) {
            throw new SignatureInvalidException('svix-timestamp must be a unix-second integer.');
        }
        $tsInt = (int) $timestamp;

        if (abs(time() - $tsInt) > $this->toleranceSeconds) {
            throw new SignatureInvalidException('Webhook timestamp is outside the tolerance window.');
        }

        $signedPayload = "{$id}.{$timestamp}.{$rawBody}";
        $providedSignatures = $this->extractV1Signatures($signature);

        if ($providedSignatures === []) {
            throw new SignatureInvalidException('No v1 signatures found in svix-signature header.');
        }

        if (! $this->matchesAny($signedPayload, $providedSignatures)) {
            throw new SignatureInvalidException('Webhook signature does not match.');
        }

        $payload = Json::decode($rawBody);
        if ($payload === []) {
            throw new SignatureInvalidException('Webhook body is not a JSON object.');
        }

        $type = isset($payload['type']) && is_string($payload['type']) ? $payload['type'] : '';
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $instanceId = isset($payload['instance_id']) && is_string($payload['instance_id']) ? $payload['instance_id'] : '';
        $wasTest = isset($payload['was_test']) && $payload['was_test'] === true;

        /** @var array<string, mixed> $data */
        return new WebhookEvent(
            type: $type,
            data: $data,
            timestamp: $tsInt * 1000,
            instanceId: $instanceId,
            wasTest: $wasTest,
            messageId: $id,
        );
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    public function tryVerify(string $rawBody, array $headers): ?WebhookEvent
    {
        try {
            return $this->verify($rawBody, $headers);
        } catch (SignatureInvalidException) {
            return null;
        }
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    private function headerLine(array $headers, string $name): ?string
    {
        foreach ($headers as $header => $value) {
            if (strcasecmp($header, $name) !== 0) {
                continue;
            }
            if (is_array($value)) {
                $value = $value === [] ? null : (string) $value[0];
            }

            return $value === null || $value === '' ? null : (string) $value;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractV1Signatures(string $header): array
    {
        $out = [];
        foreach (preg_split('/\s+/', trim($header)) ?: [] as $entry) {
            if ($entry === '' || ! str_starts_with($entry, 'v1,')) {
                continue;
            }
            $sig = substr($entry, 3);
            if ($sig !== '') {
                $out[] = $sig;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $providedBase64
     */
    private function matchesAny(string $signedPayload, array $providedBase64): bool
    {
        foreach ($this->signingSecrets as $secret) {
            $rawSecret = $this->decodeSecret($secret);
            $expected = base64_encode(hash_hmac('sha256', $signedPayload, $rawSecret, true));
            foreach ($providedBase64 as $provided) {
                if (hash_equals($expected, $provided)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function decodeSecret(string $secret): string
    {
        if (str_starts_with($secret, 'whsec_')) {
            $b64 = substr($secret, strlen('whsec_'));
            $decoded = base64_decode($b64, true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $secret;
    }
}
