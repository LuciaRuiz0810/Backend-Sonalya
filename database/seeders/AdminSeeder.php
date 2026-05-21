<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'admin@sonalya.com')->exists()) {
            return;
        }

        User::create([
            'nombre'         => 'Administrador',
            'nombre_usuario' => '@admin',
            'email'          => 'admin@sonalya.com',
            'password'       => Hash::make('Admin1234!'),
            'tipo'           => 'admin',
        ]);
    }
}
