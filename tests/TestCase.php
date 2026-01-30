<?php

namespace Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use UserDevices\Models\UserDevice;
use Workbench\Database\Factories\UserDeviceFactory;
use Workbench\Database\Seeders\DatabaseSeeder;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;
    use WithWorkbench;

    /**
     * Run a specific seeder before each test.
     */
    protected $seeder = DatabaseSeeder::class;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if ($modelName === UserDevice::class) {
                return UserDeviceFactory::class;
            }

            return 'Workbench\\Database\\Factories\\'.class_basename($modelName).'Factory';
        });
    }
}
