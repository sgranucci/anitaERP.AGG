<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Cabecera de configuración del canal Tiendanube, una por tienda (store_id).
 *
 * @property int $id
 * @property string|null $store_id
 * @property int|null $empresa_id
 * @property int|null $puntoventa_id
 * @property int|null $deposito_id
 * @property int|null $listaprecio_id
 * @property string|null $articulo_envio_sku
 * @property string|null $articulo_descuento_sku
 * @property string|null $usocuentacaja_nombre
 * @property bool $sube_stock
 * @property int $marketplace_codigo
 * @property string|null $hora_subida
 * @property int|null $listaprecio_precio_id
 * @property int|null $listaprecio_oferta_id
 */
class TiendanubeConfiguracion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tiendanube_configuracion';

    protected $fillable = [
        'store_id',
        'empresa_id',
        'puntoventa_id',
        'deposito_id',
        'listaprecio_id',
        'articulo_envio_sku',
        'articulo_descuento_sku',
        'usocuentacaja_nombre',
        'sube_stock',
        'marketplace_codigo',
        'hora_subida',
        'listaprecio_precio_id',
        'listaprecio_oferta_id',
    ];

    protected $casts = [
        'sube_stock' => 'boolean',
        'marketplace_codigo' => 'integer',
        'listaprecio_precio_id' => 'integer',
        'listaprecio_oferta_id' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function puntoventa(): BelongsTo
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_id');
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function listaPrecioWeb(): BelongsTo
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_precio_id');
    }

    public function listaPrecioOferta(): BelongsTo
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_oferta_id');
    }

    public function depositosStock(): HasMany
    {
        return $this->hasMany(TiendanubeStockDeposito::class, 'store_id', 'store_id')
            ->orderBy('orden')
            ->orderBy('id');
    }
}
