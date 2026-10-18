<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use App\Models\Configuracion\Salida;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use App\Models\Caja\Caja;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Ventas\GastronomiaCrown\GastroPlanoMesa;
use App\Models\Ventas\UbicacionGastronomia;
use App\Support\Ventas\GastronomiaCrown\GastronomiaCrownPosModos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Contracts\Auditable;

class ConfiguracionPuntoventaGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'configuracion_puntoventa_gastronomia';

    protected $fillable = [
        'identificador_pc',
        'descripcion',
        'modo_pos',
        'politica_comanda',
        'solo_marcha',
        'habilita_hotel',
        'habilita_canje_gst',
        'pide_cubiertos',
        'pide_mozo_manual',
        'empresa_id',
        'puntoventa_cae_id',
        'puntoventa_caea_id',
        'ubicacion_id',
        'sala_id',
        'salida_comanda_id',
        'salida_comanda_b_id',
        'salida_comanda_k_id',
        'salida_factura_id',
        'salida_hotel_id',
        'listaprecio_id',
        'listaprecio_hotel_id',
        'deposito_venta_id',
        'deposito_insumos_id',
        'tipotransaccion_id',
        'tipotransaccion_nota_credito_id',
        'tipotransaccion_caja_id',
        'waitry_habilitado',
        'waitry_mesa_gastronomia_id',
    ];

    protected $casts = [
        'waitry_habilitado' => 'boolean',
        'solo_marcha' => 'boolean',
        'habilita_hotel' => 'boolean',
        'habilita_canje_gst' => 'boolean',
        'pide_cubiertos' => 'boolean',
        'pide_mozo_manual' => 'boolean',
    ];

    public function modoPosEfectivo(): string
    {
        $modo = (string) ($this->modo_pos ?? GastronomiaCrownPosModos::RESTO);

        return GastronomiaCrownPosModos::esModoValido($modo) ? $modo : GastronomiaCrownPosModos::RESTO;
    }

    /**
     * Waitry global (.env) y habilitado para esta terminal (CRUD configuración PV).
     */
    public function waitryHabilitadoEnTerminal(): bool
    {
        if (! config('waitry.habilitado', false)) {
            return false;
        }

        return (bool) ($this->waitry_habilitado ?? true);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function puntoventaCae()
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_cae_id');
    }

    public function puntoventaCaea()
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_caea_id');
    }

    public function ubicacion()
    {
        return $this->belongsTo(UbicacionGastronomia::class, 'ubicacion_id');
    }

    public function sala()
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function waitryMesa()
    {
        return $this->belongsTo(WaitryMesaGastronomia::class, 'waitry_mesa_gastronomia_id');
    }

    public function salidaComanda()
    {
        return $this->belongsTo(Salida::class, 'salida_comanda_id');
    }

    public function salidaComandaB()
    {
        return $this->belongsTo(Salida::class, 'salida_comanda_b_id');
    }

    public function salidaComandaK()
    {
        return $this->belongsTo(Salida::class, 'salida_comanda_k_id');
    }

    public function salidaFactura()
    {
        return $this->belongsTo(Salida::class, 'salida_factura_id');
    }

    public function salidaHotel()
    {
        return $this->belongsTo(Salida::class, 'salida_hotel_id');
    }

    public function listaprecio()
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_id');
    }

    public function listaprecioHotel()
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_hotel_id');
    }

    public function depositoVenta()
    {
        return $this->belongsTo(Depmae::class, 'deposito_venta_id');
    }

    public function depositoInsumos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_insumos_id');
    }

    public function tipotransaccion()
    {
        return $this->belongsTo(Tipotransaccion::class, 'tipotransaccion_id');
    }

    public function tipotransaccionNotaCredito()
    {
        return $this->belongsTo(Tipotransaccion::class, 'tipotransaccion_nota_credito_id');
    }

    public function tipotransaccionCaja()
    {
        return $this->belongsTo(Tipotransaccion_Caja::class, 'tipotransaccion_caja_id');
    }

    /**
     * Planos de mesas asignados a esta PC, en el orden en que se muestran en el POS.
     */
    public function planos(): BelongsToMany
    {
        return $this->belongsToMany(
            GastroPlanoMesa::class,
            'configuracion_puntoventa_gastronomia_plano',
            'configuracion_puntoventa_gastronomia_id',
            'gastro_plano_mesa_id'
        )->withPivot('orden')->orderByPivot('orden');
    }
}
