<?php

namespace App\Models\Caja;

use App\Traits\Caja\Tipotransaccion_CajaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Tipotransaccion_Caja extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;
    use Tipotransaccion_CajaTrait;

    protected $fillable = ['nombre', 'operacion', 'abreviatura', 'signo', 'estado'];

    protected $table = 'tipotransaccion_caja';

    public function setSignoAttribute($signo)
    {
        switch (Tipotransaccion_CajaTrait::$enumSigno[$signo]) {
            case 'Ingreso':
                $this->attributes['signo'] = 1;
                break;
            case 'Egreso':
                $this->attributes['signo'] = -1;
                break;
        }
    }

    public function getSignoAttribute($signo)
    {
        switch ($signo) {
            case 1:
            case 0:
                $retSigno = 'I';
                break;
            case -1:
                $retSigno = 'E';
                break;
        }

        return $retSigno;
    }
}
