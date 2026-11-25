<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\BalanceService;
use Illuminate\Database\Seeder;

class BalanceSeeder extends Seeder
{
    public function run(): void
    {
        $service = new BalanceService();
        $year = now()->year;

        foreach (User::active()->get() as $user) {
            $service->ensureForUser($user, $year);
        }
    }
}
