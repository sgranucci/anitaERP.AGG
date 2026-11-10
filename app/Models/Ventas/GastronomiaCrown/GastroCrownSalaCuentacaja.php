<?php

namespace App\Models\Ventas\GastronomiaCrown;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class GastroCrownSalaCuentacaja extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'gastro_crown_sala_cuentacaja';

    protected $fillable = [
        'sala_id',
        'cuentacaja_id',
        'es_default_f5',
        'orden',
    ];

    protected $casts = [
        'es_default_f5' => 'boolean',
        'orden' => 'integer',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function cuentacaja(): BelongsTo
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}
