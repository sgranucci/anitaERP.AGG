<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Uif\Actividad_UifTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Actividad_Uif extends Model implements Auditable
{
    use Actividad_UifTrait;
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'riesgo', 'puntaje'];
    protected $table = 'actividad_uif';

}
