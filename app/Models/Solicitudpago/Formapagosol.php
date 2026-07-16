<?php

namespace App\Models\Solicitudpago;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Formapagosol extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'formapagosol';

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];
}
