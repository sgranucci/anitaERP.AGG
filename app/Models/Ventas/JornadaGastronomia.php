<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class JornadaGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_ABIERTA = 'abierta';

    public const ESTADO_CERRADA = 'cerrada';

    protected $table = 'jornada_gastronomia';

    protected $fillable = [
        'empresa_id',
        'fecha_jornada',
        'estado',
        'usuario_apertura_id',
        'usuario_cierre_id',
        'apertura_en',
        'cierre_en',
        'observacion_apertura',
        'observacion_cierre',
    ];

    protected $casts = [
        'fecha_jornada' => 'date',
        'apertura_en' => 'datetime',
        'cierre_en' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuarioApertura()
    {
        return $this->belongsTo(Usuario::class, 'usuario_apertura_id');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(Usuario::class, 'usuario_cierre_id');
    }

    public function cierreTotem()
    {
        return $this->hasOne(CierreTotemJornadaGastronomia::class, 'jornada_gastronomia_id');
    }
}
