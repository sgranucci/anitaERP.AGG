<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Uif\Inusualidad_UifTrait;

class Inusualidad_Uif extends Model
{
    use Inusualidad_UifTrait;
    protected $fillable = ['nombre', 'riesgo', 'puntaje'];
    protected $table = 'inusualidad_uif';

}
