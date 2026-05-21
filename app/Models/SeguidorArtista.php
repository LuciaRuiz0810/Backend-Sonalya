<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeguidorArtista extends Model
{
    protected $table = 'seguidores_artista';
    public $timestamps = false;

    protected $fillable = ['artista_id', 'seguidor_id'];
}
