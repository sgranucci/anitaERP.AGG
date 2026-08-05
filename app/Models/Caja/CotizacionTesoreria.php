<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Support\Caja\CotizacionTesoreriaMonedasSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionTesoreria extends Model
{
    protected $table = 'cotizacion_tesoreria';

    protected $fillable = [
        'empresa_id',
        'fecha',
        'fecha_anita',
        'fecha_alfa',
        'cambio_compra_2',
        'cambio_compra_3',
        'cambio_compra_4',
        'cambio_compra_5',
        'cambio_compra_6',
        'cambio_compra_7',
        'cambio_compra_8',
        'cambio_compra_9',
        'cambio_venta_2',
        'cambio_venta_3',
        'cambio_venta_4',
        'cambio_venta_5',
        'cambio_venta_6',
        'cambio_venta_7',
        'cambio_venta_8',
        'cambio_venta_9',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_anita' => 'integer',
        'cambio_compra_2' => 'float',
        'cambio_compra_3' => 'float',
        'cambio_compra_4' => 'float',
        'cambio_compra_5' => 'float',
        'cambio_compra_6' => 'float',
        'cambio_compra_7' => 'float',
        'cambio_compra_8' => 'float',
        'cambio_compra_9' => 'float',
        'cambio_venta_2' => 'float',
        'cambio_venta_3' => 'float',
        'cambio_venta_4' => 'float',
        'cambio_venta_5' => 'float',
        'cambio_venta_6' => 'float',
        'cambio_venta_7' => 'float',
        'cambio_venta_8' => 'float',
        'cambio_venta_9' => 'float',
    ];

    public function empresas(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function tasaCompra(int $codigo): ?float
    {
        $col = CotizacionTesoreriaMonedasSupport::columnaCompra($codigo);

        return $this->{$col} !== null ? (float) $this->{$col} : null;
    }

    public function tasaVenta(int $codigo): ?float
    {
        $col = CotizacionTesoreriaMonedasSupport::columnaVenta($codigo);

        return $this->{$col} !== null ? (float) $this->{$col} : null;
    }
}
