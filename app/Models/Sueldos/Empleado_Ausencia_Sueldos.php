<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Empleado_Ausencia_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'empleado_ausencia_sueldos';

    protected $fillable = [
        'empleado_id',
        'tipo_ausencia_id',
        'anio_imputacion',
        'fecha_desde',
        'fecha_hasta',
        'dias',
        'tipo_dias',
        'modalidad_vacaciones',
        'estado',
        'liquidacion_id',
        'certificado_archivo',
        'observacion',
        'usuario_id',
        'aprobado_por',
        'aprobado_at',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'tipo_ausencia_id' => 'integer',
        'anio_imputacion' => 'integer',
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'dias' => 'decimal:2',
        'modalidad_vacaciones' => 'integer',
        'liquidacion_id' => 'integer',
        'aprobado_at' => 'datetime',
    ];

    public const ESTADOS = [
        'planificada' => 'Planificada',
        'aprobada' => 'Aprobada',
        'tomada' => 'Tomada',
        'liquidada' => 'Liquidada',
        'anulada' => 'Anulada',
    ];

    /** Anita LVAC: 1 = plus (concepto 422), 2 = adelanto (concepto 420). */
    public const MODALIDAD_VACACIONES_PLUS = Liquidacion_Sueldos::TIPO_VACACIONES_PLUS;

    public const MODALIDAD_VACACIONES_ADELANTO = Liquidacion_Sueldos::TIPO_VACACIONES_ADELANTO;

    public const MODALIDADES_VACACIONES = Liquidacion_Sueldos::TIPOS_VACACIONES;

    /** Estados que efectivamente consumen saldo de vacaciones. */
    public const ESTADOS_CONSUMEN = ['tomada', 'liquidada'];

    public function empleado()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function tipo()
    {
        return $this->belongsTo(Tipo_Ausencia_Sueldos::class, 'tipo_ausencia_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function consumeSaldo(): bool
    {
        return in_array($this->estado, self::ESTADOS_CONSUMEN, true);
    }
}
