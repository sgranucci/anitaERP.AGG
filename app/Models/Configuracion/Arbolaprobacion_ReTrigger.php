<?php

namespace App\Models\Configuracion;

use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Arbolaprobacion_ReTrigger extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'arbolaprobacion_re_trigger';

    protected $fillable = [
        'arbolaprobacion_id',
        'nombre',
        'tipo',
        'evaluador',
        'centrocosto_id',
        'accion_rama',
        'param_monto',
        'param_moneda_id',
        'param_cuentacontable_id',
        'vigencia_desde',
        'vigencia_hasta',
        'observacion',
        'prioridad',
        'activo',
    ];

    protected $casts = [
        'param_monto' => 'float',
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
    ];

    public function arbolaprobaciones()
    {
        return $this->belongsTo(Arbolaprobacion::class, 'arbolaprobacion_id');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'param_moneda_id');
    }

    public function cuentacontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'param_cuentacontable_id');
    }

    public function estaActivo(): bool
    {
        return strtoupper((string) ($this->activo ?? 'N')) === 'S';
    }
}
