<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;

class Cliente_Congelado_Uif extends Model
{
    protected $fillable = ['nombre', 'numerodocumento', 'resolucion', 'fechacaducidad', 'usuario_id'];
    protected $table = 'cliente_congelado_uif';
}
