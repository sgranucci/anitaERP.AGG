<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Contable\CentrocostoTrait;

class Centrocosto extends Model
{
    use CentrocostoTrait;

    protected $fillable = ['nombre', 'codigo', 'abreviatura', 'tipoiva'];
    protected $table = 'centrocosto';
}
