<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownMesaLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_mesa_linea';

    protected $fillable = [
        'mesa_id',
        'valor_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
    ];

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownMesa::class, 'mesa_id');
    }

    public function valor(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownValor::class, 'valor_id');
    }
}
