<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Uif\So_UifTrait;
use OwenIt\Auditing\Contracts\Auditable;

class So_Uif extends Model implements Auditable
{
    use So_UifTrait;
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'riesgo', 'puntaje'];
    protected $table = 'so_uif';

}
