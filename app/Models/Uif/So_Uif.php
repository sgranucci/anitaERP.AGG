<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Uif\So_UifTrait;

class So_Uif extends Model
{
    use So_UifTrait;
    protected $fillable = ['nombre', 'riesgo', 'puntaje'];
    protected $table = 'so_uif';

}
