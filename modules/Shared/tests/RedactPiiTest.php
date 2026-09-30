<?php

use Modules\Shared\Logging\RedactPii;
use Modules\Shared\Logging\RedactPiiTap;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

it('scrubs phones, tokens and secret keys from logs', function () {
    $handler = new TestHandler;
    $logger = new Logger('test', [$handler], [new RedactPii]);

    $logger->info('sent to +21620123456 with Bearer 12|nemchiw_abcDEF123', [
        'code' => '123456',
        'nested' => ['token' => 'secret', 'note' => 'call +21698765432'],
        'count' => 3,
    ]);

    $record = $handler->getRecords()[0];
    expect($record->message)->not->toContain('20123456')->not->toContain('nemchiw_abc');
    expect($record->context['code'])->toBe('[redacted]');
    expect($record->context['nested']['token'])->toBe('[redacted]');
    expect($record->context['nested']['note'])->not->toContain('98765432');
    expect($record->context['count'])->toBe(3);
});

it('is tapped onto the log channels', function () {
    foreach (['stack', 'single', 'daily'] as $channel) {
        expect(config("logging.channels.$channel.tap"))->toContain(RedactPiiTap::class);
    }
});
