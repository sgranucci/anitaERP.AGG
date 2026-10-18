<?php

namespace App\Models\Compras;

use App\Models\Caja\Cheque;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ProgramaPagoCheque extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'programa_pago_cheque';

    protected $fillable = [
        'programa_pago_linea_id',
        'clave',
        'cheque_id',
        'monto_cheque',
        'monto_programado',
    ];

    protected $casts = [
        'monto_cheque' => 'float',
        'monto_programado' => 'float',
    ];

    public function linea(): BelongsTo
    {
        return $this->belongsTo(ProgramaPagoLinea::class, 'programa_pago_linea_id');
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class, 'cheque_id');
    }
}
