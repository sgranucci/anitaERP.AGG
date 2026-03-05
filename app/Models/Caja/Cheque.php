<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Configuracion\Tipodocumento;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Banco;
use App\Traits\Caja\ChequeTrait;

class Cheque extends Model implements Auditable
{
    use ChequeTrait;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [ 
            'origen', 'chequera_id', 'caracter', 'estado', 'fechaemision', 'fechapago', 'cuentacaja_id',
            'empresa_id', 'caja_id', 'caja_movimiento_id', 
            'cobranza_id',
            'numerocheque', 'moneda_id', 'monto', 'cotizacion', 'proveedor_id', 'cliente_id',
            'tipodocumento_id', 'numerodocumento', 'entregado', 'anombrede', 'estadocheque_banco_id', 
            'sucursalpago', 'tipodistribucion', 'banco_id', 'cuentalibradora'
                            ];
    protected $table = 'cheque';

    public function chequeras()
    {
        return $this->belongsTo(Chequera::class, 'chequera_id');
    }

    public function caja_movimientos()
    {
        return $this->belongsTo(Caja_Movimiento::class, 'caja_movimiento_id');
    }

    public function cobranzas()
	{
    	return $this->belongsTo(Cobranza::class, 'id', 'cobranza_id');
	}

    public function bancos()
    {
        return $this->belongsTo(Banco::class, 'banco_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacajas()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }

    public function cajas()
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function clientes()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function tipodocumentos()
    {
        return $this->belongsTo(Tipodocumento::class, 'tipodocumento_id');
    }
}

