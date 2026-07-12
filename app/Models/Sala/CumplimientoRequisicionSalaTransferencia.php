<?php

namespace App\Models\Sala;

use App\Models\Stock\Transferencia_Mercaderia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CumplimientoRequisicionSalaTransferencia extends Model
{
    protected $table = 'cumplimiento_requisicion_sala_transferencia';

    protected $fillable = [
        'cumplimiento_requisicion_sala_id',
        'transferencia_mercaderia_id',
    ];

    public function cumplimiento(): BelongsTo
    {
        return $this->belongsTo(CumplimientoRequisicionSala::class, 'cumplimiento_requisicion_sala_id');
    }

    public function transferenciaMercaderia(): BelongsTo
    {
        return $this->belongsTo(Transferencia_Mercaderia::class, 'transferencia_mercaderia_id');
    }
}
