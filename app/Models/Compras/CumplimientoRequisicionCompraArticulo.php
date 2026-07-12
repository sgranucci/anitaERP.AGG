<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CumplimientoRequisicionCompraArticulo extends Model
{
    protected $table = 'cumplimiento_requisicion_compra_articulo';

    protected $fillable = [
        'cumplimiento_requisicion_compra_id',
        'requisicion_id',
        'requisicion_articulo_id',
        'articulo_id',
        'articulo_id_original',
        'cantidad_entrega',
        'cantidad_pendiente_antes',
        'cantidadentregada_antes',
        'deposito_origen_id',
        'deposito_destino_id',
        'precio',
        'moneda_id',
        'centrocostodestino_id',
        'detalle',
        'estado_requisicion_antes',
    ];

    protected $casts = [
        'cantidad_entrega' => 'float',
        'cantidad_pendiente_antes' => 'float',
        'cantidadentregada_antes' => 'float',
        'precio' => 'float',
    ];

    public function cumplimiento(): BelongsTo
    {
        return $this->belongsTo(CumplimientoRequisicionCompra::class, 'cumplimiento_requisicion_compra_id');
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function requisicionArticulo(): BelongsTo
    {
        return $this->belongsTo(Requisicion_Articulo::class, 'requisicion_articulo_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function articuloOriginal(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id_original');
    }

    public function depositoOrigen(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_origen_id');
    }

    public function depositoDestino(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_destino_id');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function centrocostoDestino(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocostodestino_id');
    }
}
