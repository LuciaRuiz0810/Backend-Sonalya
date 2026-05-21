<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancionFavorita extends Model
{
    use HasFactory;

    protected $table = 'canciones_favoritas';

    protected $fillable = [
        'user_id',
        'deezer_id',
        'titulo',
        'artista',
        'imagen',
        'duracion',
        'preview',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
