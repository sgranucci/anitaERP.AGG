<?php

namespace App\Models\Receptivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Receptivo\MovilTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Movil extends Model implements Auditable
{
    use MovilTrait;    

    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'dominio', 'tipomovil', 'codigo', 'vencimientoverificacionmunicipal', 'vencimientoverificaciontecnica',
                            'vencimientoservice', 'vencimientocorredor', 'vencimientoingresoparque', 'vencimientoseguro'];
    protected $table = 'movil';
}
