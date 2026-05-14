<?php

namespace App\Models\Caja;

use App\Traits\Caja\ChequeraTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Chequera extends Model implements Auditable
{
    use ChequeraTrait;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['tipochequera', 'tipocheque', 'codigo',
        'cuentacaja_id', 'estado', 'fechauso', 'desdenumerocheque',
        'hastanumerocheque'];

    protected $table = 'chequera';

    public function cuentacajas()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}
