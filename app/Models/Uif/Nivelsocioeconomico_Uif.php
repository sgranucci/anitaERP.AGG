<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

class Nivelsocioeconomico_Uif extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'abreviatura'];
    protected $table = 'nivelsocioeconomico_uif';

}
