<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Formula_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_formula_sueldos';

    protected $fillable = [
        'concepto_id',
        'nro_linea',
        'formula',
    ];

    protected $casts = [
        'concepto_id' => 'integer',
        'nro_linea' => 'integer',
    ];

    public function concepto()
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_id');
    }
}
