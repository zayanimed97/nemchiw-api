<?php

it('boots', function () {
    $this->get('/up')->assertOk();
});

it('serves no web pages at the root', function () {
    $this->get('/')->assertNotFound()->assertJsonPath('code', 'not_found');
});
