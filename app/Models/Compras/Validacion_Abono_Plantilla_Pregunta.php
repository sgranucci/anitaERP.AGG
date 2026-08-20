<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Validacion_Abono_Plantilla_Pregunta extends Model
{
    protected $table = 'validacion_abono_plantilla_pregunta';

    protected $fillable = [
        'validacion_abono_plantilla_id', 'codigo', 'orden', 'enunciado',
        'comentario_si_valor', 'es_tickets',
    ];

    protected $casts = [
        'es_tickets' => 'boolean',
        'orden' => 'integer',
    ];

    public function plantilla()
    {
        return $this->belongsTo(Validacion_Abono_Plantilla::class, 'validacion_abono_plantilla_id');
    }
}
