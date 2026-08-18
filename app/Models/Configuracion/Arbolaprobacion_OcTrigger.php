<?php

namespace App\Models\Configuracion;

use App\Models\Compras\Sector_Legajocompra;
use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Arbolaprobacion_OcTrigger extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'arbolaprobacion_oc_trigger';

    protected $fillable = [
        'arbolaprobacion_id', 'nombre', 'tipo', 'evento', 'evaluador',
        'sector_origen_id', 'sector_destino_id', 'centrocosto_circuito_id',
        'documento_estado_al_aprobar', 'accion_final', 'accion_final_sector_id', 'accion_final_estado',
        'prioridad', 'anula_auto_aprobacion', 'reevaluar_en_actualizacion', 'activo',
    ];

    public function arbolaprobaciones()
    {
        return $this->belongsTo(Arbolaprobacion::class, 'arbolaprobacion_id');
    }

    public function sector_origen()
    {
        return $this->belongsTo(Sector_Legajocompra::class, 'sector_origen_id');
    }

    public function sector_destino()
    {
        return $this->belongsTo(Sector_Legajocompra::class, 'sector_destino_id');
    }

    public function centrocosto_circuito()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_circuito_id');
    }

    public function accion_final_sector()
    {
        return $this->belongsTo(Sector_Legajocompra::class, 'accion_final_sector_id');
    }

    public function estaActivo(): bool
    {
        return strtoupper((string) ($this->activo ?? 'N')) === 'S';
    }

    public function anulaAutoAprobacion(): bool
    {
        return strtoupper((string) ($this->anula_auto_aprobacion ?? 'N')) === 'S';
    }
}
