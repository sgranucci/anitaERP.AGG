<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Ivacompra_Condicioniva extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
                            'concepto_ivacompra_id', 'condicioniva_id'
                        ];
    protected $table = 'concepto_ivacompra_condicioniva';

    public function concepto_ivacompras()
    {
        return $this->belongsTo(Concepto_Ivacompra::class, 'concepto_ivacompra_id');
    }
}

