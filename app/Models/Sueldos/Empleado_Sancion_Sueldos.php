<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use App\Support\Sueldos\EmpleadoSancionSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class Empleado_Sancion_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'empleado_sancion_sueldos';

    protected $fillable = [
        'empleado_id',
        'tipo_sancion_id',
        'motivo_sancion_id',
        'fecha_hecho',
        'fecha_desde',
        'fecha_hasta',
        'cant_dias',
        'tipo_dias',
        'importe_perdida',
        'fecha_notificacion',
        'fecha_recepcion',
        'estado',
        'comentario',
        'descargo_texto',
        'descargo_fecha',
        'resolucion_texto',
        'resolucion_fecha',
        'usuario_id',
        'nro_interno',
        'anita_nro_interno',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'tipo_sancion_id' => 'integer',
        'motivo_sancion_id' => 'integer',
        'fecha_hecho' => 'date',
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'cant_dias' => 'integer',
        'importe_perdida' => 'decimal:2',
        'fecha_notificacion' => 'date',
        'fecha_recepcion' => 'date',
        'descargo_fecha' => 'date',
        'resolucion_fecha' => 'date',
        'usuario_id' => 'integer',
        'nro_interno' => 'integer',
        'anita_nro_interno' => 'integer',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(Tipo_Sancion_Sueldos::class, 'tipo_sancion_id');
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(Motivo_Sancion_Sueldos::class, 'motivo_sancion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(Empleado_Sancion_Archivo_Sueldos::class, 'sancion_id');
    }

    public function novedad(): HasOne
    {
        return $this->hasOne(Novedad_Sueldos::class, 'sancion_id');
    }

    public function estadoLabel(): string
    {
        return EmpleadoSancionSupport::etiquetaEstado($this->estado);
    }

    public function esEditable(): bool
    {
        return in_array((string) $this->estado, [
            EmpleadoSancionSupport::ESTADO_BORRADOR,
            EmpleadoSancionSupport::ESTADO_NOTIFICADA,
            EmpleadoSancionSupport::ESTADO_CON_DESCARGO,
        ], true);
    }
}
