<?php

namespace App\Models\Sueldos;

use App\Models\Contable\Centrocosto;
use App\Support\Sueldos\ContratoModalidadSijp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Empleado_Contrato_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const MOTIVO_REEMPLAZO = 'REEMPLAZO';

    public const MOTIVO_EVENTO = 'EVENTO';

    /** @var array<string, string> */
    public const MOTIVOS = [
        self::MOTIVO_REEMPLAZO => 'Reemplazo',
        self::MOTIVO_EVENTO => 'Evento',
    ];

    protected $table = 'empleado_contrato_sueldos';

    protected $fillable = [
        'empleado_id',
        'nro_orden',
        'desde_fecha',
        'hasta_fecha',
        'comentario',
        'modalidad',
        'centrocosto_id',
        'categoria_id',
        'liquidacion',
        'motivo',
        'dias',
        'empleado_reemplazo_id',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'nro_orden' => 'integer',
        'desde_fecha' => 'date',
        'hasta_fecha' => 'date',
        'centrocosto_id' => 'integer',
        'categoria_id' => 'integer',
        'liquidacion' => 'integer',
        'dias' => 'integer',
        'empleado_reemplazo_id' => 'integer',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function empleadoReemplazo(): BelongsTo
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_reemplazo_id');
    }

    public function centrocosto(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria_Sueldos::class, 'categoria_id');
    }

    public function emisiones(): HasMany
    {
        return $this->hasMany(Empleado_Contrato_Emision_Sueldos::class, 'empleado_contrato_id');
    }

    public function getMotivoLabelAttribute(): string
    {
        return self::MOTIVOS[$this->motivo] ?? (string) $this->motivo;
    }

    public function getModalidadLabelAttribute(): string
    {
        return ContratoModalidadSijp::label($this->modalidad);
    }
}
