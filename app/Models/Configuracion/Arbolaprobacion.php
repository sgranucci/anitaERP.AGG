<?php

namespace App\Models\Configuracion;

use App\Traits\Configuracion\ArbolaprobacionTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Arbolaprobacion extends Model implements Auditable
{
    protected $fillable = [
        'nombre', 'tipoarbol', 'empresa_id', 'recordatorio', 'diasinrespuesta', 'diavencimientorecordatorio', 'estado',
        'oc_disparar_arbol_al_alta', 'oc_sector_cambio_centrocosto_id', 'oc_sector_disparo_aprobacion_id', 'oc_sector_destino_aprobacion_id',
    ];

    protected $table = 'arbolaprobacion';

    use ArbolaprobacionTrait;
    use \OwenIt\Auditing\Auditable;

    public function arbolaprobacion_niveles()
    {
        $rel = $this->hasMany(Arbolaprobacion_Nivel::class, 'arbolaprobacion_id')
            ->with('usuarios')->with('moneda_ids')->with('centrocosto_ids')
            ->orderBy('centrocosto_id');

        if (\Illuminate\Support\Facades\Schema::hasColumn('arbolaprobacion_nivel', 'rama')) {
            $rel->orderByRaw("CASE WHEN rama = 'A' THEN 1 WHEN rama = 'B' THEN 2 ELSE 3 END");
        }

        return $rel
            ->orderBy('nivel')
            ->orderBy('desdemonto')
            ->orderBy('id');
    }

    public function oc_triggers()
    {
        return $this->hasMany(Arbolaprobacion_OcTrigger::class, 'arbolaprobacion_id')
            ->orderBy('prioridad')
            ->orderBy('id');
    }

    public function cuenta_excepciones()
    {
        return $this->hasMany(Arbolaprobacion_CuentaExcepcion::class, 'arbolaprobacion_id')
            ->orderBy('centrocosto_id')
            ->orderBy('id');
    }

    public function re_triggers()
    {
        return $this->hasMany(Arbolaprobacion_ReTrigger::class, 'arbolaprobacion_id')
            ->orderBy('prioridad')
            ->orderBy('id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
