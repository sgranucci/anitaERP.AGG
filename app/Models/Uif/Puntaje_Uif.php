<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use App\Traits\Uif\Puntaje_UifTrait;

class Puntaje_Uif extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use Puntaje_UifTrait;

    protected $fillable = ['desdepuntaje', 'hastapuntaje', 'riesgo'];
    protected $table = 'puntaje_uif';

}
