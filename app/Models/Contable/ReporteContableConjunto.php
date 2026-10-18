<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ReporteContableConjunto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
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
