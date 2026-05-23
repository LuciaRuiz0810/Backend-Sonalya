<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlbumArtista;
use App\Models\ArtistaPerfil;
use App\Models\CancionArtista;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Panel del artista: gestión de perfil, canciones y álbumes propios.
 *
 * @author  Lucia Ruiz Salvador
 * @version 1.0.0
 * @date    2025-09-01
 */
class ArtistaCuentaController extends Controller
{
    private function soloArtista(Request $request)
    {
        if ($request->user()->tipo !== 'artista') {
            abort(403, 'Solo los artistas pueden acceder a este recurso.');
        }
    }

    // ── Perfil del artista ───────────────────────────────────────────────────

    public function miPerfil(Request $request)
    {
        $this->soloArtista($request);
        $user   = $request->user()->load('artistaPerfil');
        $perfil = $user->artistaPerfil;

        return response()->json([
            'artista' => $this->formatearArtista($user, $perfil),
        ]);
    }

    public function actualizarPerfil(Request $request)
    {
        $this->soloArtista($request);
        $user = $request->user();

        $datos = $request->validate([
            'nombre_artista'  => ['sometimes', 'string', 'max:100'],
            'bio'             => ['sometimes', 'nullable', 'string', 'max:1000'],
            'genero'          => ['sometimes', 'nullable', 'string', 'max:80'],
            'ciudad'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'sitio_web'       => ['sometimes', 'nullable', 'url', 'max:255'],
            'imagen_portada'  => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if (isset($datos['nombre_artista'])) {
            $user->update(['nombre_artista' => $datos['nombre_artista']]);
            unset($datos['nombre_artista']);
        }

        ArtistaPerfil::updateOrCreate(
            ['user_id' => $user->id],
            $datos
        );

        $user->refresh()->load('artistaPerfil');
        return response()->json([
            'artista' => $this->formatearArtista($user, $user->artistaPerfil),
        ]);
    }

    // ── Canciones ────────────────────────────────────────────────────────────

    public function canciones(Request $request)
    {
        $this->soloArtista($request);
        $canciones = CancionArtista::where('artista_id', $request->user()->id)
            ->with('album')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['canciones' => $canciones]);
    }

    public function crearCancion(Request $request)
    {
        $this->soloArtista($request);

        $artistaId = $request->user()->id;
        $request->validate([
            'titulo'    => ['required', 'string', 'max:200'],
            'duracion'  => ['sometimes', 'string', 'max:10'],
            'imagen'    => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'audio'     => ['sometimes', 'nullable', 'file', 'mimes:mp3,ogg,wav,m4a,aac,flac', 'max:102400'],
            'genero'    => ['sometimes', 'nullable', 'string', 'max:80'],
            'album_id'  => ['sometimes', 'nullable', 'integer', Rule::exists('albumes_artista', 'id')->where('artista_id', $artistaId)],
        ]);

        $datos = [
            'artista_id' => $request->user()->id,
            'titulo'     => $request->titulo,
            'duracion'   => $request->duracion ?? '0:00',
            'genero'     => $request->genero,
            'album_id'   => $request->album_id ?: null,
        ];

        if ($request->hasFile('imagen')) {
            $datos['imagen'] = url('storage/' . $request->file('imagen')->store('artistas/imagenes', 'public'));
        }

        if ($request->hasFile('audio')) {
            $datos['audio_url'] = url('storage/' . $request->file('audio')->store('artistas/audios', 'public'));
        }

        $cancion = CancionArtista::create($datos);
        return response()->json(['cancion' => $cancion->load('album')], 201);
    }

    public function actualizarCancion(Request $request, int $id)
    {
        $this->soloArtista($request);
        $cancion = CancionArtista::where('artista_id', $request->user()->id)->findOrFail($id);

        // Normalizar album_id: cadena vacía significa "sin álbum" (null)
        if ($request->has('album_id') && $request->album_id === '') {
            $request->merge(['album_id' => null]);
        }

        $artistaId = $request->user()->id;
        $request->validate([
            'titulo'    => ['sometimes', 'string', 'max:200'],
            'duracion'  => ['sometimes', 'string', 'max:10'],
            'imagen'    => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'audio'     => ['sometimes', 'nullable', 'file', 'mimes:mp3,ogg,wav,m4a,aac,flac', 'max:102400'],
            'genero'    => ['sometimes', 'nullable', 'string', 'max:80'],
            'album_id'  => ['sometimes', 'nullable', 'integer', Rule::exists('albumes_artista', 'id')->where('artista_id', $artistaId)],
            'activa'    => ['sometimes', 'boolean'],
        ]);

        $datos = [];
        if ($request->has('titulo'))    $datos['titulo']    = $request->titulo;
        if ($request->has('duracion'))  $datos['duracion']  = $request->duracion;
        if ($request->has('genero'))    $datos['genero']    = $request->genero ?: null;
        if ($request->has('album_id'))  $datos['album_id']  = $request->album_id ?: null;
        if ($request->has('activa'))    $datos['activa']    = $request->boolean('activa');

        if ($request->hasFile('imagen')) {
            if ($cancion->imagen) $this->borrarArchivo($cancion->imagen);
            $datos['imagen'] = url('storage/' . $request->file('imagen')->store('artistas/imagenes', 'public'));
        }

        if ($request->hasFile('audio')) {
            if ($cancion->audio_url) $this->borrarArchivo($cancion->audio_url);
            $datos['audio_url'] = url('storage/' . $request->file('audio')->store('artistas/audios', 'public'));
        }

        $cancion->update($datos);
        return response()->json(['cancion' => $cancion->load('album')]);
    }

    public function eliminarCancion(Request $request, int $id)
    {
        $this->soloArtista($request);
        $cancion = CancionArtista::where('artista_id', $request->user()->id)->findOrFail($id);
        if ($cancion->imagen)   $this->borrarArchivo($cancion->imagen);
        if ($cancion->audio_url) $this->borrarArchivo($cancion->audio_url);
        $cancion->delete();
        return response()->json(['ok' => true]);
    }

    // ── Álbumes ──────────────────────────────────────────────────────────────

    public function albumes(Request $request)
    {
        $this->soloArtista($request);
        $albumes = AlbumArtista::where('artista_id', $request->user()->id)
            ->withCount('canciones')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['albumes' => $albumes]);
    }

