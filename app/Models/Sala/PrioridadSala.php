<?php

namespace App\Models\Sala;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;

class PrioridadSala extends Model
{
    protected $fillable = ['nombre', 'empresa_id'];

    protected $table = 'prioridad_sala';

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id', 'id');
    }
}
