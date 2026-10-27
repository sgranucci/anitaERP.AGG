<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownMesaJuego extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_mesa_juego';

    protected $fillable = [
        'deposito_id',
        'banca_codigo',
    ];

    protected $casts = [
        'banca_codigo' => 'integer',
    ];

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownDeposito::class, 'deposito_id');
    }
}
