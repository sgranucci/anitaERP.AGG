<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Uif\Frecuencia_UifTrait;

class Frecuencia_Uif extends Model
{
    use Frecuencia_UifTrait;
    protected $fillable = ['desdeoperacion', 'hastaoperacion', 'riesgo', 'puntaje'];
    protected $table = 'frecuencia_uif';

}
