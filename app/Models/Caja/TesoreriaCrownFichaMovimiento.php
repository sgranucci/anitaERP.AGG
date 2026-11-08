<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownFichaMovimiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ORIGEN_MESA = 'mesa';

    public const ORIGEN_CAJERO = 'cajero';

    public const ORIGEN_CANJE = 'canje';

    public const ORIGEN_OTROS = 'otros';

    protected $table = 'tesoreria_crown_ficha_movimiento';

    protected $fillable = [
        'fecha',
        'origen',
        'codigo_ficha',
        'tipo_oper',
        'cantidad',
    ];

    protected $casts = [
        'fecha' => 'date',
        'codigo_ficha' => 'integer',
        'cantidad' => 'integer',
    ];
}
