<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;

class Profesion_Uif extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'profesion_uif';

}
