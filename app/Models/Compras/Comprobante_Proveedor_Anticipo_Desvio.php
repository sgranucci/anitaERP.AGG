<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Comprobante_Proveedor_Anticipo_Desvio extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_PENDIENTE = 'pendiente_aprobacion';

    public const ESTADO_APROBADA = 'aprobada';

    public const ESTADO_CONTABILIZADO = 'contabilizado';

    public const ESTADO_RECHAZADA = 'rechazada';

    protected $table = 'comprobante_proveedor_anticipo_desvio';

    protected $fillable = [
        'comprobante_proveedor_id',
        'ordencompra_id',
        'empresa_id',
        'proveedor_id',
        'pct_anticipo_oc',
        'total_oc',
        'importe_esperado',
        'importe_ingresado',
        'desvio_pct',
        'tolerancia_pct',
        'estado',
        'usuario_aprobacion_id',
        'aprobado_at',
        'motivo',
    ];

    protected $casts = [
        'pct_anticipo_oc' => 'float',
        'total_oc' => 'float',
        'importe_esperado' => 'float',
        'importe_ingresado' => 'float',
        'desvio_pct' => 'float',
        'tolerancia_pct' => 'float',
        'aprobado_at' => 'datetime',
    ];

    public function comprobantes()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function usuariosAprobacion()
    {
        return $this->belongsTo(Usuario::class, 'usuario_aprobacion_id');
    }
}
