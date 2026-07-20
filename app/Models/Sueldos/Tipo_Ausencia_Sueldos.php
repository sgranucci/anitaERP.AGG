<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Tipo_Ausencia_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tipo_ausencia_sueldos';

    protected $fillable = [
        'codigo',
        'nombre',
        'categoria',
        'afecta_saldo_vacaciones',
        'goza_sueldo',
        'computa_antiguedad',
        'requiere_certificado',
        'tipo_dias',
        'tope_dias_anio',
        'concepto_id',
        'color',
        'activo',
        'orden',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'afecta_saldo_vacaciones' => 'boolean',
        'goza_sueldo' => 'boolean',
        'computa_antiguedad' => 'boolean',
        'requiere_certificado' => 'boolean',
        'tope_dias_anio' => 'integer',
        'concepto_id' => 'integer',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    /** @var array<string, string> */
    public const CATEGORIAS = [
        'vacaciones' => 'Vacaciones',
        'enfermedad' => 'Enfermedad inculpable',
        'accidente' => 'Accidente / ART',
        'licencia' => 'Licencia especial (LCT art. 158)',
        'suspension' => 'Suspensión',
        'otro' => 'Otro',
    ];

    /** @var array<string, string> */
    public const TIPOS_DIA = [
        'corridos' => 'Corridos',
        'habiles' => 'Hábiles',
    ];

    public static function etiquetaCategoria(?string $categoria): string
    {
        return self::CATEGORIAS[$categoria] ?? (string) $categoria;
    }

    public static function etiquetaTipoDias(?string $tipoDias): string
    {
        return self::TIPOS_DIA[$tipoDias] ?? (string) $tipoDias;
    }

    public function concepto()
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_id');
    }

    public function esVacaciones(): bool
    {
        return $this->categoria === 'vacaciones' || (bool) $this->afecta_saldo_vacaciones;
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
