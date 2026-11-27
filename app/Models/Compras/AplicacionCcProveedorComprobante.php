<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class AplicacionCcProveedorComprobante extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_EMITIDO = 'EMITIDO';

    public const ESTADO_ANULADO = 'ANULADO';

    public const ORIGEN_ERP = 'ERP';

    public const ORIGEN_HISTORICO = 'HISTORICO';

    public const PREFIJO = 'APL';

    protected $table = 'aplicacion_cc_proveedor_comprobante';

    protected $fillable = [
        'empresa_id', 'proveedor_id', 'numero', 'fecha', 'estado', 'moneda_id',
        'total_moneda', 'total', 'diferencia_cambio', 'cantidad_lineas', 'detalle',
        'observacion', 'origen', 'usuario_id', 'anulado_at', 'anulado_usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'anulado_at' => 'datetime',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function anulado_usuarios()
    {
        return $this->belongsTo(Usuario::class, 'anulado_usuario_id');
    }

    public function lineas()
    {
        return $this->hasMany(AplicacionCcProveedorComprobanteLinea::class, 'comprobante_id')->orderBy('id');
    }

    public function estaAnulado(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }

    public static function formatearNumero(int|string|null $numero): string
    {
        return self::PREFIJO.' '.str_pad((string) (int) $numero, 8, '0', STR_PAD_LEFT);
    }

    public function numeroFormateado(): string
    {
        return self::formatearNumero($this->numero);
    }
}
