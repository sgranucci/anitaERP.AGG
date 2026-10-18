<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

class Estadocivil_Uif extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre'];
    protected $table = 'estadocivil_uif';

}
