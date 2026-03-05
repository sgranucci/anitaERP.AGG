<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Seteosalida extends Model
{
    protected $fillable = ['usuario_id', 'salida_id', 'programa'];
    protected $table = 'seteosalida';
    protected $keyField = 'id';

    public function salidas()
    {
        return $this->belongsTo(Salida::class, 'salida_id');
    }

}
