<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Aprobacion_Indumentaria_Nivel_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'aprobacion_indumentaria_nivel_sueldos';

    protected $fillable = [
        'empresa_id',
        'agrupamiento_id',
        'nivel',
        'usuario_id',
        'orden',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'agrupamiento_id' => 'integer',
        'nivel' => 'integer',
        'usuario_id' => 'integer',
        'orden' => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
