<?php

namespace App\Models\Compras;

use App\Models\Stock\Transferencia_Mercaderia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CumplimientoRequisicionCompraTransferencia extends Model
{
    protected $table = 'cumplimiento_requisicion_compra_transferencia';

    protected $fillable = [
        'cumplimiento_requisicion_compra_id',
        'transferencia_mercaderia_id',
    ];

    public function cumplimiento(): BelongsTo
    {
        return $this->belongsTo(CumplimientoRequisicionCompra::class, 'cumplimiento_requisicion_compra_id');
    }

    public function transferenciaMercaderia(): BelongsTo
    {
        return $this->belongsTo(Transferencia_Mercaderia::class, 'transferencia_mercaderia_id');
    }
}
