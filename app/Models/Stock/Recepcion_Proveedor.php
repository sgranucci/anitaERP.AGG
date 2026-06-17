<?php

namespace App\Models\Stock;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Asiento;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Recepcion_Proveedor extends Model
{
    public const TIPO_RECEPCION = 'RECEPCION';

    public const TIPO_DEVOLUCION = 'DEVOLUCION';

    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_CONFIRMADA = 'CONFIRMADA';

    public const ESTADO_ANULADA = 'ANULADA';

    protected $table = 'recepcion_proveedor';

    protected $fillable = [
        'ordencompra_id', 'tipo', 'recepcion_referencia_id', 'empresa_id', 'proveedor_id', 'deposito_id',
        'fecha', 'numerorecepcion', 'numerofactura', 'moneda_id', 'cotizacion', 'estado',
        'fl_precio_diferencia', 'comentario_precio', 'observacion', 'asiento_id', 'movimientostock_id',
        'fl_diferencia_cantidad', 'fl_articulo_extra', 'fl_faltante_oc', 'fl_laboratorio', 'fl_linea_rechazada',
        'resumen_diferencias', 'resumen_rechazos',
        'anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro', 'origen_carga', 'creousuario_id',
    ];

    protected $casts = [
        'fl_precio_diferencia' => 'boolean',
        'fl_linea_rechazada' => 'boolean',
        'fecha' => 'date',
        'numerorecepcion' => 'integer',
    ];

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function recepcion_referencia()
    {
        return $this->belongsTo(self::class, 'recepcion_referencia_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function depositos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function asientos()
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function movimientosstock()
    {
        return $this->belongsTo(MovimientoStock::class, 'movimientostock_id');
    }

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function recepcion_proveedor_articulos()
    {
        return $this->hasMany(Recepcion_Proveedor_Articulo::class, 'recepcion_proveedor_id');
    }

    public function recepcion_proveedor_estados()
    {
        return $this->hasMany(Recepcion_Proveedor_Estado::class, 'recepcion_proveedor_id');
    }

    public function recepcion_proveedor_archivos()
    {
        return $this->hasMany(Recepcion_Proveedor_Archivo::class, 'recepcion_proveedor_id');
    }

    public function recepcion_proveedor_partes_unicas()
    {
        return $this->hasMany(Recepcion_Proveedor_ParteUnica::class, 'recepcion_proveedor_id');
    }
}
