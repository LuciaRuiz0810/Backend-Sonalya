<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\PlaylistCancion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CRUD de playlists del usuario autenticado y gestión de sus canciones.
 *
 * @author  Lucia Ruiz Salvador
 * @version 1.0.0
 * @date    2025-09-01
 */
class PlaylistController extends Controller
{
    public function index()
    {
        $playlists = auth()->user()
            ->playlists()
            ->withCount('canciones')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['playlists' => $playlists]);
    }

    public function show($id)
    {
        $playlist = Playlist::where('id', $id)
            ->where('user_id', auth()->id())
            ->with(['canciones' => fn ($q) => $q->orderBy('orden')])
            ->withCount('canciones')
            ->firstOrFail();

        return response()->json(['playlist' => $playlist]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $playlist = auth()->user()->playlists()->create([
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'imagen'      => $request->imagen,
            'publica'     => $request->boolean('publica', false),
        ]);

        $playlist->loadCount('canciones');

        return response()->json(['playlist' => $playlist], 201);
    }

    public function update($id, Request $request)
    {
        $playlist = Playlist::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
        ]);

        $data = [];
        if ($request->has('nombre'))      $data['nombre']      = $request->nombre;
        if ($request->has('descripcion')) $data['descripcion'] = $request->descripcion ?: null;
        if ($request->has('imagen'))      $data['imagen']      = $request->imagen ?: null;

        if (!empty($data)) {
            $playlist->update($data);
        }

        return response()->json(['playlist' => $playlist->fresh()->loadCount('canciones')]);
    }

    public function subirImagen($id, Request $request)
    {
        $playlist = Playlist::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $request->validate([
            'imagen' => 'required|image|max:4096',
        ]);

        // Borrar imagen anterior si fue subida al storage local
        if ($playlist->imagen && Str::contains($playlist->imagen, '/storage/playlists/')) {
            $oldPath = Str::after($playlist->imagen, '/storage/');
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('imagen')->store('playlists', 'public');
        $url  = asset(Storage::url($path));

        $playlist->update(['imagen' => $url]);

        return response()->json(['playlist' => $playlist->fresh()->loadCount('canciones')]);
    }

    public function destroy($id)
    {
        $playlist = Playlist::findOrFail($id);

        if ($playlist->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $playlist->delete();

        return response()->json(['message' => 'Playlist eliminada']);
    }

    public function agregarCancion($id, Request $request)
    {
        $playlist = Playlist::findOrFail($id);

        if ($playlist->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'deezer_id' => 'required|string',
            'titulo'    => 'required|string',
            'artista'   => 'required|string',
        ]);

        $orden = $playlist->canciones()->max('orden') + 1;

        $cancion = $playlist->canciones()->create([
            'deezer_id' => $request->deezer_id,
            'titulo'    => $request->titulo,
            'artista'   => $request->artista,
            'imagen'    => $request->imagen,
            'duracion'  => $request->duracion,
            'preview'   => $request->preview,
            'orden'     => $orden,
        ]);

        return response()->json($cancion, 201);
    }

    public function quitarCancion($playlistId, $cancionId)
    {
        $playlist = Playlist::findOrFail($playlistId);

        if ($playlist->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $cancion = PlaylistCancion::where('playlist_id', $playlistId)
            ->where('id', $cancionId)
            ->firstOrFail();

        $cancion->delete();

        return response()->json(['message' => 'Canción eliminada de la playlist']);
    }
}
