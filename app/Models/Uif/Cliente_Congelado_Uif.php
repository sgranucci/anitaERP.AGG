<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

class Cliente_Congelado_Uif extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'numerodocumento', 'resolucion', 'fechacaducidad', 'usuario_id'];
    protected $table = 'cliente_congelado_uif';
}
