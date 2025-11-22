<?php

namespace NodusIT\LaravelMailSync\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NodusIT\LaravelMailSync\Models\MailAccount;

class MailAccountFactory extends Factory
{
    protected $model = MailAccount::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Mail',
            'email_address' => $this->faker->safeEmail(),
            'host' => $this->faker->domainName(),
            'port' => $this->faker->randomElement([993, 995, 143, 110]),
            'encryption' => $this->faker->randomElement(['ssl', 'tls', null]),
            'username' => $this->faker->userName(),
            'password' => $this->faker->password(),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'last_synced_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 month', 'now'),
            'last_connection_error' => $this->faker->optional(0.2)->sentence(),
            'last_connection_failed' => $this->faker->boolean(20), // 20% chance of connection failure
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'last_connection_failed' => false,
            'last_connection_error' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withConnectionError(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_connection_failed' => true,
            'last_connection_error' => $this->faker->sentence(),
        ]);
    }
}
