<?php

use Modules\Trips\Support\JoinCode;

const RFC_SECRET = '12345678901234567890123456789012';

it('matches the RFC 6238 SHA-256 vectors (last six digits)', function (int $time, string $code) {
    expect(JoinCode::at(RFC_SECRET, JoinCode::counter($time)))->toBe($code);
})->with([
    [59, '119246'], [1111111109, '084774'], [1111111111, '062674'], [1234567890, '819424'], [2000000000, '698825'],
]);

it('accepts the current window and one either side', function () {
    $now = 1_760_000_000;
    $code = fn (int $offset) => JoinCode::at(RFC_SECRET, JoinCode::counter($now) + $offset);

    expect(JoinCode::check(RFC_SECRET, $code(0), $now))->toBe('ok');
    expect(JoinCode::check(RFC_SECRET, $code(-1), $now))->toBe('ok');
    expect(JoinCode::check(RFC_SECRET, $code(1), $now))->toBe('ok');
    expect(JoinCode::check(RFC_SECRET, $code(-5), $now))->toBe('expired');
    expect(JoinCode::check(RFC_SECRET, '12345', $now))->toBe('invalid');
    expect(JoinCode::check(RFC_SECRET, 'abcdef', $now))->toBe('invalid');
});
