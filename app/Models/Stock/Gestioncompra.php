<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Gestioncompra extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'gestioncompra';

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
