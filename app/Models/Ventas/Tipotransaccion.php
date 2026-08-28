<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Ventas\TipotransaccionTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tipotransaccion extends Model
{
    use SoftDeletes;
	use TipotransaccionTrait;

    protected $fillable = ['nombre', 'operacion', 'operacionstock', 'abreviatura', 'codigo', 'signo', 'estado'];
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

    public function getDescOperacionstockAttribute()
    {
        return Arr::get(TipotransaccionTrait::$enumOperacionStock, $this->operacionstock);
    }

    public function esNotaCredito(): bool
    {
        return ($this->operacion ?? '') === 'C';
    }
}

