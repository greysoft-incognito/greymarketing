<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Starter plans. Prices and limits are placeholders to adjust from the admin API.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        if (Plan::query()->exists()) {
            return;
        }

        Plan::create([
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'Try GreyMarketing with your own provider keys.',
            'price' => 0,
            'contacts_limit' => 500,
            'emails_limit' => 2000,
            'sms_limit' => 200,
            'seats_limit' => 1,
            'is_default' => true,
            'sort' => 0,
        ]);

        Plan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'For small businesses sending regular campaigns.',
            'price' => 5000,
            'contacts_limit' => 5000,
            'emails_limit' => 25000,
            'sms_limit' => 2500,
            'seats_limit' => 3,
            'sort' => 1,
        ]);

        Plan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'description' => 'For growing audiences and frequent sends.',
            'price' => 20000,
            'contacts_limit' => 50000,
            'emails_limit' => 250000,
            'sms_limit' => 25000,
            'seats_limit' => 10,
            'sort' => 2,
        ]);
    }
}
