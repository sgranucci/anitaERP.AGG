<?php

namespace App\Models\Configuracion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class BitacoraAcceso extends Model
{
    public $timestamps = false;

    protected $table = 'bitacora_acceso';

    protected $fillable = [
        'usuario_id',
        'usuario_nombre',
        'empresa_id',
        'rol_id',
        'session_id',
        'tipo',
        'metodo',
        'ruta',
        'nombre_ruta',
        'url',
        'status',
        'duracion_ms',
        'memoria_pico_kb',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'empresa_id' => 'integer',
        'rol_id' => 'integer',
        'status' => 'integer',
        'duracion_ms' => 'integer',
        'memoria_pico_kb' => 'integer',
        'created_at' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
