<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReporteContableConjunto extends Model
{
    protected $table = 'reporte_contable_conjunto';

    protected $fillable = [
        'codigo',
        'nombre',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function cuentas(): HasMany
    {
        return $this->hasMany(ReporteContableConjuntoCuenta::class, 'reporte_contable_conjunto_id')
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function rubros(): HasMany
    {
        return $this->hasMany(ReporteContableRubro::class, 'conjunto_id');
    }
}
