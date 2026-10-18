<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Tipodocumento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'abreviatura', 'codigoexterno'];
    protected $table = 'tipodocumento';
}

