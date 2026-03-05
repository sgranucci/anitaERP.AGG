<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Uif\Juego_UifTrait;

class Juego_Uif extends Model
{
    use Juego_UifTrait;
    protected $fillable = ['nombre', 'riesgo', 'puntaje'];
    protected $table = 'juego_uif';

}
