<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Uif\Monto_UifTrait;

class Monto_Uif extends Model
{
    use Monto_UifTrait;
    protected $fillable = ['desdemonto', 'hastamonto', 'riesgo', 'puntaje'];
    protected $table = 'monto_uif';

}
