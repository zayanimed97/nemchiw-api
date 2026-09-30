<?php

it('hides the client IP check unless it is switched on', function () {
    $this->getJson('/api/v1/_client-ip')->assertNotFound();
});

it('echoes the IP rate limits will use, ignoring a forged X-Forwarded-For', function () {
    config(['shared.expose_client_ip' => true]);

    $this->getJson('/api/v1/_client-ip', ['X-Forwarded-For' => '203.0.113.9'])
        ->assertOk()
        ->assertExactJson(['ip' => '127.0.0.1']);
});
