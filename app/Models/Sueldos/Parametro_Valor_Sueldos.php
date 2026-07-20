<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Valor con vigencia de un parametro global de liquidacion.
 */
class Parametro_Valor_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'parametro_valor_sueldos';

    protected $fillable = [
        'parametro_id',
        'fecha_vigencia',
        'valor',
        'valor_texto',
    ];

    protected $casts = [
        'parametro_id' => 'integer',
        'fecha_vigencia' => 'date',
        'valor' => 'decimal:6',
    ];

    public function parametro()
    {
        return $this->belongsTo(Parametro_Sueldos::class, 'parametro_id');
    }
}
