<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use App\Models\Configuracion\Empresa;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Concepto_Ivacompra;

class Precarga_Comprobante_Proveedor_Concepto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['precarga_comprobante_proveedor_id', 'concepto_ivacompra_id', 'monto'];
    protected $table = 'precarga_comprobante_proveedor_concepto';

	public function precarga_comprobante_proveedor()
	{
    	return $this->belongsToy(Precarga_Comprobante_Proveedor::class, 'precarga_comprobante_proveedor_id');
	}

    public function concepto_ivacompras()
    {
        return $this->belongsTo(Concepto_Ivacompra::class, 'tipotransaccion_compra_id');
    }    
}
