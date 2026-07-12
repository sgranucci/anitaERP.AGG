<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisicionArticuloCambio extends Model
{
    protected $table = 'requisicion_articulo_cambio';

    protected $fillable = [
        'requisicion_id',
        'requisicion_articulo_id',
        'articulo_id_anterior',
        'articulo_id_nuevo',
        'usuario_id',
        'cumplimiento_requisicion_compra_id',
        'motivo',
    ];

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function requisicionArticulo(): BelongsTo
    {
        return $this->belongsTo(Requisicion_Articulo::class, 'requisicion_articulo_id');
    }

    public function articuloAnterior(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id_anterior');
    }

    public function articuloNuevo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id_nuevo');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cumplimiento(): BelongsTo
    {
        return $this->belongsTo(CumplimientoRequisicionCompra::class, 'cumplimiento_requisicion_compra_id');
    }
}
