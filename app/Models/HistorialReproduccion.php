<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialReproduccion extends Model
{
    use HasFactory;

    protected $table = 'historial_reproducciones';

    public $timestamps = false;

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'deezer_id',
        'tipo',
        'titulo',
        'artista',
        'imagen',
        'preview',
    ];

    protected $casts = [
        'reproducido_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
