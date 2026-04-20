<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use App\Traits\Uif\Profesion_UifTrait;

class Profesion_Uif extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use Profesion_UifTrait;

    protected $fillable = ['nombre', 'riesgo', 'puntaje', 'codigo'];
    protected $table = 'profesion_uif';

}
