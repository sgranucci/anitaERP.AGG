<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ValeClienteLocal extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPO_VAL = 'VAL';

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_APLICADO = 'aplicado';

    public const ESTADO_ANULADO = 'anulado';

    protected $table = 'vale_cliente_local';

    protected $fillable = [
        'local_venta_id',
        'cliente_id',
        'tipo_documento',
        'nro_documento',
        'nombre',
        'tipo',
        'importe_original',
        'saldo',
        'estado',
        'venta_origen_id',
        'venta_aplicacion_id',
        'turno_operativo_local_id',
        'usuario_id',
        'observacion',
    ];

    protected $casts = [
        'importe_original' => 'float',
        'saldo' => 'float',
    ];

    public function localVenta()
    {
        return $this->belongsTo(LocalVenta::class, 'local_venta_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ventaOrigen()
    {
        return $this->belongsTo(Venta::class, 'venta_origen_id');
    }
}
