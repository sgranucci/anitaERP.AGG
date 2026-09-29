<?php

namespace App\Models\Contable;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Configuracion\Moneda;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Asiento_Movimiento extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'asiento_id', 'cuentacontable_id', 'centrocosto_id', 'monto', 'moneda_id',
        'cotizacion', 'observacion', 'comprobante_proveedor_id',
        'comprobante_proveedor_concepto_id', 'concepto_ivacompra_id',
        'anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro', 'nro_ordencompra',
    ];

    protected $table = 'asiento_movimiento';

	public function asientos()
	{
    	return $this->belongsTo(Asiento::class, 'asiento_id', 'id');
	}

	public function cuentacontables()
	{
    	return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
	}

	public function centrocostos()
	{
    	return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
	}
	
	public function monedas()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function comprobante_proveedor_conceptos()
    {
        return $this->belongsTo(Comprobante_Proveedor_Concepto::class, 'comprobante_proveedor_concepto_id');
    }

    public function concepto_ivacompras()
    {
        return $this->belongsTo(Concepto_Ivacompra::class, 'concepto_ivacompra_id');
    }

    /**
     * Comprobante original del renglón (Anita): «FIS A0004-00198310».
     */
    public function etiquetaComprobanteOrigen(): string
    {
        $nro = (int) ($this->anita_nro ?? 0);
        if ($nro <= 0) {
            return '';
        }

        $comp = MayorPlanoCuentaSupport::formatearComprobante(
            (string) ($this->anita_tipo ?? ''),
            (string) ($this->anita_letra ?? ' '),
            (int) ($this->anita_sucursal ?? 0),
            $nro,
        );
        $tipo = strtoupper(trim((string) ($this->anita_tipo ?? '')));

        return trim($tipo.' '.$comp);
    }
}
