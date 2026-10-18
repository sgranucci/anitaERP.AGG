<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Caja extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['codigo', 'nombre', 'empresa_id', 'sala_id'];

    protected $table = 'caja';

    protected $casts = [
        'empresa_id' => 'integer',
        'sala_id' => 'integer',
    ];

    public function sala()
    {
        return $this->belongsTo(\App\Models\Configuracion\Sala::class, 'sala_id');
    }
}
