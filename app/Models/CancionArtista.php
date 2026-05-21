<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancionArtista extends Model
{
    protected $table = 'canciones_artista';

    protected $fillable = [
        'artista_id', 'album_id', 'titulo', 'duracion', 'imagen', 'audio_url', 'genero', 'reproducciones', 'activa',
    ];

    protected $casts = ['activa' => 'boolean'];

    public function artista()
    {
        return $this->belongsTo(User::class, 'artista_id');
    }

    public function album()
    {
        return $this->belongsTo(AlbumArtista::class, 'album_id');
    }

    public function toFormatoReproductor(): array
    {
        return [
            'id'        => 'plataforma_' . $this->id,
            'titulo'    => $this->titulo,
            'nombre'    => $this->titulo,
            'artista'   => $this->artista?->nombre_artista ?? $this->artista?->nombre ?? '',
            'artista_id'=> 'plataforma_' . $this->artista_id,
            'album'     => $this->album?->titulo ?? null,
            'imagen'    => $this->imagen,
            'preview'   => $this->audio_url,
            'duracion'  => $this->duracion,
            'tipo'      => 'cancion',
        ];
    }
}
