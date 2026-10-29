<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Factura o nota emitida desde un legajo de cambio/devolución marketplace.
 */
class CambioDevolucionMarketplaceComprobante extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPO_FAC_REEMPLAZO = 'fac_reemplazo';

    public const TIPO_NC_REEMPLAZO = 'nc_reemplazo';

    public const TIPO_NC_ORIGINAL = 'nc_original';

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::TIPO_FAC_REEMPLAZO => 'Factura reemplazo',
        self::TIPO_NC_REEMPLAZO => 'Nota de crédito del reemplazo',
        self::TIPO_NC_ORIGINAL => 'Nota de crédito de lo devuelto',
    ];

    protected $table = 'cambio_devolucion_marketplace_comprobante';

    protected $fillable = [
        'cambio_id',
        'tipo',
        'venta_id',
        'anula_venta_id',
    ];

    protected $casts = [
        'cambio_id' => 'integer',
        'venta_id' => 'integer',
        'anula_venta_id' => 'integer',
    ];

    public function cambio()
    {
        return $this->belongsTo(CambioDevolucionMarketplace::class, 'cambio_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function etiqueta(): string
    {
        return self::ETIQUETAS[(string) $this->tipo] ?? (string) $this->tipo;
    }
}
