<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReporteContableRubro extends Model
{
    protected $table = 'reporte_contable_rubro';

    protected $fillable = [
        'reporte_contable_id',
        'parent_id',
        'codigo_linea',
        'nombre',
        'nivel',
        'orden',
        'tipo',
        'formula',
        'estilo_negrita',
        'estilo_subrayado',
        'mostrar_total',
        'anita_rubro',
        'conjunto_id',
        'lado_presentacion',
        'ocultar_si_cero',
    ];

    protected $casts = [
        'nivel' => 'integer',
        'orden' => 'integer',
        'estilo_negrita' => 'boolean',
        'estilo_subrayado' => 'boolean',
        'mostrar_total' => 'boolean',
        'anita_rubro' => 'integer',
        'conjunto_id' => 'integer',
        'ocultar_si_cero' => 'boolean',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteContable::class, 'reporte_contable_id');
    }

    public function conjunto(): BelongsTo
    {
        return $this->belongsTo(ReporteContableConjunto::class, 'conjunto_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('orden')->orderBy('id');
    }

    public function cuentas(): HasMany
    {
        return $this->hasMany(ReporteContableCuenta::class, 'reporte_contable_rubro_id')
            ->orderBy('orden')
            ->orderBy('id');
    }
}
