<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use UserDevices\Models\UserDevice;
use Workbench\App\Models\User;

class UserDeviceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = UserDevice::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'last_activity' => Carbon::now()->timestamp,
            'blocked' => false,
        ];
    }

    /**
     * Indicate that the device is blocked.
     */
    public function blocked(): static
    {
        return $this->state(fn () => ['blocked' => true]);
    }
}
