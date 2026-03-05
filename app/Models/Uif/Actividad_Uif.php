<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Uif\Actividad_UifTrait;

class Actividad_Uif extends Model
{
    use Actividad_UifTrait;
    protected $fillable = ['nombre', 'riesgo', 'puntaje'];
    protected $table = 'actividad_uif';

}
