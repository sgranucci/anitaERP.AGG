<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownBancaLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_banca_linea';

    protected $fillable = [
        'banca_id',
        'valor_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    public function banca(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownBanca::class, 'banca_id');
    }

    public function valor(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownValor::class, 'valor_id');
    }
}
