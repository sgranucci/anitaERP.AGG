<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use App\Models\Configuracion\Empresa;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Moneda;

class Precarga_Comprobante_Proveedor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['empresa_id', 'proveedor_id', 'identificacion_proveedor_cuit', 'tipotransaccion_compra_id', 'letra', 'sucursal', 
                            'numerocomprobante', 'fechafactura', 'fecharecepcionemail', 'fecharecepcionemail', 
                            'fechavencimientocaicae', 'fechavencimiento', 'numerocae', 'tipo_autorizacion', 'numeroordencompra', 'rutaalmacenamiento',
                            'pararevisar', 'subtotal', 'total', 'estado', 'anita_nro_interno', 'origen_entrada', 'moneda', 'moneda_id', 'cotizacion'];
    protected $table = 'precarga_comprobante_proveedor';

    protected $casts = [
        'fechafactura' => 'date',
        'fechavencimientocaicae' => 'date',
        'fechavencimiento' => 'date',
    ];

	public function precarga_comprobante_proveedor_conceptos()
	{
    	return $this->hasMany(Precarga_Comprobante_Proveedor_Concepto::class, 'precarga_comprobante_proveedor_id');
	}

	public function precarga_comprobante_proveedor_articulos()
	{
    	return $this->hasMany(Precarga_Comprobante_Proveedor_Articulo::class, 'precarga_comprobante_proveedor_id')
            ->orderBy('orden');
	}

    /**
     * Comprobante de proveedor generado desde esta precarga (si existe).
     */
    public function comprobante_proveedor()
    {
        return $this->hasOne(Comprobante_Proveedor::class, 'precarga_comprobante_proveedor_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function tipotransaccion_compras()
    {
        return $this->belongsTo(Tipotransaccion_Compra::class, 'tipotransaccion_compra_id');
    }    
}
