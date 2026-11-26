<?php

declare(strict_types=1);

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Parte de una devolución aplicada contra un certificado de una quincena.
 */
class IibbNeteoAplicacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'iibb_neteo_aplicacion';

    protected $fillable = [
        'iibb_neteo_devolucion_id',
        'empresa_id',
        'iibb_presentacion_config_id',
        'periodo',
        'quincena',
        'nro_certificado',
        'proveedor_id',
        'importe_aplicado',
    ];

    protected $casts = [
        'importe_aplicado' => 'float',
        'quincena' => 'integer',
    ];

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(IibbNeteoDevolucion::class, 'iibb_neteo_devolucion_id');
    }
}
