<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Caja\Cuentacaja;
use App\Traits\Caja\ChequeraTrait;

class Chequera extends Model
{
    use ChequeraTrait;

    protected $fillable = ['tipochequera', 'tipocheque', 'codigo', 
                            'cuentacaja_id', 'estado', 'fechauso', 'desdenumerocheque',
                            'hastanumerocheque'];
    protected $table = 'chequera';

    public function cuentacajas()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}

