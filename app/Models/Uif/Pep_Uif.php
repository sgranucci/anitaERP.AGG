<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Uif\Pep_UifTrait;

class Pep_Uif extends Model
{
    use Pep_UifTrait;
    protected $fillable = ['nombre', 'riesgo', 'puntaje'];
    protected $table = 'pep_uif';

}
