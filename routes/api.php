<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ArtistaPublicoController;
use App\Http\Controllers\Api\ArtistaCuentaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventoController;
use App\Http\Controllers\Api\FavoritosController;
use App\Http\Controllers\Api\HistorialController;
use App\Http\Controllers\Api\MusicaController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\RecomendacionController;
use Illuminate\Support\Facades\Route;

// Rutas públicas de música (proxy a Deezer)
Route::prefix('musica')->group(function () {
    Route::get('charts',       [MusicaController::class, 'charts']);
    Route::get('generos',      [MusicaController::class, 'generos']);
    Route::get('buscar',       [MusicaController::class, 'buscar']);
    Route::get('genero/{id}',   [MusicaController::class, 'porGenero']);
    Route::get('artista/{id}',  [MusicaController::class, 'artista']);
    Route::get('album/{id}',    [MusicaController::class, 'album']);
    Route::get('emergentes',    [MusicaController::class, 'emergentes']);
});

// Búsqueda de contenido de la plataforma
Route::get('musica/buscar-plataforma', [ArtistaPublicoController::class, 'buscarPlataforma']);

// Artistas de la plataforma (públicos)
Route::get('artistas-plataforma',                     [ArtistaPublicoController::class, 'listar']);
Route::get('artistas-plataforma/album/{albumId}',     [ArtistaPublicoController::class, 'cancionesAlbum']);
Route::get('artistas-plataforma/{id}',                [ArtistaPublicoController::class, 'perfil']);
Route::post('artistas-plataforma/{id}/reproducir/{cancionId}', [ArtistaPublicoController::class, 'reproducir']);

// Eventos públicos (lectura sin autenticación)
Route::get('eventos',                [EventoController::class, 'index']);
Route::get('eventos/nuevos-talentos', [EventoController::class, 'eventosTalentos']);

