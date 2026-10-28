<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('GREENBASKET_ADMIN_EMAIL');
        $password = env('GREENBASKET_ADMIN_PASSWORD');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return;
        }

        $admin = User::firstOrNew(['email' => $email]);
        $admin->forceFill([
            'name' => env('GREENBASKET_ADMIN_NAME', 'GreenBasket Admin'),
            'email' => $email,
            'password' => $password,
            'is_admin' => true,
        ])->save();
    }
}
