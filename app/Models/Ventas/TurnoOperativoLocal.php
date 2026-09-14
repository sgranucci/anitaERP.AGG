<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TurnoOperativoLocal extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_ABIERTO = 'abierto';

    public const ESTADO_CERRADO = 'cerrado';

    protected $table = 'turno_operativo_local';

    protected $fillable = [
        'local_venta_id',
        'turno_local_id',
        'identificador_pc',
        'estado',
        'usuario_apertura_id',
        'apertura_en',
        'fondo_inicial',
        'observacion_apertura',
        'usuario_cierre_id',
        'cierre_en',
        'sobrante_faltante',
        'medios_contado_cierre_json',
        'observacion_cierre',
        'monto_facturacion_turno',
    ];

    protected $casts = [
        'apertura_en' => 'datetime',
        'cierre_en' => 'datetime',
        'fondo_inicial' => 'float',
        'sobrante_faltante' => 'float',
        'monto_facturacion_turno' => 'float',
        'medios_contado_cierre_json' => 'array',
    ];

    public function localVenta()
    {
        return $this->belongsTo(LocalVenta::class, 'local_venta_id');
    }

    public function turnoLocal()
    {
        return $this->belongsTo(TurnoLocal::class, 'turno_local_id');
    }

    public function usuarioApertura()
    {
        return $this->belongsTo(Usuario::class, 'usuario_apertura_id');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(Usuario::class, 'usuario_cierre_id');
    }

    public function emisiones()
    {
        return $this->hasMany(FacturacionLocalEmision::class, 'turno_operativo_local_id');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === self::ESTADO_ABIERTO;
    }
}
