<?php

namespace App\Models\Solicitudpago;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Sector_Solicitudpago extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'sector_solicitudpago';

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];
}
