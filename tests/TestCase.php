<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flushHeaders();

        putenv('VITE_API_KEY=testing-key');
        $_ENV['VITE_API_KEY'] = 'testing-key';
        $_SERVER['VITE_API_KEY'] = 'testing-key';
        config(['installer.buildPayload.license_code' => 'testing-key']);

        if (!file_exists(storage_path('installed'))) {
            touch(storage_path('installed'));
        }
    }
}
