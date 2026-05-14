<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Conceptogasto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre'];

    protected $table = 'conceptogasto';

    public function conceptogasto_cuentacontables()
    {
        return $this->hasMany(Conceptogasto_Cuentacontable::class)->with('cuentacontables');
    }
}
