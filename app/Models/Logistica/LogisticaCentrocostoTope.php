<?php

namespace App\Models\Logistica;

use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class LogisticaCentrocostoTope extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'logistica_centrocosto_tope';

    protected $fillable = [
        'centrocosto_id', 'monto_mensual',
    ];

    protected $casts = [
        'centrocosto_id' => 'integer',
        'monto_mensual' => 'float',
    ];

    public function centrocosto(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}
