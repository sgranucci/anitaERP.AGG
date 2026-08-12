<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReporteContableConjuntoCuenta extends Model
{
    protected $table = 'reporte_contable_conjunto_cuenta';

    protected $fillable = [
        'reporte_contable_conjunto_id',
        'cuentacontable_id',
        'codigo_cuenta',
        'codigo_hasta',
        'origen',
        'signo',
        'carga_ccosto',
        'orden',
    ];

    protected $casts = [
        'codigo_cuenta' => 'integer',
        'codigo_hasta' => 'integer',
        'signo' => 'integer',
        'orden' => 'integer',
        'cuentacontable_id' => 'integer',
    ];

    public function conjunto(): BelongsTo
    {
        return $this->belongsTo(ReporteContableConjunto::class, 'reporte_contable_conjunto_id');
    }

    public function ccostos(): HasMany
    {
        return $this->hasMany(ReporteContableConjuntoCcosto::class, 'reporte_contable_conjunto_cuenta_id');
    }

    public function cuentacontable(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }
}
