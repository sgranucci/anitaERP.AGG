<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Lineamaterial extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'lineamaterial';

    protected $fillable = [
        'codigo_interno_sifab',
        'codigo',
        'nombre',
        'habilitado',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
    ];
}
