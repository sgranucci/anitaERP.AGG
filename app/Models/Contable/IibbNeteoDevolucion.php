<?php

declare(strict_types=1);

namespace App\Models\Contable;

use App\Models\Compras\Proveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Devolución de retención IIBB (Debe en la cuenta 214010-014) pendiente o neteada.
 * Pendiente = importe − Σ aplicaciones de otras quincenas.
 */
class IibbNeteoDevolucion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'iibb_neteo_devolucion';

    protected $fillable = [
        'empresa_id',
        'iibb_presentacion_config_id',
        'asiento_movimiento_id',
        'asiento_id',
        'asiento_numero',
        'periodo',
        'quincena',
        'fecha',
        'detalle',
        'importe',
        'proveedor_id',
        'cert_referencia',
        'identificacion',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'importe' => 'float',
        'quincena' => 'integer',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(IibbNeteoAplicacion::class, 'iibb_neteo_devolucion_id');
    }
}
