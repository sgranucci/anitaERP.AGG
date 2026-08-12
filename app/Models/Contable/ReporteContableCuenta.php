<?php

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReporteContableCuenta extends Model
{
    protected $table = 'reporte_contable_cuenta';

    protected $fillable = [
        'reporte_contable_rubro_id',
        'empresa_id',
        'cuentacontable_id',
        'codigo_cuenta',
        'codigo_hasta',
        'origen',
        'signo',
        'carga_ccosto',
        'sucursal',
        'orden',
    ];

    protected $casts = [
        'codigo_cuenta' => 'integer',
        'codigo_hasta' => 'integer',
        'signo' => 'integer',
        'sucursal' => 'integer',
        'orden' => 'integer',
    ];

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(ReporteContableRubro::class, 'reporte_contable_rubro_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontable(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }

    public function ccostos(): HasMany
    {
        return $this->hasMany(ReporteContableCcosto::class, 'reporte_contable_cuenta_id');
    }
}
