<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistorialReproduccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Registro y consulta del historial de reproducciones del usuario.
 *
 * @author  Lucia Ruiz Salvador
 * @version 1.0.0
 * @date    2025-09-01
 */
class HistorialController extends Controller
{
    public function index()
    {
        $historial = HistorialReproduccion::where('user_id', auth()->id())
            ->orderBy('reproducido_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['historial' => $historial]);
    }

    public function populares()
    {
        $userId = auth()->id();

        // Canciones más repetidas (distintos días)
        $canciones = HistorialReproduccion::where('user_id', $userId)
            ->select('deezer_id', 'titulo', 'artista', 'imagen', 'preview',
                DB::raw('COUNT(*) as reproducciones'))
            ->groupBy('deezer_id', 'titulo', 'artista', 'imagen', 'preview')
            ->orderByDesc('reproducciones')
            ->limit(5)
            ->get()
            ->map(fn ($h) => [
                'id'             => $h->deezer_id,
                'titulo'         => $h->titulo,
                'artista'        => $h->artista,
                'imagen'         => $h->imagen,
                'preview'        => $h->preview,
                'duracion'       => '',
                'reproducciones' => $h->reproducciones,
                'tipo'           => 'cancion',
            ]);

        // Número de canciones distintas escuchadas (para el umbral del frontend)
        $totalDistintas = HistorialReproduccion::where('user_id', $userId)
            ->distinct('deezer_id')
            ->count('deezer_id');

        return response()->json([
            'canciones'      => $canciones,
            'total_distintas' => $totalDistintas,
        ]);
    }

    public function registrar(Request $request)
    {
        $request->validate([
            'deezer_id' => 'required|string',
            'titulo'    => 'required|string',
            'artista'   => 'required|string',
            'tipo'      => 'required|string',
        ]);

        $userId = auth()->id();

        // Si la misma canción ya fue reproducida hoy, solo actualizar el timestamp
        $existente = HistorialReproduccion::where('user_id', $userId)
            ->where('deezer_id', $request->deezer_id)
            ->where('reproducido_at', '>=', now()->startOfDay())
            ->first();

        if ($existente) {
            HistorialReproduccion::where('id', $existente->id)
                ->update(['reproducido_at' => now()]);
            return response()->json($existente, 200);
        }

        $entrada = HistorialReproduccion::create([
            'user_id'   => $userId,
            'deezer_id' => $request->deezer_id,
            'tipo'      => $request->tipo,
            'titulo'    => $request->titulo,
            'artista'   => $request->artista,
            'imagen'    => $request->imagen,
            'preview'   => $request->preview,
        ]);

        // Mantener sólo las últimas 200 entradas por usuario
        $total = HistorialReproduccion::where('user_id', $userId)->count();

        if ($total > 200) {
            $idsAEliminar = HistorialReproduccion::where('user_id', $userId)
                ->orderBy('reproducido_at', 'asc')
                ->limit($total - 200)
                ->pluck('id');

            HistorialReproduccion::whereIn('id', $idsAEliminar)->delete();
        }

        return response()->json($entrada, 201);
    }
}
