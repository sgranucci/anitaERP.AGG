<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Str;
use App\Models\Configuracion\Empresa;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Ventas\Venta;
use App\Models\Stock\MovimientoStock;
use App\Models\Caja\Cobranza;
use Auth;

class Asiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_APROBACION_CONFIRMADO = 'confirmado';

    public const ESTADO_APROBACION_PENDIENTE = 'pendiente';

    public const ESTADO_APROBACION_RECHAZADO = 'rechazado';

    protected $fillable = ['empresa_id', 'tipoasiento_id', 'numeroasiento', 'fecha', 'venta_id', 'movimientostock_id',
                            'cobranza_id',
                            'compra_id', 'caja_movimiento_id', 'ordencompra_id', 'recepcionproveedor_id',
                            'comprobante_proveedor_id', 'observacion',
                            'usuario_id', 'estado_aprobacion', 'cuentas_no_autorizadas',
                            'aprobador_id', 'aprobado_el', 'rechazado_el', 'motivo_rechazo'];

    protected $casts = [
        'aprobado_el' => 'datetime',
        'rechazado_el' => 'datetime',
    ];

    protected $table = 'asiento';

    public function asiento_movimientos()
	{
    	return $this->hasMany(Asiento_Movimiento::class, 'asiento_id')
                        ->with('cuentacontables')
                        ->with('centrocostos')
                        ->with('monedas');
	}

	public function asiento_archivos()
	{
    	return $this->hasMany(Asiento_Archivo::class, 'asiento_id');
	}

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function tipoasientos()
    {
        return $this->belongsTo(Tipoasiento::class, 'tipoasiento_id');
    }

    public function ventas()
    {
        return $this->belongsTo(Ventas::class, 'venta_id');
    }

    public function movimientostocks()
    {
        return $this->belongsTo(MovimientoStock::class, 'movimientostock_id');
    }

    public function cobranzas()
	{
    	return $this->belongsTo(Cobranza::class, 'id', 'caja_movimiento_id');
	}

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function aprobadores()
    {
        return $this->belongsTo(Usuario::class, 'aprobador_id');
    }

    public function estaPendienteAprobacion(): bool
    {
        return ($this->estado_aprobacion ?? self::ESTADO_APROBACION_CONFIRMADO) === self::ESTADO_APROBACION_PENDIENTE;
    }

    /** @return list<int> */
    public function cuentasNoAutorizadasIds(): array
    {
        if (empty($this->cuentas_no_autorizadas)) {
            return [];
        }

        $decoded = json_decode($this->cuentas_no_autorizadas, true);

        return is_array($decoded)
            ? array_values(array_filter(array_map('intval', $decoded), fn ($id) => $id > 0))
            : [];
    }

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }


}
