<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtistaSeguido;
use App\Models\Entrada;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Gestión de eventos musicales: listado, detalle y compra de entradas.
 *
 * @author  Lucia Ruiz Salvador
 * @version 1.0.0
 * @date    2025-09-01
 */
class EventoController extends Controller
{
    public function index()
    {
        // Ruta pública: intentar resolver usuario autenticado vía Bearer token
        $user   = auth()->user() ?? auth('sanctum')->user();
        $userId = $user?->id;

        $eventos = Evento::orderBy('fecha', 'asc')->get();
        $resultado = $this->procesarEventos($eventos, $userId);

        return response()->json(['eventos' => $resultado]);
    }

    public function eventosSeguidos()
    {
        $userId = auth()->id();

        $nombresSeguidos = ArtistaSeguido::where('user_id', $userId)
            ->pluck('nombre')
            ->map(fn ($n) => strtolower(trim($n)))
            ->all();

        if (empty($nombresSeguidos)) {
            return response()->json(['eventos' => []]);
        }

        $eventos = Evento::orderBy('fecha', 'asc')->get()->filter(function ($evento) use ($nombresSeguidos) {
            return in_array(strtolower(trim($evento->artista)), $nombresSeguidos, true);
        })->values();

        $resultado = $this->procesarEventos($eventos, $userId);

        return response()->json(['eventos' => $resultado]);
    }

    public function eventosTalentos()
    {
        // Ruta pública: intentar resolver usuario autenticado vía Bearer token
        $user   = auth()->user() ?? auth('sanctum')->user();
        $userId = $user?->id;

        $eventos = Evento::where('nuevo_talento', true)->orderBy('fecha', 'asc')->get();
        $resultado = $this->procesarEventos($eventos, $userId);

        return response()->json(['eventos' => $resultado]);
    }

    private function procesarEventos($eventos, ?int $userId): \Illuminate\Support\Collection
    {
        $todosArtistas = Evento::pluck('artista')->unique()->values()->all();
        $imagenesArtista = $this->obtenerImagenesArtistas($todosArtistas);

        return $eventos->map(function ($evento) use ($userId, $imagenesArtista) {
            $data = $evento->toArray();
            $data['entradas_disponibles'] = $evento->aforo - $evento->entradas_vendidas;
            $data['tiene_entrada'] = $userId
                ? Entrada::where('user_id', $userId)->where('evento_id', $evento->id)->exists()
                : false;
            $data['imagen'] = $imagenesArtista[$evento->artista] ?? $evento->imagen;
            return $data;
        });
    }

    private function obtenerImagenesArtistas(array $artistas): array
    {
        return Cache::remember('eventos_imagenes_v2', 86400, function () use ($artistas) {
            try {
                $responses = Http::pool(fn ($pool) =>
                    array_map(
                        fn ($artista) => $pool->timeout(8)->get('https://api.deezer.com/search/artist', ['q' => $artista, 'limit' => 1]),
                        $artistas
                    )
                );
                $map = [];
                foreach ($artistas as $i => $artista) {
                    $body = $responses[$i]->json();
                    $map[$artista] = $body['data'][0]['picture_medium'] ?? null;
                }
                return $map;
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function comprar($id)
    {
        $evento = Evento::findOrFail($id);
        $userId = auth()->id();

        // Verificar si ya tiene entrada
        if (Entrada::where('user_id', $userId)->where('evento_id', $evento->id)->exists()) {
            return response()->json(['message' => 'Ya tienes una entrada para este evento'], 409);
        }

        // Verificar disponibilidad
        if ($evento->entradas_vendidas >= $evento->aforo) {
            return response()->json(['message' => 'No hay entradas disponibles'], 422);
        }

        $entrada = Entrada::create([
            'user_id'   => $userId,
            'evento_id' => $evento->id,
            'codigo'    => strtoupper(Str::random(12)),
            'estado'    => 'confirmada',
        ]);

        $evento->increment('entradas_vendidas');

        return response()->json(['entrada' => $entrada->load('evento')], 201);
    }

    public function misEntradas()
    {
        $entradas = Entrada::where('user_id', auth()->id())
            ->with('evento')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['entradas' => $entradas]);
    }
}
