<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Usocuentacaja extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre'];

    protected $table = 'usocuentacaja';

    public function cuentacajas()
    {
        return $this->belongsToMany(Cuentacaja::class, 'cuentacaja_usocuentacaja', 'usocuentacaja_id', 'cuentacaja_id');
    }
}
