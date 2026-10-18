<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Tiposuspensioncliente extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'tiposuspensioncliente';
}

