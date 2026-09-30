<?php

use Modules\Shared\Providers\ModuleServiceProvider;

beforeEach(function () {
    $this->app->register(new class($this->app) extends ModuleServiceProvider
    {
        protected function module(): string
        {
            return 'Shared/tests/Fixtures/Demo';
        }
    });
});

it('mounts module routes under /api/v1', function () {
    $this->getJson('/api/v1/demo-ping')->assertOk()->assertExactJson(['ok' => true]);
});

it('merges module config under the snake-cased module name', function () {
    expect(config('shared/tests/_fixtures/_demo.answer'))->toBe(42);
});
