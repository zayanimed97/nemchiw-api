<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['parties.contact_key' => 'test-key']);
    }

    /**
     * A request with a bearer token authenticates afresh, as a real one does. Without
     * this the sanctum guard keeps a test's first user for every later request.
     * actingAs() requests (no token) keep their user.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (isset($this->defaultHeaders['Authorization'])) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
