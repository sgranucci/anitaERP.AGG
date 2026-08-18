<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteSueldosDefinibleAlerta extends Model
{
    public const TIPO_SIN_FILAS = 'sin_filas';

    public const TIPO_FILAS_MAYOR = 'filas_mayor';

    public const TIPO_TOTAL_FUERA_RANGO = 'total_fuera_rango';

    public const TIPO_VARIACION_PCT = 'variacion_pct';

    public const TIPO_PARIDAD = 'paridad';

    protected $table = 'reporte_sueldos_definible_alerta';

    protected $fillable = [
        'reporte_sueldos_definible_id',
        'nombre',
        'tipo',
        'columna_nro',
        'operador',
        'umbral',
        'umbral_hasta',
        'bloqueante',
        'activo',
        'orden',
    ];

    protected $casts = [
        'reporte_sueldos_definible_id' => 'integer',
        'columna_nro' => 'integer',
        'umbral' => 'float',
        'umbral_hasta' => 'float',
        'bloqueante' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinible::class, 'reporte_sueldos_definible_id');
    }

    /**
     * @return array<string, string>
     */
    public static function tipos(): array
    {
        return [
            self::TIPO_SIN_FILAS => 'El informe no devuelve filas',
            self::TIPO_FILAS_MAYOR => 'Cantidad de filas supera el umbral',
            self::TIPO_TOTAL_FUERA_RANGO => 'Total de columna fuera de rango',
            self::TIPO_VARIACION_PCT => 'Variación porcentual supera el umbral',
            self::TIPO_PARIDAD => 'La comparación con Anita no coincide',
        ];
    }

    public function comparar(float $valor): bool
    {
        return match ($this->operador) {
            '>=' => $valor >= (float) $this->umbral,
            '<' => $valor < (float) $this->umbral,
            '<=' => $valor <= (float) $this->umbral,
            '=' => abs($valor - (float) $this->umbral) < 0.0001,
            '!=' => abs($valor - (float) $this->umbral) >= 0.0001,
            'entre' => $valor >= (float) $this->umbral
                && $valor <= (float) ($this->umbral_hasta ?? $this->umbral),
            default => $valor > (float) $this->umbral,
        };
    }
}
