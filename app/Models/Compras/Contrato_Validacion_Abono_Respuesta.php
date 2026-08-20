<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Contrato_Validacion_Abono_Respuesta extends Model
{
    protected $table = 'contrato_validacion_abono_respuesta';

    protected $fillable = [
        'contrato_validacion_abono_id', 'pregunta_id', 'valor', 'comentario',
    ];

    public function validacion()
    {
        return $this->belongsTo(Contrato_Validacion_Abono::class, 'contrato_validacion_abono_id');
    }

    public function pregunta()
    {
        return $this->belongsTo(Validacion_Abono_Plantilla_Pregunta::class, 'pregunta_id');
    }
}
