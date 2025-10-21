<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use App\Traits\Configuracion\ArbolaprobacionTrait;

class Arbolaprobacion extends Model implements Auditable
{
    protected $fillable = ['nombre', 'tipoarbol', 'empresa_id', 'recordatorio', 'diasinrespuesta', 'diavencimientorecordatorio', 'estado'];
    protected $table = 'arbolaprobacion';
    use \OwenIt\Auditing\Auditable;
    use ArbolaprobacionTrait;

	public function arbolaprobacion_niveles()
	{
    	return $this->hasMany(Arbolaprobacion_Nivel::class, 'arbolaprobacion_id')->with('usuarios')->with('moneda_ids')->with('centrocosto_ids');
	}

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
