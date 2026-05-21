<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlbumArtista extends Model
{
    protected $table = 'albumes_artista';

    protected $fillable = [
        'artista_id', 'titulo', 'imagen', 'genero', 'descripcion', 'publicado_at',
    ];

    protected $appends = ['canciones_count'];

    public function artista()
    {
        return $this->belongsTo(User::class, 'artista_id');
    }

    public function canciones()
    {
        return $this->hasMany(CancionArtista::class, 'album_id')->orderBy('created_at');
    }

    public function getCancionesCountAttribute(): int
    {
        return $this->canciones()->count();
    }
}
