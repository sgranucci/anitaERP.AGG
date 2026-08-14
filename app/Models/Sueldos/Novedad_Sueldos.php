<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Novedad de liquidación (Anita: novedad). Entrada del período para el motor.
 */
class Novedad_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'novedad_sueldos';

    protected $fillable = [
        'empresa_id',
        'liquidacion_id',
        'empleado_id',
        'concepto_id',
        'concepto_codigo',
        'valor1',
        'valor2',
        'estado',
        'fecha_vto',
        'fecha_desde',
        'fecha_hasta',
        'nro_interno',
        'periodo',
        'origen',
        'ausencia_id',
        'dtofallo_id',
        'usuario_id',
        'observacion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'liquidacion_id' => 'integer',
        'empleado_id' => 'integer',
        'concepto_id' => 'integer',
        'concepto_codigo' => 'integer',
        'valor1' => 'decimal:4',
        'valor2' => 'decimal:4',
        'fecha_vto' => 'date',
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'nro_interno' => 'integer',
        'periodo' => 'integer',
        'ausencia_id' => 'integer',
        'dtofallo_id' => 'integer',
        'usuario_id' => 'integer',
    ];

    /** Tiene vigencia recurrente (estilo SAP 0014 / Workday Ongoing). */
    public function esRecurrente(): bool
    {
        return $this->fecha_desde !== null;
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion_Sueldos::class, 'liquidacion_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ausencia(): BelongsTo
    {
        return $this->belongsTo(Empleado_Ausencia_Sueldos::class, 'ausencia_id');
    }

    public function dtofallo(): BelongsTo
    {
        return $this->belongsTo(Dtofallo_Sueldos::class, 'dtofallo_id');
    }

    public function estadoLabel(): string
    {
        return NovedadSueldosCatalogo::etiquetaEstado($this->estado);
    }

    public function origenLabel(): string
    {
        return NovedadSueldosCatalogo::etiquetaOrigen($this->origen);
    }

    public function scopeActivasParaMotor($query)
    {
        return $query->where('estado', '!=', NovedadSueldosCatalogo::ESTADO_ANULADA);
    }
}
