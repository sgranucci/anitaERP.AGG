<?php

namespace App\Models\Receptivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Receptivo\MovilTrait;

class Movil extends Model
{
    use MovilTrait;    

    protected $fillable = ['nombre', 'dominio', 'tipomovil', 'codigo', 'vencimientoverificacionmunicipal', 'vencimientoverificaciontecnica',
                            'vencimientoservice', 'vencimientocorredor', 'vencimientoingresoparque', 'vencimientoseguro'];
    protected $table = 'movil';
}
