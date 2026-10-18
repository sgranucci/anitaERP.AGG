<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Uif\Inusualidad_UifTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Inusualidad_Uif extends Model implements Auditable
{
    use Inusualidad_UifTrait;
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'riesgo', 'puntaje'];
    protected $table = 'inusualidad_uif';

}
