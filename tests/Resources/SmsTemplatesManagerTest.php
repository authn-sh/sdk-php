<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Resources\SmsTemplate;
use Authn\Sdk\Resources\SmsTemplatesManager;
use Authn\Sdk\Tests\Support\MockTransport;

/**
 * @return array<string, mixed>
 */
function smsTemplatePayload(string $slug = SmsTemplate::SLUG_VERIFICATION_CODE): array
{
    return [
        'id' => 'tmpl_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        'object' => 'sms_template',
        'slug' => $slug,
        'body' => 'Your {{app.name}} code is {{otp_code}}.',
        'delivered_by_us' => true,
        'from_number_override' => null,
        'created_at' => 1_700_000_000_000,
        'updated_at' => 1_700_000_001_000,
    ];
}

it('lists sms templates as an array of SmsTemplate', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        smsTemplatePayload(SmsTemplate::SLUG_VERIFICATION_CODE),
        smsTemplatePayload(SmsTemplate::SLUG_RESET_PASSWORD_CODE),
        smsTemplatePayload(SmsTemplate::SLUG_INVITATION),
    ]);
    $templates = new SmsTemplatesManager($mock->transport());

    $list = $templates->list();

    expect($list)->toHaveCount(3);
    expect($list[0])->toBeInstanceOf(SmsTemplate::class);
    expect($list[0]->slug)->toBe(SmsTemplate::SLUG_VERIFICATION_CODE);
    expect((string) $mock->lastRequest()->getUri())->toEndWith('/v1/sms-templates');
});

it('gets a single sms template by slug', function (): void {
    $mock = (new MockTransport)->enqueue(body: smsTemplatePayload());
    $templates = new SmsTemplatesManager($mock->transport());

    $template = $templates->get(SmsTemplate::SLUG_VERIFICATION_CODE);

    expect($template->slug)->toBe(SmsTemplate::SLUG_VERIFICATION_CODE);
    expect($template->body)->toContain('{{otp_code}}');
    expect($template->deliveredByUs)->toBeTrue();
    expect($template->fromNumberOverride)->toBeNull();
    expect((string) $mock->lastRequest()->getUri())->toEndWith('/v1/sms-templates/verification_code');
});

it('updates sms template body and surfaces typed result', function (): void {
    $updated = ['body' => 'New copy {{otp_code}}'] + smsTemplatePayload();
    $mock = (new MockTransport)->enqueue(body: $updated);
    $templates = new SmsTemplatesManager($mock->transport());

    $result = $templates->update(SmsTemplate::SLUG_VERIFICATION_CODE, [
        'body' => 'New copy {{otp_code}}',
    ], idempotencyKey: 'idem-1');

    expect($result->body)->toBe('New copy {{otp_code}}');
    expect($mock->lastRequest()->getMethod())->toBe('PATCH');
    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('idem-1');
    expect((string) $mock->lastRequest()->getBody())->toBe('{"body":"New copy {{otp_code}}"}');
});

it('reverts sms template via POST to /revert', function (): void {
    $mock = (new MockTransport)->enqueue(body: smsTemplatePayload());
    $templates = new SmsTemplatesManager($mock->transport());

    $result = $templates->revert(SmsTemplate::SLUG_VERIFICATION_CODE);

    expect($result)->toBeInstanceOf(SmsTemplate::class);
    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/sms-templates/verification_code/revert');
});

it('flips delivered_by_us to webhook handoff', function (): void {
    $payload = ['delivered_by_us' => false] + smsTemplatePayload();
    $mock = (new MockTransport)->enqueue(body: $payload);
    $templates = new SmsTemplatesManager($mock->transport());

    $result = $templates->update(SmsTemplate::SLUG_INVITATION, ['delivered_by_us' => false]);

    expect($result->deliveredByUs)->toBeFalse();
});

it('unknown slug surfaces as ApiException', function (): void {
    $mock = (new MockTransport)->enqueue(404, [
        'errors' => [['code' => 'sms_template_not_found', 'message' => 'no such slug', 'long_message' => '...']],
    ]);
    $templates = new SmsTemplatesManager($mock->transport());

    expect(fn () => $templates->get('does_not_exist'))->toThrow(ApiException::class);
});

it('Client::smsTemplates() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: []);
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->smsTemplates())->toBeInstanceOf(SmsTemplatesManager::class);
});
