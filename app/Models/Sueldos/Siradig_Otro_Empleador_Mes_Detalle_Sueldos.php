<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Detalle de conceptos análogos de un mes (otrosConAn/bonosProd/fallosCaja/conSimNat).
 */
class Siradig_Otro_Empleador_Mes_Detalle_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'siradig_otro_empleador_mes_detalle_sueldos';

    protected $fillable = [
        'otro_empleador_mes_id',
        'grupo',
        'descripcion',
        'monto',
    ];

    protected $casts = [
        'otro_empleador_mes_id' => 'integer',
    ];

    public function mes(): BelongsTo
    {
        return $this->belongsTo(Siradig_Otro_Empleador_Mes_Sueldos::class, 'otro_empleador_mes_id');
    }
}
