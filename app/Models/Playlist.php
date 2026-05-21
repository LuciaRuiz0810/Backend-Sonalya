<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Playlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'imagen',
        'publica',
    ];

    protected $casts = [
        'publica' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function canciones()
    {
        return $this->hasMany(PlaylistCancion::class);
    }
}
