<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use App\Models\Sueldos\Empleado_Sueldos;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class PerdidaPersonal extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_PENDIENTE = 'P';

    public const TURNO_MANIANA = '1';

    public const TURNO_TARDE = '2';

    public const TURNO_NOCHE = '3';

    /** @var array<int, array{valor: string, nombre: string}> */
    public static array $enumEstado = [
        ['valor' => self::ESTADO_PENDIENTE, 'nombre' => 'Pendiente'],
    ];

    /** @var array<int, array{valor: string, nombre: string}> */
    public static array $enumTurno = [
        ['valor' => self::TURNO_MANIANA, 'nombre' => 'Mañana'],
        ['valor' => self::TURNO_TARDE, 'nombre' => 'Tarde'],
        ['valor' => self::TURNO_NOCHE, 'nombre' => 'Noche'],
    ];

    /** Códigos de concepto Anita que habilitan carga de máquina. */
    public const CONCEPTOS_CON_MAQUINA = [6, 8];

    protected $table = 'perdida_personal';

    protected $fillable = [
        'numero',
        'fecha',
        'empresa_id',
        'centrocosto_id',
        'imputacion_perdida_id',
        'concepto_perdida_id',
        'empleado_sueldos_id',
        'supervisor_empleado_sueldos_id',
        'turno',
        'fecha_ingreso',
        'hora_ingreso',
        'usuario_id',
        'estado',
        'leyenda',
        'maquina',
        'importe',
    ];

    protected $casts = [
        'numero' => 'integer',
        'fecha' => 'date',
        'fecha_ingreso' => 'date',
        'empresa_id' => 'integer',
        'centrocosto_id' => 'integer',
        'imputacion_perdida_id' => 'integer',
        'concepto_perdida_id' => 'integer',
        'empleado_sueldos_id' => 'integer',
        'supervisor_empleado_sueldos_id' => 'integer',
        'usuario_id' => 'integer',
        'importe' => 'decimal:2',
    ];

    protected $attributes = [
        'estado' => self::ESTADO_PENDIENTE,
        'turno' => self::TURNO_MANIANA,
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function centrocosto()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function imputacionPerdida()
    {
        return $this->belongsTo(ImputacionPerdida::class, 'imputacion_perdida_id');
    }

    public function conceptoPerdida()
    {
        return $this->belongsTo(ConceptoPerdida::class, 'concepto_perdida_id');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_sueldos_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'supervisor_empleado_sueldos_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function getEstadoLabelAttribute(): string
    {
        foreach (self::$enumEstado as $row) {
            if ($row['valor'] === $this->estado) {
                return $row['nombre'];
            }
        }

        return (string) ($this->estado ?? '');
    }

    public function getTurnoLabelAttribute(): string
    {
        foreach (self::$enumTurno as $row) {
            if ($row['valor'] === $this->turno) {
                return $row['nombre'];
            }
        }

        return (string) ($this->turno ?? '');
    }
}
