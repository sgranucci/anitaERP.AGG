<?php

namespace App\Models\Caja;

use App\Traits\Caja\TalonariorendicionTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Talonariorendicion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use TalonariorendicionTrait;

    protected $fillable = ['nombre', 'serie', 'desdenumero', 'hastanumero',
        'fechainicio', 'fechacierre', 'estado'];

    protected $table = 'talonariorendicion';
}
