<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Validacion_Abono_Plantilla extends Model
{
    protected $table = 'validacion_abono_plantilla';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function preguntas()
    {
        return $this->hasMany(Validacion_Abono_Plantilla_Pregunta::class, 'validacion_abono_plantilla_id')
            ->orderBy('orden')
            ->orderBy('id');
    }
}
