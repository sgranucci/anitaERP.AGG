<?php

namespace App\Models\Stock;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;

class Configuracion_RecepcionProveedor extends Model
{
    protected $table = 'configuracion_recepcion_proveedor';

    protected $fillable = [
        'empresa_id', 'activa_contabilidad',
        'cuentacontable_provision_facturas_id',
        'cuentacontable_factura_anticipada_id',
        'cuentacontable_anticipo_bienes_uso_id',
        'cuentacontable_proveedores_intangible_id',
    ];

    protected $casts = [
        'activa_contabilidad' => 'boolean',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontable_provision_facturas()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_provision_facturas_id');
    }

    public function cuentacontable_factura_anticipada()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_factura_anticipada_id');
    }

    public function cuentacontable_anticipo_bienes_uso()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_anticipo_bienes_uso_id');
    }

    public function cuentacontable_proveedores_intangible()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_proveedores_intangible_id');
    }
}
