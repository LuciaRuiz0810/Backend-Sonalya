<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtistaSeguido;
use App\Models\CancionFavorita;
use App\Models\Entrada;
use App\Models\HistorialReproduccion;
use Illuminate\Support\Facades\Http;

/**
 * Recomendaciones personalizadas basadas en el historial y favoritos del usuario.
 *
 * @author  Lucia Ruiz Salvador
 * @version 1.0.0
 * @date    2025-09-01
 */
class RecomendacionController extends Controller
{
    private const URL_DEEZER = 'https://api.deezer.com';

    public function index()
    {
        $userId = auth()->id();

        // 1. IDs de artistas seguidos (hasta 4)
        $idsArtistas = ArtistaSeguido::where('user_id', $userId)
            ->pluck('deezer_id')
            ->take(4)
            ->all();

        // 2. Artistas más frecuentes del historial (top 4)
        $artistasHistorial = HistorialReproduccion::where('user_id', $userId)
            ->selectRaw('artista, COUNT(*) as cnt')
            ->groupBy('artista')
            ->orderByDesc('cnt')
            ->limit(4)
            ->pluck('artista')
            ->all();

        // 3. Artistas de canciones favoritas (top 3 más repetidos)
        $artistasFavs = CancionFavorita::where('user_id', $userId)
            ->selectRaw('artista, COUNT(*) as cnt')
            ->groupBy('artista')
            ->orderByDesc('cnt')
            ->limit(3)
            ->pluck('artista')
            ->all();

        // 4. Artistas de eventos con entradas compradas (géneros/tipo de evento)
        $artistasEventos = Entrada::where('user_id', $userId)
            ->with('evento:id,artista')
            ->get()
            ->pluck('evento.artista')
            ->filter()
            ->unique()
            ->take(2)
            ->values()
            ->all();

        // Si no hay ningún dato de usuario, devolver vacío
        if (empty($idsArtistas) && empty($artistasHistorial) && empty($artistasFavs)) {
            return response()->json(['recomendaciones' => []]);
        }

        // Construir las URLs a consultar en paralelo
        $urls = [];

        // Canciones top de artistas seguidos (por ID Deezer)
        foreach ($idsArtistas as $id) {
            $urls[] = self::URL_DEEZER . "/artist/{$id}/top?limit=5";
        }

        // Búsqueda por nombre de artista para historial + favs + eventos
        $nombresBusqueda = collect(array_merge($artistasHistorial, $artistasFavs, $artistasEventos))
            ->unique()
            ->take(6)
            ->values()
            ->all();

        foreach ($nombresBusqueda as $nombre) {
            $urls[] = self::URL_DEEZER . '/search/track?q=' . urlencode("artist:\"{$nombre}\"") . '&limit=5';
        }

        if (empty($urls)) {
            return response()->json(['recomendaciones' => []]);
        }

        // Petición paralela
        $responses = Http::pool(fn ($pool) =>
            array_map(fn ($url) => $pool->timeout(8)->get($url), $urls)
        );

        $canciones = collect();
        foreach ($responses as $resp) {
            if (!$resp->successful()) continue;
            $tracks = $resp->json()['data'] ?? [];
            foreach (array_slice($tracks, 0, 3) as $t) {
                if (!empty($t['preview'])) {
                    $canciones->push($this->formatCancion($t));
                }
            }
        }

        $resultado = $canciones
            ->unique('id')
            ->shuffle()
            ->take(12)
            ->values();

        return response()->json(['recomendaciones' => $resultado]);
    }

    private function formatCancion(array $t): array
    {
        return [
            'id'         => $t['id'],
            'nombre'     => $t['title'],
            'artista'    => $t['artist']['name'],
            'artista_id' => $t['artist']['id'],
            'imagen'     => $t['album']['cover_medium'] ?? null,
            'duracion'   => $this->segundosAMinutos($t['duration'] ?? 0),
            'preview'    => $t['preview'],
            'tipo'       => 'cancion',
        ];
    }

    private function segundosAMinutos(int $s): string
    {
        return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }
}
