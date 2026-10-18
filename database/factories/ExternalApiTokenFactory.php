<?php

namespace Database\Factories;

use App\Models\ExternalApiToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalApiToken>
 */
class ExternalApiTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'ventas_usuario',
            'token' => fake()->uuid(),
            'expires_at' => now()->addHour(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }
}
