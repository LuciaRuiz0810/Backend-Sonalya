<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtistaPerfil extends Model
{
    protected $table = 'artista_perfiles';

    protected $fillable = [
        'user_id', 'bio', 'genero', 'ciudad', 'sitio_web', 'imagen_portada',
    ];

    public function artista()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
