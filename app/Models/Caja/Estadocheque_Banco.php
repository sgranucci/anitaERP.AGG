<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Estadocheque_Banco extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'abreviatura', 'codigoexterno', 'banco_id'];

    protected $table = 'estadocheque_banco';

    public function bancos()
    {
        return $this->belongsTo(Banco::class, 'banco_id');
    }
}
