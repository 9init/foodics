<?php

namespace Database\Seeders;

use App\Models\Acquirer;
use Illuminate\Database\Seeder;

class AcquirerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $acquirers = [
            [
                'name' => 'Foodics Bank',
                'identifier' => 'foodics_bank',
                'parser_class' => \App\Services\Webhook\Parsers\FoodicsBankParser::class,
                'country_code' => 'SA',
                'currency' => 'SAR',
                'webhook_endpoint' => '/api/webhooks/foodics-bank',
                'is_active' => true,
                'metadata' => [
                    'description' => 'Foodics payment gateway for Saudi Arabia',
                    'format' => 'Date,Amount#Reference#metadata',
                ],
            ],
            [
                'name' => 'Acme Bank',
                'identifier' => 'acme_bank',
                'parser_class' => \App\Services\Webhook\Parsers\AcmeBankParser::class,
                'country_code' => 'US',
                'currency' => 'USD',
                'webhook_endpoint' => '/api/webhooks/acme-bank',
                'is_active' => true,
                'metadata' => [
                    'description' => 'Acme payment gateway for international transactions',
                    'format' => 'Amount//Reference//Date',
                ],
            ],
        ];

        foreach ($acquirers as $acquirer) {
            Acquirer::updateOrCreate(
                ['identifier' => $acquirer['identifier']],
                $acquirer
            );
        }

        $this->command->info('Acquirers seeded successfully!');
    }
}
