<?php

namespace Database\Seeders;

use App\Models\ArtistaPerfil;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Oyente demo ──────────────────────────────────────────────────────
        if (!User::where('email', 'oyente@sonalya.com')->exists()) {
            User::create([
                'nombre'         => 'Oyente Demo',
                'nombre_usuario' => '@oyente_demo',
                'email'          => 'oyente@sonalya.com',
                'password'       => Hash::make('Oyente1234!'),
                'tipo'           => 'oyente',
                'biografia'      => 'Amante de la música en todos sus géneros. Siempre con los auriculares puestos.',
            ]);
        }

        // ── Artista demo ─────────────────────────────────────────────────────
        if (!User::where('email', 'artista@sonalya.com')->exists()) {
            $artista = User::create([
                'nombre'         => 'Artista Demo',
                'nombre_artista' => 'Artista Ejemplo',
                'nombre_usuario' => '@artista_ejemplo',
                'email'          => 'artista@sonalya.com',
                'password'       => Hash::make('Artista1234!'),
                'tipo'           => 'artista',
                'biografia'      => 'Compositor y productor musical independiente. Fusiono pop, electrónica y soul.',
            ]);

            ArtistaPerfil::create([
                'user_id'  => $artista->id,
                'bio'      => 'Artista indie afincado en Madrid. Llevo 10 años creando música que mezcla pop, electrónica y soul. Mi objetivo es que cada canción cuente una historia real.',
                'genero'   => 'Pop / Electrónica',
                'ciudad'   => 'Madrid',
                'sitio_web'=> 'https://artista_ejemplo.com',
            ]);
        }
    }
}
