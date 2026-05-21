<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlaylistCancion extends Model
{
    use HasFactory;

    protected $table = 'playlist_canciones';

    protected $fillable = [
        'playlist_id',
        'deezer_id',
        'titulo',
        'artista',
        'imagen',
        'duracion',
        'preview',
        'orden',
    ];

    public function playlist()
    {
        return $this->belongsTo(Playlist::class);
    }
}
