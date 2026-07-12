<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Salida;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Tipotransaccion_Stock;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConfiguracionTerminalVianda extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'configuracion_terminal_vianda';

    protected $fillable = [
        'identificador_pc',
        'descripcion',
        'ubicacion_id',
        'empresa_id',
        'deposito_platos_id',
        'deposito_insumos_id',
        'salida_voucher_id',
        'listaprecio_venta_id',
        'tipotransaccion_stock_id',
        'estado',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function ubicacion()
    {
        return $this->belongsTo(UbicacionGastronomia::class, 'ubicacion_id');
    }

    public function depositoPlatos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_platos_id');
    }

    public function depositoInsumos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_insumos_id');
    }

    public function salidaVoucher()
    {
        return $this->belongsTo(Salida::class, 'salida_voucher_id');
    }

    public function listaprecioVenta()
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_venta_id');
    }

    public function tipotransaccion()
    {
        return $this->belongsTo(Tipotransaccion_Stock::class, 'tipotransaccion_stock_id');
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            'A' => 'Activo',
            'I' => 'Inactivo',
            default => (string) $this->estado,
        };
    }

    /**
     * Configuración de la terminal (identificador_pc) resuelta desde la request.
     */
    public static function resolverPorTerminal(string $identificadorPc): ?self
    {
        if (trim($identificadorPc) === '') {
            return null;
        }

        return static::query()
            ->with(['empresa', 'ubicacion', 'depositoPlatos', 'depositoInsumos', 'salidaVoucher', 'listaprecioVenta', 'tipotransaccion'])
            ->where('identificador_pc', $identificadorPc)
            ->where('estado', 'A')
            ->orderBy('id')
            ->first();
    }
}
