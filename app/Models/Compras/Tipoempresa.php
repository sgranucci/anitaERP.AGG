<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Tipoempresa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'tipoempresa';
}
