<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Tipo_Sancion_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tipo_sancion_sueldos';

    public const CLASE_LLAMADO = 'llamado_atencion';

    public const CLASE_APERCIBIMIENTO = 'apercibimiento';

    public const CLASE_SUSPENSION = 'suspension';

    public const CLASE_OTRO = 'otro';

    /** @var array<string, string> */
    public const CLASES = [
        self::CLASE_LLAMADO => 'Llamado de atención',
        self::CLASE_APERCIBIMIENTO => 'Apercibimiento',
        self::CLASE_SUSPENSION => 'Suspensión',
        self::CLASE_OTRO => 'Otro',
    ];

    /** @var array<string, string> */
    public const TIPOS_DIA = [
        'corridos' => 'Corridos',
        'habiles' => 'Hábiles',
    ];

    protected $fillable = [
        'codigo',
        'nombre',
        'clase',
        'requiere_dias',
        'tope_dias',
        'tipo_dias',
        'goza_sueldo',
        'genera_novedad',
        'concepto_id',
        'orden_progresivo',
        'plazo_descargo_dias',
        'plantilla_notificacion',
        'activo',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'requiere_dias' => 'boolean',
        'tope_dias' => 'integer',
        'goza_sueldo' => 'boolean',
        'genera_novedad' => 'boolean',
        'concepto_id' => 'integer',
        'orden_progresivo' => 'integer',
        'plazo_descargo_dias' => 'integer',
        'activo' => 'boolean',
    ];

    public static function etiquetaClase(?string $clase): string
    {
        return self::CLASES[$clase] ?? (string) $clase;
    }

    public static function etiquetaTipoDias(?string $tipoDias): string
    {
        return self::TIPOS_DIA[$tipoDias] ?? (string) $tipoDias;
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_id');
    }

    public function sanciones(): HasMany
    {
        return $this->hasMany(Empleado_Sancion_Sueldos::class, 'tipo_sancion_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function esSuspension(): bool
    {
        return $this->clase === self::CLASE_SUSPENSION;
    }
}
