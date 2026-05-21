<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlbumArtista;
use App\Models\CancionArtista;
use App\Models\SeguidorArtista;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Perfiles públicos de artistas de la plataforma: información, canciones y álbumes.
 *
 * @author  Lucia Ruiz Salvador
 * @version 1.0.0
 * @date    2025-09-01
 */
class ArtistaPublicoController extends Controller
{
    // Lista todos los artistas de la plataforma
    public function listar()
    {
        $artistas = User::where('tipo', 'artista')
            ->with('artistaPerfil')
            ->withCount('seguidores')
            ->withCount('cancionesArtista')
            ->orderByDesc('seguidores_count')
            ->limit(50)
            ->get()
            ->map(fn ($u) => $this->formatearCard($u));

        return response()->json(['artistas' => $artistas]);
    }

    // Perfil público de un artista de la plataforma
    public function perfil(int $id)
    {
        $user = User::where('tipo', 'artista')
            ->with('artistaPerfil')
            ->withCount('seguidores')
            ->findOrFail($id);

        $canciones = CancionArtista::where('artista_id', $id)
            ->where('activa', true)
            ->whereNull('album_id')
            ->orderByDesc('reproducciones')
            ->limit(20)
            ->get()
            ->map(fn ($c) => $c->toFormatoReproductor());

        $albumes = AlbumArtista::where('artista_id', $id)
            ->withCount('canciones')
            ->orderByDesc('publicado_at')
            ->get();

        return response()->json([
            'artista'  => $this->formatearPerfil($user),
            'canciones' => $canciones,
            'albumes'   => $albumes,
        ]);
    }

    // Canciones de un álbum de artista de plataforma
    public function cancionesAlbum(int $albumId)
    {
        $album = AlbumArtista::with('artista')->findOrFail($albumId);

        $canciones = CancionArtista::where('album_id', $albumId)
            ->where('activa', 1)
            ->with(['artista', 'album'])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($c) => $c->toFormatoReproductor());

        return response()->json([
            'album'    => $album,
            'canciones' => $canciones,
        ]);
    }

    // Seguir / dejar de seguir
    public function seguir(Request $request, int $id)
    {
        $artistaObj = User::where('tipo', 'artista')->findOrFail($id);
        $seguidor   = $request->user();

        if ($seguidor->id === $id) {
            return response()->json(['message' => 'No puedes seguirte a ti mismo.'], 422);
        }

        SeguidorArtista::firstOrCreate([
            'artista_id'  => $id,
            'seguidor_id' => $seguidor->id,
        ]);

        return response()->json(['siguiendo' => true]);
    }

    public function dejarSeguir(Request $request, int $id)
    {
        SeguidorArtista::where('artista_id', $id)
            ->where('seguidor_id', $request->user()->id)
            ->delete();

        return response()->json(['siguiendo' => false]);
    }

    public function estadoSeguimiento(Request $request, int $id)
    {
        $siguiendo = SeguidorArtista::where('artista_id', $id)
            ->where('seguidor_id', $request->user()->id)
            ->exists();

        return response()->json(['siguiendo' => $siguiendo]);
    }

    // Incrementar reproducciones de una canción
    public function reproducir(int $cancionId)
    {
        CancionArtista::where('id', $cancionId)->increment('reproducciones');
        return response()->json(['ok' => true]);
    }

    // Búsqueda de contenido de la plataforma (artistas, canciones, álbumes)
    public function buscarPlataforma(Request $request)
    {
        $q = trim($request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['artistas' => [], 'canciones' => [], 'albumes' => []]);
        }

        $artistas = User::where('tipo', 'artista')
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                      ->orWhere('nombre_artista', 'like', "%{$q}%");
            })
            ->with('artistaPerfil')
            ->withCount('seguidores')
            ->limit(10)
            ->get()
            ->map(fn ($u) => $this->formatearCard($u));

        $canciones = CancionArtista::where('activa', true)
            ->where('titulo', 'like', "%{$q}%")
            ->with(['artista', 'album'])
            ->orderByDesc('reproducciones')
            ->limit(20)
            ->get()
            ->map(fn ($c) => $c->toFormatoReproductor());

        $albumes = AlbumArtista::where('titulo', 'like', "%{$q}%")
            ->with('artista')
            ->withCount('canciones')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id'          => 'plataforma_album_' . $a->id,
                'album_id'    => $a->id,
                'nombre'      => $a->titulo,
                'imagen'      => $a->imagen,
                'tipo'        => 'album_plataforma',
                'descripcion' => ($a->artista?->nombre_artista ?? $a->artista?->nombre ?? '') . ' · ' . $a->canciones_count . ' canciones',
                'artista_id'  => $a->artista_id,
            ]);

        return response()->json([
            'artistas' => $artistas,
            'canciones' => $canciones,
            'albumes'   => $albumes,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formatearCard(User $u): array
    {
        return [
            'id'             => $u->id,
            'nombre'         => $u->nombre_artista ?? $u->nombre,
            'nombre_usuario' => $u->nombre_usuario,
            'imagen'         => $u->url_avatar,
            'descripcion'    => ($u->seguidores_count ?? 0) . ' seguidores',
            'tipo'           => 'artista_plataforma',
            'seguidores'     => $u->seguidores_count ?? 0,
        ];
    }

    private function formatearPerfil(User $u): array
    {
        $perfil = $u->artistaPerfil;
        return [
            'id'             => $u->id,
            'nombre'         => $u->nombre_artista ?? $u->nombre,
            'nombre_usuario' => $u->nombre_usuario,
            'imagen'         => $u->url_avatar,
            'tipo'           => 'artista_plataforma',
            'seguidores'     => $u->seguidores_count ?? 0,
            'bio'            => $perfil?->bio,
            'genero'         => $perfil?->genero,
            'ciudad'         => $perfil?->ciudad,
            'sitio_web'      => $perfil?->sitio_web,
            'imagen_portada' => $perfil?->imagen_portada,
        ];
    }
}
