<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Stock\Mventa;
use App\Models\Seguridad\Usuario;
use App\Traits\Ventas\RemitoTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Remito extends Model implements Auditable
{
    use RemitoTrait;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'fecha', 'fechaentrega', 'cliente_id', 'condicionventa_id', 'vendedor_id', 'transporte_id',
        'mventa_id', 'zonavta_id', 'cliente_entrega_id', 'lugarentrega', 'estado', 'estadoremito',
        'usuario_id', 'leyenda', 'descuento', 'descuentointegrado', 'codigo', 'tipocomprobante',
        'letra', 'puntoventa_id', 'numero', 'pedido_id', 'venta_id', 'origen', 'oblea',
    ];

    protected $table = 'remito';

    protected $casts = [
        'fecha' => 'datetime:d-m-Y',
        'fechaentrega' => 'datetime',
    ];

    public function remito_articulos()
    {
        return $this->hasMany(Remito_Articulo::class, 'remito_id')
            ->with('articulos')
            ->with('lotes')
            ->with('descuentoventa_ids');
    }

    public function clientes()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id')->with('tipossuspensioncliente');
    }

    public function condicionesdeventa()
    {
        return $this->belongsTo(Condicionventa::class, 'condicionventa_id');
    }

    public function mventas()
    {
        return $this->belongsTo(Mventa::class, 'mventa_id');
    }

    public function vendedores()
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_id');
    }

    public function transportes()
    {
        return $this->belongsTo(Transporte::class, 'transporte_id');
    }

    public function zonavtas()
    {
        return $this->belongsTo(Zonavta::class, 'zonavta_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cliente_entregas()
    {
        return $this->belongsTo(Cliente_Entrega::class, 'cliente_entrega_id');
    }

    public function pedidos()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function ventas()
    {
        return $this->belongsTo(Venta::class, 'venta_id')->with('cliente_cuentacorrientes');
    }

    public function puntoventas()
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_id');
    }

    public function getEntregaNombreAttribute()
    {
        $data = Cliente_Entrega::find($this->cliente_entrega_id);

        return $data ? $data->nombre : '';
    }
}
