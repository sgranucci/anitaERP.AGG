<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Lsd_Presentacion_Registro_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'lsd_presentacion_registro_sueldos';

    protected $fillable = [
        'presentacion_id',
        'tipo_registro',
        'nro_linea',
        'cuil',
        'contenido',
        'contenido_override',
        'estado_linea',
        'mensaje',
    ];

    protected $casts = [
        'presentacion_id' => 'integer',
        'nro_linea' => 'integer',
    ];

    public function presentacion()
    {
        return $this->belongsTo(Lsd_Presentacion_Sueldos::class, 'presentacion_id');
    }

    public function lineaEfectiva(): string
    {
        $ov = trim((string) ($this->contenido_override ?? ''));

        return $ov !== '' ? $ov : (string) $this->contenido;
    }
}
