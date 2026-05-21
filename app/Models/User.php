<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nombre',
        'nombre_artista',
        'nombre_usuario',
        'email',
        'tipo',
        'password',
        'avatar',
        'biografia',
        'intentos_login',
        'bloqueado_hasta',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'intentos_login',
        'bloqueado_hasta',
    ];

    protected $appends = ['url_avatar'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'bloqueado_hasta'   => 'datetime',
            'password'          => 'hashed',
        ];
    }

    protected function urlAvatar(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->avatar ? url('storage/' . $this->avatar) : null,
        );
    }

    public function isLocked(): bool
    {
        return $this->bloqueado_hasta && $this->bloqueado_hasta->isFuture();
    }

    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }

    public function cancionesFavoritas()
    {
        return $this->hasMany(CancionFavorita::class);
    }

    public function albumesFavoritos()
    {
        return $this->hasMany(AlbumFavorito::class);
    }

    public function artistasSeguidos()
    {
        return $this->hasMany(ArtistaSeguido::class);
    }

    public function historialReproducciones()
    {
        return $this->hasMany(HistorialReproduccion::class);
    }

    public function entradas()
    {
        return $this->hasMany(Entrada::class);
    }

    public function artistaPerfil()
    {
        return $this->hasOne(ArtistaPerfil::class);
    }

    public function cancionesArtista()
    {
        return $this->hasMany(CancionArtista::class, 'artista_id');
    }

    public function albumesArtista()
    {
        return $this->hasMany(AlbumArtista::class, 'artista_id');
    }

    public function seguidores()
    {
        return $this->hasMany(SeguidorArtista::class, 'artista_id');
    }

    public function siguiendo()
    {
        return $this->hasMany(SeguidorArtista::class, 'seguidor_id');
    }

    public function esArtista(): bool
    {
        return $this->tipo === 'artista';
    }

    public function esAdmin(): bool
    {
        return $this->tipo === 'admin';
    }
}
