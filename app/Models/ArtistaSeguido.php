<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArtistaSeguido extends Model
{
    use HasFactory;

    protected $table = 'artistas_seguidos';

    protected $fillable = [
        'user_id',
        'deezer_id',
        'nombre',
        'imagen',
        'descripcion',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
