<?php

declare(strict_types=1);

use Authn\Sdk\Util\Query;

it('encodes scalars and skips nulls/empties', function (): void {
    expect(Query::build(['a' => 1, 'b' => null, 'c' => '', 'd' => 'x']))
        ->toBe('a=1&d=x');
});

it('emits arrays as repeated keys (no brackets)', function (): void {
    expect(Query::build(['ids' => ['u_1', 'u_2', 'u_3']]))
        ->toBe('ids=u_1&ids=u_2&ids=u_3');
});

it('encodes booleans as true/false strings', function (): void {
    expect(Query::build(['active' => true, 'banned' => false]))
        ->toBe('active=true&banned=false');
});

it('rawurlencodes special characters', function (): void {
    expect(Query::build(['email' => 'a+b@c.com']))
        ->toBe('email=a%2Bb%40c.com');
});
