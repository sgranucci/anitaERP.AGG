<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Compras\Concepto_IvacompraTrait;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Impuesto;

class Concepto_Ivacompra extends Model
{
    use Concepto_IvacompraTrait;

    protected $fillable = ['nombre', 'codigo', 'formula', 'columna_ivacompra_id', 'empresa_id', 
                            'cuentacontabledebe_id', 'cuentacontablehaber_id', 'tipoconcepto', 'retieneganancia', 
                            'retieneIIBB', 'provincia_id', 'impuesto_id', 'nombre_ia'];
    protected $table = 'concepto_ivacompra';

	public function concepto_ivacompra_condicionivas()
	{
    	return $this->hasMany(Concepto_Ivacompra_Condicioniva::class, 'concepto_ivacompra_id');
	}

    public function columna_ivacompras()
    {
        return $this->belongsTo(Columna_Ivacompra::class, 'columna_ivacompra_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontablesdebe()
    {
        return $this->belongsTo(CuentaContable::class, 'cuentacontabledebe_id');
    }

    public function cuentacontableshaber()
    {
        return $this->belongsTo(CuentaContable::class, 'cuentacontablehaber_id');
    }

    public function provincias()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function impuestos()
    {
        return $this->belongsTo(Impuesto::class, 'impuesto_id');
    }

    public function getDescTipoConceptoAttribute()
	{
        $nombreTipoConcepto = '';
        foreach (Concepto_Ivacompra::$enumTipoConcepto as $tipoconcepto)
        {
            if ($tipoconcepto['valor'] == $this->tipoconcepto)
                $nombreTipoConcepto = $tipoconcepto['nombre'];
        }
	  	return $nombreTipoConcepto;
	}

    public function getDescRetieneGananciaAttribute()
	{
        $nombreRetiene = '';
        foreach (Concepto_Ivacompra::$enumRetiene as $retiene)
        {
            if ($retiene['valor'] == $this->retieneganancia)
                $nombreRetiene = $retiene['nombre'];
        }
	  	return $nombreRetiene;
	}

    public function getDescRetieneiibbAttribute()
	{
        $nombreRetiene = '';
        foreach (Concepto_Ivacompra::$enumRetiene as $retiene)
        {
            if ($retiene['valor'] == $this->retieneIIBB)
                $nombreRetiene = $retiene['nombre'];
        }
	  	return $nombreRetiene;
	}
}
