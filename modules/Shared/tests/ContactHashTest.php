<?php

use Modules\Shared\Support\ContactHash;
use Modules\Shared\Support\TunisianPhone;

it('normalises Tunisian numbers', function () {
    expect(TunisianPhone::normalise('98 765 432'))->toBe('+21698765432')
        ->and(TunisianPhone::normalise('0021698765432'))->toBe('+21698765432')
        ->and(TunisianPhone::normalise('+216-98.765 432'))->toBe('+21698765432')
        ->and(TunisianPhone::normalise('21698765432'))->toBe('+21698765432')
        ->and(TunisianPhone::normalise('12345678'))->toBeNull()
        ->and(TunisianPhone::normalise('abc'))->toBeNull();
});

it('normalises contacts to a phone or a lower-case email', function () {
    expect(ContactHash::normalise('98 765 432'))->toBe('+21698765432')
        ->and(ContactHash::normalise('  Anas@Gmail.com '))->toBe('anas@gmail.com')
        ->and(ContactHash::normalise('nonsense'))->toBeNull();
});

it('masks contacts', function () {
    expect(ContactHash::mask('+21698765432'))->toBe('+216 98 ··· 432')
        ->and(ContactHash::mask('anas.b@gmail.com'))->toBe('a···@gmail.com');
});

it('hashes with the contact key', function () {
    $hash = ContactHash::of('+21698765432');
    expect($hash)->toBe(hash_hmac('sha256', '+21698765432', 'test-key'))->toHaveLength(64);

    config(['parties.contact_key' => 'other']);
    expect(ContactHash::of('+21698765432'))->not->toBe($hash);
});
