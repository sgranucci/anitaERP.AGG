<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Ordencompra_Archivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'ordencompra_archivo';

    protected $fillable = ['ordencompra_id', 'nombrearchivo'];

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }
}
