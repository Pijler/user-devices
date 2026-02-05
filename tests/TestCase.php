<?php

namespace Tests;

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Workbench\App\Models\User;
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
     * Login a user.
     */
    public function login(User $user): self
    {
        $this->actingAs($user);

        event(new Login('web', $user, false));

        return $this;
    }
}
