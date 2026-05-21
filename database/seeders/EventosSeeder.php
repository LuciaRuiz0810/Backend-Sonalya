<?php

namespace Database\Seeders;

use App\Models\Evento;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EventosSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $eventos = [
            [
                'nombre'      => 'Bad Bunny - Most Wanted Tour',
                'descripcion' => 'El rey del trap latino trae su gira mundial a Madrid con un espectáculo lleno de sorpresas y producción espectacular.',
                'artista'     => 'Bad Bunny',
                'imagen'      => 'https://i.imgur.com/badbunny.jpg',
                'fecha'       => '2026-05-22 21:00:00',
                'lugar'       => 'Estadio Santiago Bernabéu',
                'ciudad'      => 'Madrid',
                'precio'      => 89.00,
                'aforo'       => 50000,
                'entradas_vendidas' => 38000,
            ],
            [
                'nombre'      => 'Taylor Swift - The Eras Tour',
                'descripcion' => 'La artista más taquillera del mundo vuelve a Europa con su legendaria gira que recorre todas sus eras musicales.',
                'artista'     => 'Taylor Swift',
                'imagen'      => 'https://i.imgur.com/taylorswift.jpg',
                'fecha'       => '2026-06-05 20:30:00',
                'lugar'       => 'Palau Sant Jordi',
                'ciudad'      => 'Barcelona',
                'precio'      => 120.00,
                'aforo'       => 17000,
                'entradas_vendidas' => 17000,
            ],
            [
                'nombre'      => 'Coldplay - Music of the Spheres World Tour',
                'descripcion' => 'Coldplay presenta su espectacular gira sostenible con pantallas LED, confeti biodegradable y pulseras luminosas para todo el público.',
                'artista'     => 'Coldplay',
                'imagen'      => 'https://i.imgur.com/coldplay.jpg',
                'fecha'       => '2026-06-18 21:00:00',
                'lugar'       => 'Estadio Metropolitano',
                'ciudad'      => 'Madrid',
                'precio'      => 95.00,
                'aforo'       => 68000,
                'entradas_vendidas' => 52000,
            ],
            [
                'nombre'      => 'Rosalía - Motomami Live',
                'descripcion' => 'La artista catalana más internacional regresa a casa con un show íntimo lleno de experimentación sonora y visuales únicos.',
                'artista'     => 'Rosalía',
                'imagen'      => 'https://i.imgur.com/rosalia.jpg',
                'fecha'       => '2026-07-04 22:00:00',
                'lugar'       => 'Wizink Center',
                'ciudad'      => 'Madrid',
                'precio'      => 75.00,
                'aforo'       => 18000,
                'entradas_vendidas' => 14000,
            ],
            [
                'nombre'      => 'The Weeknd - After Hours til Dawn Tour',
                'descripcion' => 'Abel Tesfaye deslumbra con su producción cinematográfica en una noche que fusiona r&b, pop y electrónica.',
                'artista'     => 'The Weeknd',
                'imagen'      => 'https://i.imgur.com/theweeknd.jpg',
                'fecha'       => '2026-07-17 21:30:00',
                'lugar'       => 'Ciudad del Rock',
                'ciudad'      => 'Madrid',
                'precio'      => 85.00,
                'aforo'       => 40000,
                'entradas_vendidas' => 28000,
            ],
            [
                'nombre'      => 'Karol G - Mañana Será Bonito Tour',
                'descripcion' => 'La Bichota llega a Valencia con energía desbordante y sus éxitos más recientes en una noche llena de color.',
                'artista'     => 'Karol G',
                'imagen'      => 'https://i.imgur.com/karolg.jpg',
                'fecha'       => '2026-08-01 22:00:00',
                'lugar'       => 'Estadio Mestalla',
                'ciudad'      => 'Valencia',
                'precio'      => 65.00,
                'aforo'       => 30000,
                'entradas_vendidas' => 18000,
            ],
            [
                'nombre'      => 'Dua Lipa - Future Nostalgia Tour',
                'descripcion' => 'Dua Lipa presenta su propuesta disco-pop con una producción visual impresionante y sus hits internacionales.',
                'artista'     => 'Dua Lipa',
                'imagen'      => 'https://i.imgur.com/dualipa.jpg',
                'fecha'       => '2026-08-14 21:00:00',
                'lugar'       => 'Palau Sant Jordi',
                'ciudad'      => 'Barcelona',
                'precio'      => 80.00,
                'aforo'       => 17000,
                'entradas_vendidas' => 12000,
            ],
            [
                'nombre'      => 'C. Tangana - El Madrileño Live',
                'descripcion' => 'Pucho vuelve a los escenarios con una puesta en escena teatral que mezcla flamenco, trap y rumba en una experiencia única.',
                'artista'     => 'C. Tangana',
                'imagen'      => 'https://i.imgur.com/ctangana.jpg',
                'fecha'       => '2026-09-06 22:00:00',
                'lugar'       => 'La Riviera',
                'ciudad'      => 'Madrid',
                'precio'      => 45.00,
                'aforo'       => 1500,
                'entradas_vendidas' => 800,
                'nuevo_talento' => true,
            ],
            [
                'nombre'      => 'Beyoncé - Renaissance World Tour',
                'descripcion' => 'Queen B regresa a Europa con su gira más ambiciosa, celebrando la música dance y la cultura negra en un espectáculo de tres horas.',
                'artista'     => 'Beyoncé',
                'imagen'      => 'https://i.imgur.com/beyonce.jpg',
                'fecha'       => '2026-09-19 21:00:00',
                'lugar'       => 'Estadio Olímpico',
                'ciudad'      => 'Sevilla',
                'precio'      => 115.00,
                'aforo'       => 55000,
                'entradas_vendidas' => 50000,
            ],
            [
                'nombre'      => 'Billie Eilish - Hit Me Hard and Soft Tour',
                'descripcion' => 'La voz de una generación presenta su álbum más íntimo en una gira que explora la vulnerabilidad y la fuerza femenina.',
                'artista'     => 'Billie Eilish',
                'imagen'      => 'https://i.imgur.com/billieeilish.jpg',
                'fecha'       => '2026-10-03 20:30:00',
                'lugar'       => 'BEC - Bilbao Exhibition Centre',
                'ciudad'      => 'Bilbao',
                'precio'      => 70.00,
                'aforo'       => 10000,
                'entradas_vendidas' => 6500,
            ],
            [
                'nombre'      => 'Peso Pluma - Génesis Tour',
                'descripcion' => 'El fenómeno del corrido tumbado hace su debut en España con una noche que promete reventar los altavoces.',
                'artista'     => 'Peso Pluma',
                'imagen'      => 'https://i.imgur.com/pesopluma.jpg',
                'fecha'       => '2026-11-14 22:00:00',
                'lugar'       => 'Palau Sant Jordi',
                'ciudad'      => 'Barcelona',
                'precio'      => 55.00,
                'aforo'       => 17000,
                'entradas_vendidas' => 9000,
                'nuevo_talento' => true,
            ],
            [
                'nombre'      => 'Harry Styles - Love on Tour',
                'descripcion' => 'Harry Styles cierra el año con su gira más emotiva, llena de confeti, magia y canciones para cantar a pleno pulmón.',
                'artista'     => 'Harry Styles',
                'imagen'      => 'https://i.imgur.com/harrystyles.jpg',
                'fecha'       => '2026-12-12 21:00:00',
                'lugar'       => 'Wizink Center',
                'ciudad'      => 'Madrid',
                'precio'      => 90.00,
                'aforo'       => 18000,
                'entradas_vendidas' => 15000,
            ],
        ];

        foreach ($eventos as $evento) {
            Evento::create($evento);
        }
    }
}
