<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Vacacion_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'vacacion_sueldos';

    protected $fillable = [
        'codigo',
        'descripcion',
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];

    public function periodos()
    {
        return $this->hasMany(Vacacion_Periodo_Sueldos::class, 'vacacion_id')
            ->orderBy('nro_linea');
    }
}
