<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Uif\Juego_UifTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Juego_Uif extends Model implements Auditable
{
    use Juego_UifTrait;
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'riesgo', 'puntaje'];
    protected $table = 'juego_uif';

}
