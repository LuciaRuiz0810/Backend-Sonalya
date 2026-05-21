<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlbumArtista;
use App\Models\ArtistaPerfil;
use App\Models\CancionArtista;
use App\Models\Entrada;
use App\Models\Evento;
use App\Models\SeguidorArtista;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Panel de administración: estadísticas globales y CRUD de usuarios,
 * artistas, canciones y eventos. Acceso restringido a rol admin.
 *
 * @author  Lucia Ruiz Salvador
 * @version 1.0.0
 * @date    2025-09-01
 */
class AdminController extends Controller
{
    private function soloAdmin(Request $request): void
    {
        if ($request->user()->tipo !== 'admin') {
            abort(403, 'Acceso restringido a administradores.');
        }
    }

    // ── Estadísticas globales ────────────────────────────────────────────────

    public function estadisticas(Request $request)
    {
        $this->soloAdmin($request);

        return response()->json([
            'total_usuarios'      => User::where('tipo', '!=', 'admin')->count(),
            'total_artistas'      => User::where('tipo', 'artista')->count(),
            'total_oyentes'       => User::where('tipo', 'oyente')->count(),
            'total_canciones'     => CancionArtista::count(),
            'total_albumes'       => AlbumArtista::count(),
            'total_reproducciones'=> CancionArtista::sum('reproducciones'),
            'total_seguidores'    => SeguidorArtista::count(),
            'total_eventos'       => Evento::count(),
            'total_entradas'      => Entrada::count(),
        ]);
    }

    // ── Usuarios ─────────────────────────────────────────────────────────────

