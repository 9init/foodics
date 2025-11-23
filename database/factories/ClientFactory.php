<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'account_number' => 'SA' . fake()->numerify('################'),
            'bank_code' => fake()->randomElement(['FDCSSARI', 'RIBLSARI', 'NCBKSAJE']),
            'country_code' => fake()->randomElement(['SA', 'AE', 'KW', 'BH']),
            'is_active' => true,
        ];
    }
}
