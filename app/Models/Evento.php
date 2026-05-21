<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'artista',
        'imagen',
        'fecha',
        'lugar',
        'ciudad',
        'precio',
        'aforo',
        'entradas_vendidas',
        'nuevo_talento',
        'artista_user_id',
    ];

    protected $casts = [
        'fecha'         => 'datetime',
        'precio'        => 'decimal:2',
        'tipos_entrada' => 'array',
    ];

    // Serializa la fecha sin offset de zona horaria para que el navegador
    // la interprete siempre como hora local, no UTC.
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s');
    }

    public function entradas()
    {
        return $this->hasMany(Entrada::class);
    }
}
