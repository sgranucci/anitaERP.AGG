<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class LoteBancarioLinea extends Model
{
    protected $table = 'lote_bancario_linea';

    protected $fillable = [
        'lote_bancario_id',
        'pagoproveedor_id',
        'proveedor_id',
        'proveedor_nombre',
        'cuit',
        'cbu',
        'alias_cbu',
        'monto_bruto',
        'monto_retenciones',
        'monto_neto',
        'referencia_op',
        'medio',
        'observacion',
    ];

    protected $casts = [
        'monto_bruto' => 'float',
        'monto_retenciones' => 'float',
        'monto_neto' => 'float',
    ];

    public function lote_bancarios()
    {
        return $this->belongsTo(LoteBancario::class, 'lote_bancario_id');
    }

    public function pagoproveedores()
    {
        return $this->belongsTo(Pagoproveedor::class, 'pagoproveedor_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }
}
