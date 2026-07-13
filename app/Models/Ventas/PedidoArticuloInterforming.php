<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Moneda;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Depmae;
use App\Models\Stock\Unidadmedida;
use App\Support\Ventas\PedidoEstadosInterforming;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ítem de pedido INTERFORMING (tabla pedido_articulo + columnas IF).
 */
class PedidoArticuloInterforming extends Pedido_Articulo
{
    protected $table = 'pedido_articulo';

    protected $fillable = [
        'pedido_id', 'articulo_id', 'numeroitem', 'caja', 'pieza', 'kilo', 'pesada',
        'precio', 'incluyeimpuesto', 'listaprecio_id', 'moneda_id', 'descuento',
        'descuentointegrado', 'lote_id', 'observacion', 'estado', 'unidadmedida_id',
        'descuentoventa_id',
        // Interforming
        'cantidad', 'cantidad_a_entregar', 'cantidad_entregada', 'cantidad_facturada',
        'unidadmedida_alter_id', 'cantidad_alter', 'fechaentrega', 'orden_compra',
        'articulo_cliente', 'partida', 'porc_fason', 'porc_fason_ant', 'precio_fason',
        'moneda_fason_id', 'deposito_id', 'ubicacion', 'detalle_ubicacion',
        'estado_cierre', 'motivocierrepedido_id', 'fecha_cierre', 'hora_cierre',
        'usuario_cierre_id', 'usuario_aprobacion_id', 'fecha_aprobacion',
        'motivo_rechazo_id', 'descripcion_aux',
    ];

    protected $casts = [
        'fechaentrega' => 'date',
        'fecha_cierre' => 'date',
        'fecha_aprobacion' => 'date',
    ];

    public function pedidos(): BelongsTo
    {
        return $this->belongsTo(PedidoInterforming::class, 'pedido_id', 'id');
    }

    public function unidadmedidaAlter(): BelongsTo
    {
        return $this->belongsTo(Unidadmedida::class, 'unidadmedida_alter_id');
    }

    public function monedaFason(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_fason_id');
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function usuarioAprobacion(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_aprobacion_id');
    }

    public function usuarioCierre(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_cierre_id');
    }

    public function motivocierrepedido(): BelongsTo
    {
        return $this->belongsTo(Motivocierrepedido::class, 'motivocierrepedido_id');
    }

    public function etiquetaEstado(): string
    {
        return PedidoEstadosInterforming::etiquetaItem($this->estado);
    }
}
