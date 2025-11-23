<?php

namespace Database\Factories;

use App\Services\Webhook\Parsers\FoodicsBankParser;
use App\Services\Webhook\Parsers\AcmeBankParser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Acquirer>
 */
class AcquirerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $identifiers = ['foodics_bank', 'acme_bank', 'stripe', 'paypal'];
        $identifier = fake()->randomElement($identifiers);

        return [
            'name' => fake()->company() . ' Bank',
            'identifier' => $identifier,
            'parser_class' => $identifier === 'foodics_bank' ? FoodicsBankParser::class : AcmeBankParser::class,
            'country_code' => fake()->countryCode(),
            'currency' => fake()->currencyCode(),
            'webhook_endpoint' => '/api/webhooks/' . $identifier,
            'is_active' => true,
            'metadata' => [
                'description' => fake()->sentence(),
            ],
        ];
    }

    public function foodicsBank(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Foodics Bank',
            'identifier' => 'foodics_bank',
            'parser_class' => FoodicsBankParser::class,
            'country_code' => 'SA',
            'currency' => 'SAR',
            'webhook_endpoint' => '/api/webhooks/foodics-bank',
        ]);
    }

    public function acmeBank(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Acme Bank',
            'identifier' => 'acme_bank',
            'parser_class' => AcmeBankParser::class,
            'country_code' => 'US',
            'currency' => 'USD',
            'webhook_endpoint' => '/api/webhooks/acme-bank',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
