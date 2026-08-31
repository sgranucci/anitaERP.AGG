<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CuentaGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPO_MESA = 'mesa';

    public const TIPO_CUENTA = 'cuenta';

    public const ORIGEN_SALON = 'salon';

    public const ORIGEN_CANJE_MARKETING = 'canje_marketing';

    /** Factura importada desde cabecera Informix (POS Anita legacy, fuera del flujo ERP). */
    public const ORIGEN_IMPORT_ANITA = 'import_anita';

    public const ESTADO_ABIERTA = 'abierta';

    public const ESTADO_CERRADA = 'cerrada';

    public const ESTADO_FACTURADA = 'facturada';

    protected $table = 'cuenta_gastronomia';

    protected $fillable = [
        'tipo', 'origen_pos', 'empresa_id', 'mesa_gastronomia_id', 'mozo_gastronomia_id', 'cubiertos',
        'estado', 'identificador_pc', 'cliente_id', 'descuento_gastronomia_id', 'cliente_interno_descuento_id',
        'cliente_vip_gastronomia_id',
        'factura_receptor_nombre', 'factura_receptor_documento', 'factura_receptor_domicilio',
        'factura_receptor_tipodocumento_id',
        'configuracion_puntoventa_gastronomia_id', 'venta_id', 'waitry_order_id', 'waitry_display_id', 'waitry_cobro_totem', 'waitry_tipo_pago',
        'waitry_monto_cobro',
        'canje_premio_pendiente',
        'canje_fidelidad_pendiente',
    ];

    protected $casts = [
        'waitry_cobro_totem' => 'boolean',
        'waitry_monto_cobro' => 'float',
        'canje_premio_pendiente' => 'array',
        'canje_fidelidad_pendiente' => 'array',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function mesa()
    {
        return $this->belongsTo(MesaGastronomia::class, 'mesa_gastronomia_id');
    }

    public function mozo()
    {
        return $this->belongsTo(MozoGastronomia::class, 'mozo_gastronomia_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function descuentoGastronomia()
    {
        return $this->belongsTo(DescuentoGastronomia::class, 'descuento_gastronomia_id');
    }

    /** Quien invita / centro de costo de la invitación (no es el cliente de la factura). */
    public function clienteInternoDescuento()
    {
        return $this->belongsTo(Cliente::class, 'cliente_interno_descuento_id');
    }

    /** Cliente VIP beneficiario del canje marketing (no es el cliente de la factura). */
    public function clienteVip()
    {
        return $this->belongsTo(ClienteVipGastronomia::class, 'cliente_vip_gastronomia_id');
    }

    public function esCanjeMarketing(): bool
    {
        return (string) ($this->origen_pos ?? self::ORIGEN_SALON) === self::ORIGEN_CANJE_MARKETING;
    }

    public function configuracionPuntoventa()
    {
        return $this->belongsTo(ConfiguracionPuntoventaGastronomia::class, 'configuracion_puntoventa_gastronomia_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function lineas()
    {
        return $this->hasMany(CuentaGastronomiaLinea::class, 'cuenta_gastronomia_id')->orderBy('numero_linea');
    }

    public function tieneCanjePremioPendiente(): bool
    {
        $pendiente = $this->canje_premio_pendiente;

        return is_array($pendiente) && trim((string) ($pendiente['numerocupon'] ?? '')) !== '';
    }

    public function tieneCanjeFidelidadPendiente(): bool
    {
        $pendiente = $this->canje_fidelidad_pendiente;

        return is_array($pendiente) && trim((string) ($pendiente['trackdata'] ?? '')) !== '';
    }

    public function tieneCanjePendienteRequiereFacturacionConDescuento(): bool
    {
        return $this->tieneCanjePremioPendiente() || $this->tieneCanjeFidelidadPendiente();
    }
}
