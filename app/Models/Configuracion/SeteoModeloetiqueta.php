<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeteoModeloetiqueta extends Model
{
    protected $fillable = ['usuario_id', 'modeloetiqueta_id', 'programa'];
    protected $table = 'seteomodeloetiqueta';
    protected $keyField = 'id';

    public function modeloetiquetas()
    {
        return $this->belongsTo(Modeloetiqueta::class, 'modeloetiqueta_id');
    }

}
