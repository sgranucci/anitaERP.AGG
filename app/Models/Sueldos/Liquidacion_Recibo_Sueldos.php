<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Cabecera del recibo de un empleado dentro de una corrida de liquidacion.
 */
class Liquidacion_Recibo_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'liquidacion_recibo_sueldos';

    protected $fillable = [
        'liquidacion_id',
        'empleado_id',
        'legajo',
        'numero_recibo',
        'apellido_nombre',
        'cuil',
        'categoria_id',
        'categoria_desc',
        'agrupamiento_id',
        'lugartrabajo_id',
        'obrasocial_id',
        'sindicato_id',
        'fecha_ingreso',
        'sueldo_basico',
        'dias_trabajados',
        'dias_vacaciones',
        'horas',
        'total_remunerativo',
        'total_no_remunerativo',
        'total_bruto',
        'total_descuentos',
        'total_aportes',
        'total_contribuciones',
        'total_asignaciones',
        'neto',
        'redondeo',
        'neto_a_pagar',
        'estado',
        'observacion',
        'origen',
        'confidencial',
        'origen_fingerprint',
    ];

    protected $casts = [
        'liquidacion_id' => 'integer',
        'empleado_id' => 'integer',
        'legajo' => 'integer',
        'numero_recibo' => 'integer',
        'categoria_id' => 'integer',
        'agrupamiento_id' => 'integer',
        'lugartrabajo_id' => 'integer',
        'obrasocial_id' => 'integer',
        'sindicato_id' => 'integer',
        'fecha_ingreso' => 'date',
        'sueldo_basico' => 'decimal:2',
        'dias_trabajados' => 'decimal:2',
        'dias_vacaciones' => 'decimal:2',
        'horas' => 'decimal:2',
        'total_remunerativo' => 'decimal:2',
        'total_no_remunerativo' => 'decimal:2',
        'total_bruto' => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'total_aportes' => 'decimal:2',
        'total_contribuciones' => 'decimal:2',
        'total_asignaciones' => 'decimal:2',
        'neto' => 'decimal:2',
        'redondeo' => 'decimal:2',
        'neto_a_pagar' => 'decimal:2',
        'confidencial' => 'boolean',
    ];

    public const ORIGEN_MOTOR = 'motor_erp';

    public const ORIGEN_AUXCONF = 'anita_auxconf';

    public function liquidacion()
    {
        return $this->belongsTo(Liquidacion_Sueldos::class, 'liquidacion_id');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function detalles()
    {
        return $this->hasMany(Liquidacion_Detalle_Sueldos::class, 'recibo_id')
            ->orderBy('nro_linea');
    }
}
