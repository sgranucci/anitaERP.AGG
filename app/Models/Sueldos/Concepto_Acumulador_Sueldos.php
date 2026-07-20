<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Override de pertenencia concepto <-> acumulador (incluir/excluir con signo),
 * por encima del agrupamiento automatico por tipo.
 */
class Concepto_Acumulador_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_acumulador_sueldos';

    protected $fillable = [
        'concepto_id',
        'acumulador_id',
        'signo',
        'excluir',
    ];

    protected $casts = [
        'concepto_id' => 'integer',
        'acumulador_id' => 'integer',
        'signo' => 'integer',
        'excluir' => 'boolean',
    ];

    public function concepto()
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_id');
    }

    public function acumulador()
    {
        return $this->belongsTo(Acumulador_Sueldos::class, 'acumulador_id');
    }
}
