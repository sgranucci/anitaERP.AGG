<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;

class Estadocivil_Uif extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'estadocivil_uif';

}
