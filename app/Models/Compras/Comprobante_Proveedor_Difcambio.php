<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Compras\Proveedor;
use App\Models\Contable\Asiento;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Comprobante_Proveedor_Difcambio extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_PENDIENTE = 'pendiente_aprobacion';

    public const ESTADO_APROBADA = 'aprobada';

    public const ESTADO_CONTABILIZADO = 'contabilizado';

    public const ESTADO_RECHAZADA = 'rechazada';

    protected $table = 'comprobante_proveedor_difcambio';

    protected $fillable = [
        'comprobante_proveedor_id',
        'recepcion_proveedor_id',
        'ordencompra_id',
        'empresa_id',
        'proveedor_id',
        'tc_recepcion',
        'tc_factura',
        'nominal_me',
        'importe_pesos',
        'porcentaje',
        'estado',
        'usuario_aprobacion_id',
        'aprobado_at',
        'asiento_id',
    ];

    protected $casts = [
        'tc_recepcion' => 'float',
        'tc_factura' => 'float',
        'nominal_me' => 'float',
        'importe_pesos' => 'float',
        'porcentaje' => 'float',
        'aprobado_at' => 'datetime',
    ];

    public function comprobantes()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function recepciones()
    {
        return $this->belongsTo(Recepcion_Proveedor::class, 'recepcion_proveedor_id');
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

    public function asientos()
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }
}