    public function crearAlbum(Request $request)
    {
        $this->soloArtista($request);

        $request->validate([
            'titulo'       => ['required', 'string', 'max:200'],
            'imagen'       => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'genero'       => ['sometimes', 'nullable', 'string', 'max:80'],
            'descripcion'  => ['sometimes', 'nullable', 'string', 'max:1000'],
            'publicado_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $datos = [
            'artista_id'   => $request->user()->id,
            'titulo'       => $request->titulo,
            'genero'       => $request->genero,
            'descripcion'  => $request->descripcion,
            'publicado_at' => $request->publicado_at,
        ];

        if ($request->hasFile('imagen')) {
            $datos['imagen'] = url('storage/' . $request->file('imagen')->store('artistas/imagenes', 'public'));
        }

        $album = AlbumArtista::create($datos);
        return response()->json(['album' => $album->loadCount('canciones')], 201);
    }

    public function actualizarAlbum(Request $request, int $id)
    {
        $this->soloArtista($request);
        $album = AlbumArtista::where('artista_id', $request->user()->id)->findOrFail($id);

        // Normalizar campos opcionales: cadena vacía equivale a null
        foreach (['genero', 'descripcion', 'publicado_at'] as $campo) {
            if ($request->has($campo) && $request->$campo === '') {
                $request->merge([$campo => null]);
            }
        }

        $request->validate([
            'titulo'       => ['sometimes', 'string', 'max:200'],
            'imagen'       => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'genero'       => ['sometimes', 'nullable', 'string', 'max:80'],
            'descripcion'  => ['sometimes', 'nullable', 'string', 'max:1000'],
            'publicado_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $datos = [];
        if ($request->has('titulo'))       $datos['titulo']       = $request->titulo;
        if ($request->has('genero'))       $datos['genero']       = $request->genero ?: null;
        if ($request->has('descripcion'))  $datos['descripcion']  = $request->descripcion ?: null;
        if ($request->has('publicado_at')) $datos['publicado_at'] = $request->publicado_at ?: null;

        if ($request->hasFile('imagen')) {
            if ($album->imagen) $this->borrarArchivo($album->imagen);
            $datos['imagen'] = url('storage/' . $request->file('imagen')->store('artistas/imagenes', 'public'));
        }

        $album->update($datos);
        return response()->json(['album' => $album->loadCount('canciones')]);
    }

    public function eliminarAlbum(Request $request, int $id)
    {
        $this->soloArtista($request);
        $album = AlbumArtista::where('artista_id', $request->user()->id)->findOrFail($id);
        if ($album->imagen) $this->borrarArchivo($album->imagen);
        $album->delete();
        return response()->json(['ok' => true]);
    }

    // ── Eventos ──────────────────────────────────────────────────────────────

    public function misEventos(Request $request)
    {
        $this->soloArtista($request);
        $eventos = Evento::where('artista_user_id', $request->user()->id)
            ->orderByDesc('fecha')
            ->get();
        return response()->json(['eventos' => $eventos]);
    }

    public function crearEvento(Request $request)
    {
        $this->soloArtista($request);
        $user = $request->user();

        $inicioSemana = now()->startOfWeek(\Carbon\Carbon::MONDAY)->format('Y-m-d');

        $request->validate([
            'nombre'         => ['required', 'string', 'max:200'],
            'descripcion'    => ['nullable', 'string', 'max:1000'],
            'fecha'          => ['required', 'date', "after_or_equal:{$inicioSemana}"],
            'lugar'          => ['required', 'string', 'max:200'],
            'ciudad'         => ['required', 'string', 'max:100'],
            'precio_pista'   => ['required', 'numeric', 'min:0'],
            'aforo_pista'    => ['required', 'integer', 'min:1'],
            'precio_tribuna' => ['required', 'numeric', 'min:0'],
            'aforo_tribuna'  => ['required', 'integer', 'min:1'],
            'precio_vip'     => ['required', 'numeric', 'min:0'],
            'aforo_vip'      => ['required', 'integer', 'min:1'],
            'imagen'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'nombre.required'         => 'El nombre del evento es obligatorio.',
            'fecha.required'          => 'La fecha del evento es obligatoria.',
            'fecha.date'              => 'El formato de fecha no es válido.',
            'fecha.after_or_equal'    => 'La fecha no puede ser anterior a la semana actual.',
            'lugar.required'          => 'El lugar del evento es obligatorio.',
            'ciudad.required'         => 'La ciudad es obligatoria.',
            'precio_pista.required'   => 'El precio de Pista es obligatorio.',
            'precio_pista.numeric'    => 'El precio de Pista debe ser un número.',
            'aforo_pista.required'    => 'El aforo de Pista es obligatorio.',
            'aforo_pista.min'         => 'El aforo de Pista debe ser al menos 1.',
            'precio_tribuna.required' => 'El precio de Tribuna es obligatorio.',
            'precio_tribuna.numeric'  => 'El precio de Tribuna debe ser un número.',
            'aforo_tribuna.required'  => 'El aforo de Tribuna es obligatorio.',
            'aforo_tribuna.min'       => 'El aforo de Tribuna debe ser al menos 1.',
            'precio_vip.required'     => 'El precio de VIP es obligatorio.',
            'precio_vip.numeric'      => 'El precio de VIP debe ser un número.',
            'aforo_vip.required'      => 'El aforo de VIP es obligatorio.',
            'aforo_vip.min'           => 'El aforo de VIP debe ser al menos 1.',
            'imagen.image'            => 'El archivo debe ser una imagen.',
            'imagen.mimes'            => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max'              => 'La imagen no puede superar los 5 MB.',
        ]);

        $tipos = [
            ['tipo' => 'Pista',   'precio' => (float) $request->precio_pista,   'aforo' => (int) $request->aforo_pista],
            ['tipo' => 'Tribuna', 'precio' => (float) $request->precio_tribuna, 'aforo' => (int) $request->aforo_tribuna],
            ['tipo' => 'VIP',     'precio' => (float) $request->precio_vip,     'aforo' => (int) $request->aforo_vip],
        ];

        $datos = [
            'nombre'           => $request->nombre,
            'descripcion'      => $request->descripcion,
            'fecha'            => $request->fecha,
            'lugar'            => $request->lugar,
            'ciudad'           => $request->ciudad,
            'tipos_entrada'    => $tipos,
            'precio'           => min($request->precio_pista, $request->precio_tribuna, $request->precio_vip),
            'aforo'            => (int)$request->aforo_pista + (int)$request->aforo_tribuna + (int)$request->aforo_vip,
            'artista'          => $user->nombre_artista ?? $user->nombre,
            'artista_user_id'  => $user->id,
            'nuevo_talento'    => false,
            'entradas_vendidas'=> 0,
        ];

        if ($request->hasFile('imagen')) {
            $datos['imagen'] = url('storage/' . $request->file('imagen')->store('eventos/imagenes', 'public'));
        } else {
            $datos['imagen'] = $user->url_avatar;
        }

        $evento = Evento::create($datos);
        return response()->json(['evento' => $evento], 201);
    }

    public function actualizarEvento(Request $request, int $id)
    {
        $this->soloArtista($request);
        $evento = Evento::where('artista_user_id', $request->user()->id)->findOrFail($id);

        $inicioSemana = now()->startOfWeek(\Carbon\Carbon::MONDAY)->format('Y-m-d');

        $request->validate([
            'nombre'         => ['sometimes', 'string', 'max:200'],
            'descripcion'    => ['nullable', 'string', 'max:1000'],
            'fecha'          => ['sometimes', 'date', "after_or_equal:{$inicioSemana}"],
            'lugar'          => ['sometimes', 'string', 'max:200'],
            'ciudad'         => ['sometimes', 'string', 'max:100'],
            'precio_pista'   => ['sometimes', 'numeric', 'min:0'],
            'aforo_pista'    => ['sometimes', 'integer', 'min:1'],
            'precio_tribuna' => ['sometimes', 'numeric', 'min:0'],
            'aforo_tribuna'  => ['sometimes', 'integer', 'min:1'],
            'precio_vip'     => ['sometimes', 'numeric', 'min:0'],
            'aforo_vip'      => ['sometimes', 'integer', 'min:1'],
            'imagen'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'fecha.after_or_equal' => 'La fecha no puede ser anterior a la semana actual.',
            'fecha.date'           => 'El formato de fecha no es válido.',
            'imagen.image'         => 'El archivo debe ser una imagen.',
            'imagen.mimes'         => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max'           => 'La imagen no puede superar los 5 MB.',
        ]);

        $datos = array_filter([
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'fecha'       => $request->fecha,
            'lugar'       => $request->lugar,
            'ciudad'      => $request->ciudad,
        ], fn($v) => $v !== null);

        // Recalcular tipos si se envía algún precio/aforo
        if ($request->has('precio_pista')) {
            $tipos = [
                ['tipo' => 'Pista',   'precio' => (float) $request->precio_pista,   'aforo' => (int) $request->aforo_pista],
                ['tipo' => 'Tribuna', 'precio' => (float) $request->precio_tribuna, 'aforo' => (int) $request->aforo_tribuna],
                ['tipo' => 'VIP',     'precio' => (float) $request->precio_vip,     'aforo' => (int) $request->aforo_vip],
            ];
            $datos['tipos_entrada'] = $tipos;
            $datos['precio']        = min($request->precio_pista, $request->precio_tribuna, $request->precio_vip);
            $datos['aforo']         = (int)$request->aforo_pista + (int)$request->aforo_tribuna + (int)$request->aforo_vip;
        }

        if ($request->hasFile('imagen')) {
            if ($evento->imagen) $this->borrarArchivo($evento->imagen);
            $datos['imagen'] = url('storage/' . $request->file('imagen')->store('eventos/imagenes', 'public'));
        }

        $evento->update($datos);
        return response()->json(['evento' => $evento]);
    }

    public function eliminarEvento(Request $request, int $id)
    {
        $this->soloArtista($request);
        $evento = Evento::where('artista_user_id', $request->user()->id)->findOrFail($id);
        if ($evento->imagen && str_contains($evento->imagen, 'storage/eventos')) {
            $this->borrarArchivo($evento->imagen);
        }
        $evento->delete();
        return response()->json(['ok' => true]);
    }

    // ── Estadísticas ─────────────────────────────────────────────────────────

    public function estadisticas(Request $request)
    {
        $this->soloArtista($request);
        $userId = $request->user()->id;

        $totalReproducciones = CancionArtista::where('artista_id', $userId)->sum('reproducciones');
        $totalSeguidores     = \App\Models\SeguidorArtista::where('artista_id', $userId)->count();
        $totalCanciones      = CancionArtista::where('artista_id', $userId)->where('activa', true)->count();
        $totalAlbumes        = AlbumArtista::where('artista_id', $userId)->count();

        $topCanciones = CancionArtista::where('artista_id', $userId)
            ->where('activa', true)
            ->orderByDesc('reproducciones')
            ->limit(5)
            ->get();

        return response()->json([
            'total_reproducciones' => $totalReproducciones,
            'total_seguidores'     => $totalSeguidores,
            'total_canciones'      => $totalCanciones,
            'total_albumes'        => $totalAlbumes,
            'top_canciones'        => $topCanciones,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function borrarArchivo(?string $url): void
    {
        if (!$url) return;
        // Convertir URL absoluta a ruta relativa en storage/public
        $ruta = Str::after($url, url('storage/'));
        if ($ruta && Storage::disk('public')->exists($ruta)) {
            Storage::disk('public')->delete($ruta);
        }
    }

    private function formatearArtista($user, $perfil): array
    {
        return [
            'id'              => $user->id,
            'nombre'          => $user->nombre,
            'nombre_artista'  => $user->nombre_artista ?? $user->nombre,
            'nombre_usuario'  => $user->nombre_usuario,
            'email'           => $user->email,
            'tipo'            => $user->tipo,
            'avatar'          => $user->url_avatar,
            'bio'             => $perfil?->bio,
            'genero'          => $perfil?->genero,
            'ciudad'          => $perfil?->ciudad,
            'sitio_web'       => $perfil?->sitio_web,
            'imagen_portada'  => $perfil?->imagen_portada,
        ];
    }
}
