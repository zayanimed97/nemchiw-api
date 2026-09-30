<?php

use Carbon\CarbonImmutable;
use Modules\Shared\Support\Governorates;
use Modules\Shared\Support\Iso;
use Modules\Shared\Support\TunisianPhone;

it('formats instants as UTC ISO with milliseconds', function () {
    $at = CarbonImmutable::parse('2026-09-29 01:02:03.456789', 'Africa/Tunis');
    expect(Iso::format($at))->toBe('2026-09-29T00:02:03.456Z');
});

it('accepts only +216 mobile-style numbers', function (string $phone, bool $valid) {
    expect(TunisianPhone::isValid($phone))->toBe($valid);
})->with([
    ['+21620123456', true],
    ['+21699999999', true],
    ['+21610123456', false],
    ['+2162012345', false],
    ['+216201234567', false],
    ['21620123456', false],
    ['+216 20 123 456', false],
    ['+33612345678', false],
]);

it('lists the 24 governorates', function () {
    expect(Governorates::ALL)->toHaveCount(24)->toContain('tunis', 'sidiBouzid', 'benArous');
});
