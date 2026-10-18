<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Uif\Monto_UifTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Monto_Uif extends Model implements Auditable
{
    use Monto_UifTrait;
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['desdemonto', 'hastamonto', 'riesgo', 'puntaje'];
    protected $table = 'monto_uif';

}
