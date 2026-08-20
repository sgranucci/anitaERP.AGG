<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\ContratoValidacionAbonoEstados;
use Illuminate\Database\Eloquent\Model;

class Contrato_Validacion_Abono extends Model
{
    protected $table = 'contrato_validacion_abono';

    protected $fillable = [
        'ordencompra_id', 'recepcion_proveedor_id', 'comprobante_proveedor_id', 'plantilla_id',
        'estado', 'periodo_modalidad', 'periodo_desde', 'periodo_hasta', 'ingresos_informados',
        'snapshot_ingresos_json', 'usuario_id', 'confirmado_at', 'aviso_pendiente_enviado_at',
    ];

    protected $casts = [
        'periodo_desde' => 'date',
        'periodo_hasta' => 'date',
        'ingresos_informados' => 'integer',
        'snapshot_ingresos_json' => 'array',
        'confirmado_at' => 'datetime',
        'aviso_pendiente_enviado_at' => 'datetime',
    ];

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function recepcion_proveedores()
    {
        return $this->belongsTo(Recepcion_Proveedor::class, 'recepcion_proveedor_id');
    }

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function plantillas()
    {
        return $this->belongsTo(Validacion_Abono_Plantilla::class, 'plantilla_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function respuestas()
    {
        return $this->hasMany(Contrato_Validacion_Abono_Respuesta::class, 'contrato_validacion_abono_id');
    }

    public function estaCompleta(): bool
    {
        return strtoupper((string) $this->estado) === ContratoValidacionAbonoEstados::COMPLETA;
    }

    public function origen(): string
    {
        if ((int) ($this->recepcion_proveedor_id ?? 0) > 0) {
            return ContratoValidacionAbonoEstados::ORIGEN_RECEPCION;
        }

        return ContratoValidacionAbonoEstados::ORIGEN_FACTURA;
    }
}
