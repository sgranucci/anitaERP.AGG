<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Familiar a cargo para cantidades del plan de Ganancias 4ta categoría.
 */
class Empleado_Familiar_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPOS = [
        'CONYUGE' => 'Cónyuge / unión convivencial',
        'HIJOS' => 'Hijo/a (100%)',
        'HIJOS_50' => 'Hijo/a (50%)',
        'HIJO_INCAP' => 'Hijo/a incapacitado',
    ];

    protected $table = 'empleado_familiar_sueldos';

    protected $fillable = [
        'empleado_id',
        'tipo',
        'apellido',
        'nombre',
        'documento',
        'fecha_nacimiento',
        'porcentaje_deduccion',
        'vigente_desde',
        'vigente_hasta',
        'activo',
        'observacion',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'porcentaje_deduccion' => 'integer',
        'fecha_nacimiento' => 'date',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'activo' => 'boolean',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function getTipoDescripcionAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? (string) $this->tipo;
    }

    /** ¿Cuenta para el mes de pago indicado? */
    public function vigenteEnMes(int $anio, int $mes): bool
    {
        if (! $this->activo) {
            return false;
        }
        $inicioMes = sprintf('%04d-%02d-01', $anio, $mes);
        $finMes = date('Y-m-t', strtotime($inicioMes));

        if ($this->vigente_desde && $this->vigente_desde->format('Y-m-d') > $finMes) {
            return false;
        }
        if ($this->vigente_hasta && $this->vigente_hasta->format('Y-m-d') < $inicioMes) {
            return false;
        }

        return true;
    }
}
