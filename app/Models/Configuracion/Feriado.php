<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Feriado extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'feriado';

    protected $fillable = ['nombre', 'fecha', 'tipo'];
}
