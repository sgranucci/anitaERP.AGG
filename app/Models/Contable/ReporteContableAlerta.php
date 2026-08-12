<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteContableAlerta extends Model
{
    protected $table = 'reporte_contable_alerta';

    public const TIPO_VAR_PCT_ABS = 'var_pct_abs';

    public const TIPO_COBERTURA_ROTA = 'cobertura_rota';

    public const TIPO_ECUACION = 'ecuacion';

    protected $fillable = [
        'reporte_contable_id',
        'tipo',
        'etiqueta',
        'expresion',
        'umbral',
        'activo',
        'orden',
    ];

    protected $casts = [
        'reporte_contable_id' => 'integer',
        'umbral' => 'float',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteContable::class, 'reporte_contable_id');
    }

    /**
     * @return array<string, string>
     */
    public static function tipos(): array
    {
        return [
            self::TIPO_VAR_PCT_ABS => 'Var % absoluta ≥ umbral',
            self::TIPO_COBERTURA_ROTA => 'Cobertura plan rota (aviso)',
            self::TIPO_ECUACION => 'Ecuación contable = 0 (ej. R001-(R050+R080))',
        ];
    }
}
