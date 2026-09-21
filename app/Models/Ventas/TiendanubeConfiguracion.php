<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Stock\Depmae;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Cabecera singleton de configuración del canal Tiendanube.
 *
 * @property int $id
 * @property int|null $empresa_id
 * @property int|null $puntoventa_id
 * @property int|null $deposito_id
 * @property int|null $listaprecio_id
 * @property string|null $articulo_envio_sku
 * @property string|null $articulo_descuento_sku
 * @property string|null $usocuentacaja_nombre
 */
class TiendanubeConfiguracion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tiendanube_configuracion';

    protected $fillable = [
        'empresa_id',
        'puntoventa_id',
        'deposito_id',
        'listaprecio_id',
        'articulo_envio_sku',
        'articulo_descuento_sku',
        'usocuentacaja_nombre',
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
}
