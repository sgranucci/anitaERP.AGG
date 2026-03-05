<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Caja\TalonariorendicionTrait;

class Talonariorendicion extends Model
{
    use TalonariorendicionTrait;

    protected $fillable = ['nombre', 'serie', 'desdenumero', 'hastanumero',
                            'fechainicio', 'fechacierre', 'estado'];
    protected $table = 'talonariorendicion';

}



