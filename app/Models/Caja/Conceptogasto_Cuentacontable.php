<?php

namespace App\Models\Caja;

use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Conceptogasto_Cuentacontable extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['conceptogasto_id', 'cuentacontable_id'];

    protected $table = 'conceptogasto_cuentacontable';

    public function conceptogastos()
    {
        return $this->belongsTo(Conceptogasto::class, 'conceptogasto_id');
    }

    public function cuentacontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id', 'id');
    }
}
