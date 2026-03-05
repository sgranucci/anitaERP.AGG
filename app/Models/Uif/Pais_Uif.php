<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use App\Traits\Uif\Pais_UifTrait;

class Pais_Uif extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use Pais_UifTrait;

    protected $fillable = ['nombre', 'riesgo', 'puntaje', 'codigo'];
    protected $table = 'pais_uif';

}
