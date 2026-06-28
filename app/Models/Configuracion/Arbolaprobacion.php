<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use App\Traits\Configuracion\ArbolaprobacionTrait;

class Arbolaprobacion extends Model implements Auditable
{
    protected $fillable = [
        'nombre', 'tipoarbol', 'empresa_id', 'recordatorio', 'diasinrespuesta', 'diavencimientorecordatorio', 'estado',
        'oc_disparar_arbol_al_alta', 'oc_sector_cambio_centrocosto_id', 'oc_sector_disparo_aprobacion_id', 'oc_sector_destino_aprobacion_id',
    ];
    protected $table = 'arbolaprobacion';
    use \OwenIt\Auditing\Auditable;
    use ArbolaprobacionTrait;

	public function arbolaprobacion_niveles()
	{
    	return $this->hasMany(Arbolaprobacion_Nivel::class, 'arbolaprobacion_id')->with('usuarios')->with('moneda_ids')->with('centrocosto_ids');
	}

    public function oc_triggers()
    {
        return $this->hasMany(Arbolaprobacion_OcTrigger::class, 'arbolaprobacion_id')
            ->whereNull('deleted_at')
            ->orderBy('prioridad')
            ->orderBy('id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
