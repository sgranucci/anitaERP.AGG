<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use App\Traits\Uif\Provincia_UifTrait;

class Provincia_Uif extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use Provincia_UifTrait;

    protected $fillable = ['nombre', 'riesgo', 'puntaje', 'codigo'];
    protected $table = 'provincia_uif';

}
