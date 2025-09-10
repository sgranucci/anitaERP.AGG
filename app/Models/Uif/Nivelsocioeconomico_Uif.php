<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;

class Nivelsocioeconomico_Uif extends Model
{
    protected $fillable = ['nombre', 'abreviatura'];
    protected $table = 'nivelsocioeconomico_uif';

}
