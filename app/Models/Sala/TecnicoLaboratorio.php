<?php

namespace App\Models\Sala;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;

class TecnicoLaboratorio extends Model
{
    protected $table = 'tecnico_laboratorio';

    protected $fillable = ['nombre', 'legajo', 'activo', 'empresa_id'];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id', 'id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', 'S');
    }
}
