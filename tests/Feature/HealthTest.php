<?php

it('boots', function () {
    $this->get('/up')->assertOk();
});
