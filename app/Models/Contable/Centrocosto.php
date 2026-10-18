<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Contable\CentrocostoTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Centrocosto extends Model implements Auditable
{
    use CentrocostoTrait;

    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'codigo', 'abreviatura', 'tipoiva', 'grupo_codigo', 'grupo_nombre'];
    protected $table = 'centrocosto';
}
