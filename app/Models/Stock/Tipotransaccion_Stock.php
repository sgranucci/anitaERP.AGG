<?php

namespace App\Models\Stock;

use App\Traits\Stock\Tipotransaccion_StockTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tipotransaccion_Stock extends Model
{
    use SoftDeletes;
    use Tipotransaccion_StockTrait;

    protected $fillable = ['nombre', 'operacion', 'abreviatura', 'signo', 'estado'];

    protected $table = 'tipotransaccion_stock';

    public function setSignoAttribute($signo)
    {
        switch (Tipotransaccion_StockTrait::$enumSigno[$signo]) {
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
        switch ($signo) {
            case 1:
                $retSigno = 'S';
                break;
            case -1:
                $retSigno = 'R';
                break;
            default:
                $retSigno = 'S';
        }

        return $retSigno;
    }
}
