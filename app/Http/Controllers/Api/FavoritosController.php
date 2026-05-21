<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CancionFavorita;
use App\Models\AlbumFavorito;
use App\Models\ArtistaSeguido;
use Illuminate\Http\Request;

/**
 * Gestión de favoritos del usuario: canciones, álbumes y artistas.
 *
 * @author  Lucia Ruiz Salvador
 * @version 1.0.0
 * @date    2025-09-01
 */
class FavoritosController extends Controller
{
    // ─── Canciones ───────────────────────────────────────────────────────────

    public function canciones()
    {
        $canciones = CancionFavorita::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['canciones' => $canciones]);
    }

    public function agregarCancion(Request $request)
    {
        $request->validate([
            'deezer_id' => 'required|string',
            'titulo'    => 'required|string',
            'artista'   => 'required|string',
        ]);

        $cancion = CancionFavorita::updateOrCreate(
            ['user_id' => auth()->id(), 'deezer_id' => $request->deezer_id],
            array_filter([
                'titulo'   => $request->titulo,
                'artista'  => $request->artista,
                'imagen'   => $request->imagen   ?: null,
                'duracion' => $request->duracion  ?: null,
                'preview'  => $request->preview   ?: null,
            ], fn($v) => $v !== null)
        );

        return response()->json($cancion, 201);
    }

    public function quitarCancion($deezerId)
    {
        CancionFavorita::where('user_id', auth()->id())
            ->where('deezer_id', $deezerId)
            ->delete();

        return response()->json(['message' => 'Canción eliminada de favoritos']);
    }

    // ─── Álbumes ─────────────────────────────────────────────────────────────

    public function albumes()
    {
        $albumes = AlbumFavorito::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['albumes' => $albumes]);
    }

    public function agregarAlbum(Request $request)
    {
        $request->validate([
            'deezer_id' => 'required|string',
            'nombre'    => 'required|string',
            'artista'   => 'required|string',
        ]);

        $album = AlbumFavorito::firstOrCreate(
            ['user_id' => auth()->id(), 'deezer_id' => $request->deezer_id],
            [
                'nombre'  => $request->nombre,
                'artista' => $request->artista,
                'imagen'  => $request->imagen,
            ]
        );

        return response()->json($album, 201);
    }

    public function quitarAlbum($deezerId)
    {
        AlbumFavorito::where('user_id', auth()->id())
            ->where('deezer_id', $deezerId)
            ->delete();

        return response()->json(['message' => 'Álbum eliminado de favoritos']);
    }

    // ─── Artistas ─────────────────────────────────────────────────────────────

    public function artistas()
    {
        $artistas = ArtistaSeguido::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['artistas' => $artistas]);
    }

    public function seguirArtista(Request $request)
    {
        $request->validate([
            'deezer_id' => 'required|string',
            'nombre'    => 'required|string',
        ]);

        $artista = ArtistaSeguido::firstOrCreate(
            ['user_id' => auth()->id(), 'deezer_id' => $request->deezer_id],
            [
                'nombre'      => $request->nombre,
                'imagen'      => $request->imagen,
                'descripcion' => $request->descripcion,
            ]
        );

        return response()->json($artista, 201);
    }

    public function dejarSeguirArtista($deezerId)
    {
        ArtistaSeguido::where('user_id', auth()->id())
            ->where('deezer_id', $deezerId)
            ->delete();

        return response()->json(['message' => 'Dejaste de seguir al artista']);
    }

    // ─── Estado ───────────────────────────────────────────────────────────────

    public function estadoFavorito($tipo, $deezerId)
    {
        $userId = auth()->id();

        $esFavorito = match ($tipo) {
            'cancion' => CancionFavorita::where('user_id', $userId)->where('deezer_id', $deezerId)->exists(),
            'album'   => AlbumFavorito::where('user_id', $userId)->where('deezer_id', $deezerId)->exists(),
            'artista' => ArtistaSeguido::where('user_id', $userId)->where('deezer_id', $deezerId)->exists(),
            default   => false,
        };

        return response()->json(['esFavorito' => $esFavorito]);
    }
}
