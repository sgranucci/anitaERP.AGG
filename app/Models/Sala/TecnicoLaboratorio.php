<?php

namespace App\Models\Sala;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TecnicoLaboratorio extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
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
