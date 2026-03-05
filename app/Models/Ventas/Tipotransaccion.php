<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Ventas\TipotransaccionTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tipotransaccion extends Model
{
    use SoftDeletes;
	use TipotransaccionTrait;

    protected $fillable = ['nombre', 'operacion', 'abreviatura', 'codigo', 'signo', 'estado'];
    protected $table = 'tipotransaccion';

    public function setSignoAttribute($signo)
    {
        switch(TipotransaccionTrait::$enumSigno[$signo])
        {
        case 'Suma':
            $this->attributes['signo'] = 1;
            break;
        case 'Resta':
            $this->attributes['signo'] = -1;
            break;
        }
    }

    public function getSignoAttribute($signo)
    {
        switch($signo)
        {
        case 1:
            $retSigno = 'S';
            break;
        case -1:
            $retSigno = 'R';
            break;
        }
        return $retSigno;
    }
}

