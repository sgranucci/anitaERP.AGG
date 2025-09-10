<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Impuesto extends Model
{
    protected $fillable = ['nombre', 'valor', 'fechavigencia', 'codigo'];
    protected $table = 'impuesto';

}