    public function usuarios(Request $request)
    {
        $this->soloAdmin($request);

        $usuarios = User::select('id', 'nombre', 'nombre_usuario', 'email', 'tipo', 'avatar', 'nombre_artista', 'created_at')
            ->withCount(['cancionesFavoritas', 'playlists'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($u) => array_merge($u->toArray(), ['url_avatar' => $u->url_avatar]));

        return response()->json(['usuarios' => $usuarios]);
    }

    public function crearUsuario(Request $request)
    {
        $this->soloAdmin($request);

        $datos = $request->validate([
            'nombre'         => ['required', 'string', 'max:100'],
            'email'          => ['required', 'email', 'unique:users,email'],
            'nombre_usuario' => ['required', 'string', 'max:50', 'unique:users,nombre_usuario', 'regex:/^@\S+$/'],
            'password'       => ['required', 'string', 'min:8'],
            'tipo'           => ['required', 'in:oyente,artista,admin'],
            'nombre_artista' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $usuario = User::create($datos);

        if ($datos['tipo'] === 'artista') {
            ArtistaPerfil::create(['user_id' => $usuario->id]);
        }

        return response()->json(['usuario' => array_merge($usuario->fresh()->toArray(), ['url_avatar' => $usuario->url_avatar])], 201);
    }

    public function actualizarUsuario(Request $request, int $id)
    {
        $this->soloAdmin($request);

        $usuario = User::findOrFail($id);

        if ($usuario->tipo === 'admin' && $request->user()->id !== $id) {
            return response()->json(['message' => 'No puedes modificar otro administrador.'], 403);
        }

        $datos = $request->validate([
            'nombre'         => ['sometimes', 'string', 'max:100'],
            'email'          => ['sometimes', 'email', 'unique:users,email,' . $id],
            'nombre_usuario' => ['sometimes', 'string', 'max:50', 'unique:users,nombre_usuario,' . $id],
            'tipo'           => ['sometimes', 'in:oyente,artista,admin'],
        ]);

        $usuario->update($datos);

        return response()->json(['usuario' => $usuario->fresh()]);
    }

    public function eliminarUsuario(Request $request, int $id)
    {
        $this->soloAdmin($request);

        if ($request->user()->id === $id) {
            return response()->json(['message' => 'No puedes eliminarte a ti mismo.'], 422);
        }

        $usuario = User::findOrFail($id);

        if ($usuario->avatar) {
            Storage::disk('public')->delete($usuario->avatar);
        }

        $usuario->delete();

        return response()->json(['ok' => true]);
    }

    // ── Canciones ─────────────────────────────────────────────────────────────

    public function canciones(Request $request)
    {
        $this->soloAdmin($request);

        $canciones = CancionArtista::with(['artista:id,nombre,nombre_artista', 'album:id,titulo'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'titulo'         => $c->titulo,
                'artista'        => $c->artista?->nombre_artista ?? $c->artista?->nombre ?? '—',
                'artista_id'     => $c->artista_id,
                'album'          => $c->album?->titulo ?? null,
                'duracion'       => $c->duracion,
                'genero'         => $c->genero,
                'reproducciones' => $c->reproducciones,
                'activa'         => $c->activa,
                'imagen'         => $c->imagen,
                'audio_url'      => $c->audio_url,
                'created_at'     => $c->created_at?->format('Y-m-d'),
            ]);

        return response()->json(['canciones' => $canciones]);
    }

    public function crearCancion(Request $request)
    {
        $this->soloAdmin($request);

        $datos = $request->validate([
            'titulo'     => ['required', 'string', 'max:200'],
            'artista_id' => ['required', 'exists:users,id'],
            'album_id'   => ['sometimes', 'nullable', 'exists:albumes_artista,id'],
            'genero'     => ['sometimes', 'nullable', 'string', 'max:80'],
            'duracion'   => ['sometimes', 'nullable', 'string', 'max:10'],
            'imagen'     => ['sometimes', 'nullable', 'string', 'max:500'],
            'audio_url'  => ['required', 'string', 'max:500'],
            'activa'     => ['sometimes', 'boolean'],
        ]);

        $cancion = CancionArtista::create($datos);

        return response()->json(['cancion' => $cancion->load('artista', 'album')], 201);
    }

    public function actualizarCancion(Request $request, int $id)
    {
        $this->soloAdmin($request);

        $cancion = CancionArtista::findOrFail($id);

        $datos = $request->validate([
            'titulo'  => ['sometimes', 'string', 'max:200'],
            'genero'  => ['sometimes', 'nullable', 'string', 'max:80'],
            'activa'  => ['sometimes', 'boolean'],
        ]);

        $cancion->update($datos);

        return response()->json(['cancion' => $cancion->fresh()]);
    }

    public function eliminarCancion(Request $request, int $id)
    {
        $this->soloAdmin($request);

        $cancion = CancionArtista::findOrFail($id);

        if ($cancion->imagen)   $this->borrarUrl($cancion->imagen);
        if ($cancion->audio_url) $this->borrarUrl($cancion->audio_url);

        $cancion->delete();

        return response()->json(['ok' => true]);
    }

    // ── Álbumes ───────────────────────────────────────────────────────────────

    public function albumes(Request $request)
    {
        $this->soloAdmin($request);

        $albumes = AlbumArtista::with('artista:id,nombre,nombre_artista')
            ->withCount('canciones')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id'             => $a->id,
                'titulo'         => $a->titulo,
                'artista'        => $a->artista?->nombre_artista ?? $a->artista?->nombre ?? '—',
                'artista_id'     => $a->artista_id,
                'genero'         => $a->genero,
                'canciones_count'=> $a->canciones_count,
                'publicado_at'   => $a->publicado_at,
                'imagen'         => $a->imagen,
                'created_at'     => $a->created_at?->format('Y-m-d'),
            ]);

        return response()->json(['albumes' => $albumes]);
    }

    public function crearAlbum(Request $request)
    {
        $this->soloAdmin($request);

        $datos = $request->validate([
            'titulo'       => ['required', 'string', 'max:200'],
            'artista_id'   => ['required', 'exists:users,id'],
            'genero'       => ['sometimes', 'nullable', 'string', 'max:80'],
            'imagen'       => ['sometimes', 'nullable', 'string', 'max:500'],
            'publicado_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $album = AlbumArtista::create($datos);

        return response()->json(['album' => $album->load('artista')], 201);
    }

    public function eliminarAlbum(Request $request, int $id)
    {
        $this->soloAdmin($request);

        $album = AlbumArtista::findOrFail($id);

        if ($album->imagen) $this->borrarUrl($album->imagen);

        $album->delete();

        return response()->json(['ok' => true]);
    }

    // ── Eventos ───────────────────────────────────────────────────────────────

    public function eventos(Request $request)
    {
        $this->soloAdmin($request);

        $eventos = Evento::orderBy('fecha', 'asc')->get();

        return response()->json(['eventos' => $eventos]);
    }

    public function crearEvento(Request $request)
    {
        $this->soloAdmin($request);

        $datos = $request->validate([
            'nombre'       => ['required', 'string', 'max:200'],
            'artista'      => ['required', 'string', 'max:200'],
            'descripcion'  => ['sometimes', 'nullable', 'string'],
            'fecha'        => ['required', 'date'],
            'lugar'        => ['required', 'string', 'max:200'],
            'ciudad'       => ['required', 'string', 'max:100'],
            'precio'       => ['required', 'numeric', 'min:0'],
            'aforo'        => ['required', 'integer', 'min:1'],
            'imagen'       => ['sometimes', 'nullable', 'string', 'max:500'],
            'nuevo_talento'=> ['sometimes', 'boolean'],
        ]);

        $evento = Evento::create($datos);

        return response()->json(['evento' => $evento], 201);
    }

    public function actualizarEvento(Request $request, int $id)
    {
        $this->soloAdmin($request);

        $evento = Evento::findOrFail($id);

        $datos = $request->validate([
            'nombre'       => ['sometimes', 'string', 'max:200'],
            'artista'      => ['sometimes', 'string', 'max:200'],
            'descripcion'  => ['sometimes', 'nullable', 'string'],
            'fecha'        => ['sometimes', 'date'],
            'lugar'        => ['sometimes', 'string', 'max:200'],
            'ciudad'       => ['sometimes', 'string', 'max:100'],
            'precio'       => ['sometimes', 'numeric', 'min:0'],
            'aforo'        => ['sometimes', 'integer', 'min:1'],
            'imagen'       => ['sometimes', 'nullable', 'string', 'max:500'],
            'nuevo_talento'=> ['sometimes', 'boolean'],
        ]);

        $evento->update($datos);

        return response()->json(['evento' => $evento->fresh()]);
    }

    public function eliminarEvento(Request $request, int $id)
    {
        $this->soloAdmin($request);

        Evento::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function borrarUrl(?string $url): void
    {
        if (!$url) return;
        $ruta = Str::after($url, url('storage/'));
        if ($ruta && Storage::disk('public')->exists($ruta)) {
            Storage::disk('public')->delete($ruta);
        }
    }
}
