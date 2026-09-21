<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Legajo RMA cambio/devolución marketplace (Facturación Local Ferli).
 */
class CambioDevolucionMarketplace extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cambio_devolucion_marketplace';

    protected $fillable = [
        'numero',
        'canal',
        'local_venta_id',
        'empresa_id',
        'usuario_alta_id',
        'tiendanube_pedido_id',
        'venta_original_id',
        'venta_reemplazo_id',
        'venta_nc_id',
        'cliente_id',
        'receptor_nombre',
        'receptor_documento',
        'estado',
        'motivo_codigo',
        'motivo',
        'disposicion',
        'diferencia_importe',
        'diferencia_sentido',
        'compensacion_registrada_at',
        'compensacion_observacion',
        'observacion',
    ];

    protected $casts = [
        'numero' => 'integer',
        'local_venta_id' => 'integer',
        'empresa_id' => 'integer',
        'usuario_alta_id' => 'integer',
        'tiendanube_pedido_id' => 'integer',
        'venta_original_id' => 'integer',
        'venta_reemplazo_id' => 'integer',
        'venta_nc_id' => 'integer',
        'cliente_id' => 'integer',
        'diferencia_importe' => 'float',
        'compensacion_registrada_at' => 'datetime',
    ];

    public function localVenta()
    {
        return $this->belongsTo(LocalVenta::class, 'local_venta_id');
    }

    public function usuarioAlta()
    {
        return $this->belongsTo(Usuario::class, 'usuario_alta_id');
    }

    public function tiendanubePedido()
    {
        return $this->belongsTo(TiendanubePedido::class, 'tiendanube_pedido_id');
    }

    public function ventaOriginal()
    {
        return $this->belongsTo(Venta::class, 'venta_original_id');
    }

    public function ventaReemplazo()
    {
        return $this->belongsTo(Venta::class, 'venta_reemplazo_id');
    }

    public function ventaNc()
    {
        return $this->belongsTo(Venta::class, 'venta_nc_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function lineas()
    {
        return $this->hasMany(CambioDevolucionMarketplaceLinea::class, 'cambio_id');
    }

    public function estados()
    {
        return $this->hasMany(CambioDevolucionMarketplaceEstado::class, 'cambio_id')->orderBy('fecha')->orderBy('id');
    }

    public function archivos()
    {
        return $this->hasMany(CambioDevolucionMarketplaceArchivo::class, 'cambio_id');
    }
}
