<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Grupoproducto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'grupoproducto';

    protected $fillable = [
        'codigo_interno_sifab',
        'codigo',
        'linea_id',
        'nombre',
        'habilitado',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
    ];

    public function lineas()
    {
        return $this->belongsTo(Linea::class, 'linea_id');
    }
}
