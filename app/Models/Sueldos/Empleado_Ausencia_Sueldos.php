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
