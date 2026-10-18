<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

class Seteosalida extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['usuario_id', 'salida_id', 'programa', 'disparar_al_grabar'];

    protected $casts = [
        'disparar_al_grabar' => 'boolean',
    ];
    protected $table = 'seteosalida';
    protected $keyField = 'id';

    public function salidas()
    {
        return $this->belongsTo(Salida::class, 'salida_id');
    }

}