// Rutas públicas
Route::prefix('auth')->group(function () {
    Route::post('registro',            [AuthController::class, 'registro']);
    Route::post('login',               [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('recuperar-password',  [AuthController::class, 'recuperarPassword']);
    Route::post('resetear-password',   [AuthController::class, 'resetearPassword']);
});

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::prefix('usuario')->group(function () {
        Route::get('perfil',                          [AuthController::class, 'perfil']);
        Route::match(['put', 'post'], 'perfil',       [AuthController::class, 'actualizarPerfil']);
        Route::put('cambiar-password',                [AuthController::class, 'cambiarPassword']);
    });

    // Playlists
    Route::prefix('playlists')->group(function () {
        Route::get('/',                              [PlaylistController::class, 'index']);
        Route::post('/',                             [PlaylistController::class, 'store']);
        Route::get('/{id}',                          [PlaylistController::class, 'show']);
        Route::put('/{id}',                          [PlaylistController::class, 'update']);
        Route::post('/{id}/imagen',                  [PlaylistController::class, 'subirImagen']);
        Route::delete('/{id}',                       [PlaylistController::class, 'destroy']);
        Route::post('/{id}/canciones',               [PlaylistController::class, 'agregarCancion']);
        Route::delete('/{id}/canciones/{cid}',       [PlaylistController::class, 'quitarCancion']);
    });

    // Favoritos
    Route::prefix('favoritos')->group(function () {
        Route::get('/canciones',                     [FavoritosController::class, 'canciones']);
        Route::post('/canciones',                    [FavoritosController::class, 'agregarCancion']);
        Route::delete('/canciones/{deezerId}',       [FavoritosController::class, 'quitarCancion']);
        Route::get('/albumes',                       [FavoritosController::class, 'albumes']);
        Route::post('/albumes',                      [FavoritosController::class, 'agregarAlbum']);
        Route::delete('/albumes/{deezerId}',         [FavoritosController::class, 'quitarAlbum']);
        Route::get('/artistas',                      [FavoritosController::class, 'artistas']);
        Route::post('/artistas',                     [FavoritosController::class, 'seguirArtista']);
        Route::delete('/artistas/{deezerId}',        [FavoritosController::class, 'dejarSeguirArtista']);
        Route::get('/estado/{tipo}/{deezerId}',      [FavoritosController::class, 'estadoFavorito']);
    });

    // Historial
    Route::get('/historial',           [HistorialController::class, 'index']);
    Route::get('/historial/populares', [HistorialController::class, 'populares']);
    Route::post('/historial',          [HistorialController::class, 'registrar']);

    // Recomendaciones personalizadas
    Route::get('/recomendaciones',     [RecomendacionController::class, 'index']);

    // Seguimiento de artistas de la plataforma
    Route::post('artistas-plataforma/{id}/seguir',         [ArtistaPublicoController::class, 'seguir']);
    Route::delete('artistas-plataforma/{id}/seguir',       [ArtistaPublicoController::class, 'dejarSeguir']);
    Route::get('artistas-plataforma/{id}/estado-seguimiento', [ArtistaPublicoController::class, 'estadoSeguimiento']);

    // Gestión propia del artista (solo tipo='artista')
    Route::prefix('artista-cuenta')->group(function () {
        Route::get('perfil',                      [ArtistaCuentaController::class, 'miPerfil']);
        Route::put('perfil',                      [ArtistaCuentaController::class, 'actualizarPerfil']);
        Route::get('estadisticas',                [ArtistaCuentaController::class, 'estadisticas']);
        Route::get('canciones',                              [ArtistaCuentaController::class, 'canciones']);
        Route::post('canciones',                             [ArtistaCuentaController::class, 'crearCancion']);
        Route::match(['put','post'], 'canciones/{id}',      [ArtistaCuentaController::class, 'actualizarCancion']);
        Route::delete('canciones/{id}',                     [ArtistaCuentaController::class, 'eliminarCancion']);
        Route::get('albumes',                               [ArtistaCuentaController::class, 'albumes']);
        Route::post('albumes',                              [ArtistaCuentaController::class, 'crearAlbum']);
        Route::match(['put','post'], 'albumes/{id}',        [ArtistaCuentaController::class, 'actualizarAlbum']);
        Route::delete('albumes/{id}',                       [ArtistaCuentaController::class, 'eliminarAlbum']);
        Route::get('eventos',                               [ArtistaCuentaController::class, 'misEventos']);
        Route::post('eventos',                              [ArtistaCuentaController::class, 'crearEvento']);
        Route::match(['put','post'], 'eventos/{id}',        [ArtistaCuentaController::class, 'actualizarEvento']);
        Route::delete('eventos/{id}',                       [ArtistaCuentaController::class, 'eliminarEvento']);
    });

    // Eventos (protegidos)
    Route::get('/eventos',                    [EventoController::class, 'index']);
    Route::get('/eventos/seguidos',           [EventoController::class, 'eventosSeguidos']);
    Route::post('/eventos/{id}/comprar',      [EventoController::class, 'comprar']);
    Route::get('/mis-entradas',               [EventoController::class, 'misEntradas']);

    // Panel de administración
    Route::prefix('admin')->group(function () {
        Route::get('estadisticas',         [AdminController::class, 'estadisticas']);

        Route::get('usuarios',             [AdminController::class, 'usuarios']);
        Route::post('usuarios',            [AdminController::class, 'crearUsuario']);
        Route::put('usuarios/{id}',        [AdminController::class, 'actualizarUsuario']);
        Route::delete('usuarios/{id}',     [AdminController::class, 'eliminarUsuario']);

        Route::get('canciones',            [AdminController::class, 'canciones']);
        Route::post('canciones',           [AdminController::class, 'crearCancion']);
        Route::put('canciones/{id}',       [AdminController::class, 'actualizarCancion']);
        Route::delete('canciones/{id}',    [AdminController::class, 'eliminarCancion']);

        Route::get('albumes',              [AdminController::class, 'albumes']);
        Route::post('albumes',             [AdminController::class, 'crearAlbum']);
        Route::delete('albumes/{id}',      [AdminController::class, 'eliminarAlbum']);

        Route::get('eventos',              [AdminController::class, 'eventos']);
        Route::post('eventos',             [AdminController::class, 'crearEvento']);
        Route::put('eventos/{id}',         [AdminController::class, 'actualizarEvento']);
        Route::delete('eventos/{id}',      [AdminController::class, 'eliminarEvento']);
    });
});
