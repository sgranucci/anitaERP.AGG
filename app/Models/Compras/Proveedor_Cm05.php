<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Provincia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Proveedor_Cm05 extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'proveedor_cm05';

    protected $fillable = [
        'proveedor_id',
        'provincia_id',
        'fechavigencia',
    ];

    protected $casts = [
        'fechavigencia' => 'date',
    ];

    public function proveedores(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function provincias(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }
}
