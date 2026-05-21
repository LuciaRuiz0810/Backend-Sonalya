<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlbumFavorito extends Model
{
    use HasFactory;

    protected $table = 'albumes_favoritos';

    protected $fillable = [
        'user_id',
        'deezer_id',
        'nombre',
        'artista',
        'imagen',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
