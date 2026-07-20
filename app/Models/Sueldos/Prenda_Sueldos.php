<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;

class Prenda_Sueldos extends Model
{
    protected $table = 'prenda_sueldos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'marca',
        'es_seguridad',
        'vida_util_meses',
        'requiere_certificacion',
        'norma',
        'porcentaje_pedido',
        'activo',
        'orden',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'es_seguridad' => 'boolean',
        'vida_util_meses' => 'integer',
        'requiere_certificacion' => 'boolean',
        'porcentaje_pedido' => 'decimal:2',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function variantes()
    {
        return $this->hasMany(Prenda_Articulo_Sueldos::class, 'prenda_id');
    }
}
