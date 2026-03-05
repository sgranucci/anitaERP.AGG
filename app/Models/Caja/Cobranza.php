<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Contable\Asiento;
use Auth;

class Cobranza extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['empresa_id', 'tipotransaccion_caja_id', 'numerotransaccion', 'fecha', 
                            'caja_id', 'cliente_id', 'detalle', 'estado', 'monto', 'cotizacion', 'moneda_id',
                            'usuario_id'];

    protected $table = 'cobranza';

    public function caja_movimientos()
	{
    	return $this->hasMany(Caja_Movimiento::class, 'cobranza_id')
                        ->with('caja_movimiento_cuentacajas')
                        ->with('caja_movimiento_estados');
	}

    public function cobranza_estados()
	{
    	return $this->hasMany(Cobranza_Estado::class, 'cobranza_id');
	}

	public function cobranza_archivos()
	{
    	return $this->hasMany(Cobranza_Archivo::class, 'cobranza_id');
	}

	public function cobranza_comprobantes()
	{
    	return $this->hasMany(Cobranza_Comprobante::class, 'cobranza_id')->with('cliente_cuentacorrientes');
	}

	public function cobranza_retenciones()
	{
    	return $this->hasMany(Cobranza_Retencion::class, 'cobranza_id')->with('retencion_cobranzas');
	}

	public function cliente_cuentacorrientes()
	{
    	return $this->hasMany(Cliente_Cuentacorriente::class, 'cobranza_id');
	}

	public function cheques()
	{
    	return $this->hasMany(Cheque::class, 'cobranza_id')->with('monedas')->with('bancos');
	}

    public function asientos()
	{
    	return $this->belongsTo(Asiento::class, 'id', 'cobranza_id')->with('asiento_movimientos');
	}

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function tipotransaccioncajas()
    {
        return $this->belongsTo(Tipotransaccion_caja::class, 'tipotransaccion_caja_id');
    }

    public function clientes()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

}
