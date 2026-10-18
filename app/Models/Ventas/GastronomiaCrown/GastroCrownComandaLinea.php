<?php

namespace App\Models\Ventas\GastronomiaCrown;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class GastroCrownComandaLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'gastro_crown_comanda_linea';

    protected $fillable = [
        'gastro_crown_comanda_id',
        'gastro_crown_cuenta_linea_id',
        'orden',
        'sku',
        'descripcion',
        'cantidad',
        'observacion',
        'prepara',
    ];

    protected $casts = [
        'orden' => 'integer',
        'cantidad' => 'float',
        'prepara' => 'boolean',
    ];

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(GastroCrownComanda::class, 'gastro_crown_comanda_id');
    }

    public function cuentaLinea(): BelongsTo
    {
        return $this->belongsTo(GastroCrownCuentaLinea::class, 'gastro_crown_cuenta_linea_id');
    }
}
