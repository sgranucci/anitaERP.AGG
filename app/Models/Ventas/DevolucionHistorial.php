<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class DevolucionHistorial extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'devolucion_historial';

    protected $fillable = [
        'fecha',
        'origen',
        'motivo_devolucion_id',
        'motivo_codigo',
        'motivo_nombre',
        'vuelve_stock',
        'articulo_id',
        'sku',
        'descripcion',
        'combinacion_id',
        'talle_id',
        'color_id',
        'cantidad',
        'precio',
        'importe',
        'local_venta_id',
        'empresa_id',
        'venta_id',
        'venta_origen_id',
        'cambio_devolucion_id',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'motivo_devolucion_id' => 'integer',
        'vuelve_stock' => 'boolean',
        'articulo_id' => 'integer',
        'combinacion_id' => 'integer',
        'talle_id' => 'integer',
        'color_id' => 'integer',
        'cantidad' => 'float',
        'precio' => 'float',
        'importe' => 'float',
        'local_venta_id' => 'integer',
        'empresa_id' => 'integer',
        'venta_id' => 'integer',
        'venta_origen_id' => 'integer',
        'cambio_devolucion_id' => 'integer',
        'usuario_id' => 'integer',
    ];

    public function motivo()
    {
        return $this->belongsTo(MotivoDevolucion::class, 'motivo_devolucion_id');
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function localVenta()
    {
        return $this->belongsTo(LocalVenta::class, 'local_venta_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cambio()
    {
        return $this->belongsTo(CambioDevolucionMarketplace::class, 'cambio_devolucion_id');
    }
}
