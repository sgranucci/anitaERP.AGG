<?php

declare(strict_types=1);

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemesaLinea extends Model
{
    protected $table = 'remesa_linea';

    protected $fillable = [
        'remesa_id',
        'lado',
        'cuentacaja_id',
        'monto',
        'orden',
    ];

    protected $casts = [
        'monto' => 'float',
        'orden' => 'integer',
    ];

    public function remesa(): BelongsTo
    {
        return $this->belongsTo(Remesa::class, 'remesa_id');
    }

    public function cuentacaja(): BelongsTo
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}
