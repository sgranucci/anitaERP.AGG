<?php

namespace App\Models\Stock;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transferencia_Mercaderia extends Model
{
    use SoftDeletes;

    protected $table = 'transferencia_mercaderia';

    protected $fillable = [
        'codigo',
        'lote',
        'empresa_id',
        'deposito_origen_id',
        'bien_uso_origen_id',
        'deposito_destino_id',
        'bien_uso_destino_id',
        'tipotransaccion_stock_id',
        'estado',
        'requiere_aprobacion',
        'usuario_origen_id',
        'usuario_destino_id',
        'usuario_aprobador_id',
        'movimientostock_salida_id',
        'movimientostock_entrada_id',
        'asiento_id',
        'fecha',
        'fecha_aprobacion',
        'observacion',
        'motivo_rechazo',
        'centrocosto_destino_id',
    ];

    protected $casts = [
        'lote' => 'integer',
        'requiere_aprobacion' => 'boolean',
        'fecha' => 'date',
        'fecha_aprobacion' => 'date',
    ];

    public function articulos()
    {
        return $this->hasMany(Transferencia_Mercaderia_Articulo::class, 'transferencia_mercaderia_id')->orderBy('item');
    }

    public function tokens()
    {
        return $this->hasMany(Transferencia_Mercaderia_Token::class, 'transferencia_mercaderia_id');
    }

    public function depositoOrigen()
    {
        return $this->belongsTo(Depmae::class, 'deposito_origen_id');
    }

    public function bienUsoOrigen()
    {
        return $this->belongsTo(\App\Models\Contable\BienUso::class, 'bien_uso_origen_id');
    }

    public function depositoDestino()
    {
        return $this->belongsTo(Depmae::class, 'deposito_destino_id');
    }

    public function centrocostoDestino()
    {
        return $this->belongsTo(\App\Models\Contable\Centrocosto::class, 'centrocosto_destino_id');
    }

    public function bienUsoDestino()
    {
        return $this->belongsTo(\App\Models\Contable\BienUso::class, 'bien_uso_destino_id');
    }

    public function tipotransaccion_stock()
    {
        return $this->belongsTo(Tipotransaccion_Stock::class, 'tipotransaccion_stock_id');
    }

    public function usuarioOrigen()
    {
        return $this->belongsTo(Usuario::class, 'usuario_origen_id');
    }

    public function usuarioDestino()
    {
        return $this->belongsTo(Usuario::class, 'usuario_destino_id');
    }

    public function usuarioAprobador()
    {
        return $this->belongsTo(Usuario::class, 'usuario_aprobador_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function asientos()
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }
}
