<?php

namespace App\Services\Ventas\Gastronomia\CanjeMarketing;

use App\Models\Ventas\CanjeMarketingEntregaGastronomia;
use App\Models\Ventas\ClienteVipGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Services\Ventas\Gastronomia\GastronomiaFacturaEmisionService;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class CanjeMarketingFacturaEmisionService
{
    public function __construct(
        private readonly GastronomiaFacturaEmisionService $facturaEmisionService,
        private readonly CanjeMarketingCuentaService $canjeMarketingCuentaService,
    ) {
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null}>  $mediosPago
     * @return array<string, mixed>
     */
    public function emitirFacturaDesdeCuenta(
        CuentaGastronomia $cuenta,
        int $monedaId,
        ?int $actividadArcaId = null,
        bool $forzarPvCaea = false,
        array $mediosPago = [],
    ): array {
        $this->canjeMarketingCuentaService->exigirCuentaCanjeMarketing((int) $cuenta->id);

        $cuenta->loadMissing(['clienteVip', 'descuentoGastronomia', 'mozo']);

        if ((int) ($cuenta->descuento_gastronomia_id ?? 0) <= 0) {
            return ['error' => 'Debe aplicar el descuento de canje marketing antes de facturar.'];
        }

        if ((int) ($cuenta->cliente_vip_gastronomia_id ?? 0) <= 0) {
            return ['error' => 'Debe indicar el cliente del descuento (VIP) del canje.'];
        }

        $codigoEsperado = trim((string) config('gastronomia.canje_marketing_descuento_codigo', '40'));
        $codigoActual = trim((string) ($cuenta->descuentoGastronomia?->codigo ?? ''));
        if ($codigoActual !== $codigoEsperado) {
            return ['error' => 'El descuento de la cuenta no corresponde al canje marketing (código '.$codigoEsperado.').'];
        }

        $prevContabilidad = config('gastronomia.genera_contabilidad_al_facturar');
        config(['gastronomia.genera_contabilidad_al_facturar' => (bool) config('gastronomia.canje_marketing_genera_contabilidad_al_facturar', false)]);

        try {
            $resultado = $this->facturaEmisionService->emitirFacturaDesdeCuenta(
                $cuenta->fresh(['lineas.articulo', 'cliente', 'descuentoGastronomia', 'clienteVip', 'mozo', 'configuracionPuntoventa']),
                $monedaId,
                $actividadArcaId,
                $forzarPvCaea,
                $mediosPago,
                false,
                true,
            );
        } finally {
            config(['gastronomia.genera_contabilidad_al_facturar' => $prevContabilidad]);
        }

        if (! empty($resultado['error']) || empty($resultado['venta_id'])) {
            return $resultado;
        }

        $ventaId = (int) $resultado['venta_id'];
        $this->registrarEntregaTrasEmision($ventaId, $cuenta->fresh(['clienteVip', 'mozo', 'descuentoGastronomia']));

        return $resultado;
    }

    /**
     * @return list<string>
     */
    public function erroresPreflight(CuentaGastronomia $cuenta, int $monedaId): array
    {
        $this->canjeMarketingCuentaService->exigirCuentaCanjeMarketing((int) $cuenta->id);

        return $this->facturaEmisionService->erroresPreflightEmision($cuenta, $monedaId, [], true);
    }

    private function registrarEntregaTrasEmision(int $ventaId, CuentaGastronomia $cuenta): void
    {
        if (! Auth::check()) {
            throw new InvalidArgumentException('Usuario no autenticado para registrar canje marketing.');
        }

        $vipId = (int) ($cuenta->cliente_vip_gastronomia_id ?? 0);
        if ($vipId <= 0) {
            throw new InvalidArgumentException('Falta cliente VIP en la cuenta para registrar el canje.');
        }

        /** @var ClienteVipGastronomia $vip */
        $vip = ClienteVipGastronomia::query()->findOrFail($vipId);

        CanjeMarketingEntregaGastronomia::create([
            'venta_id' => $ventaId,
            'cuenta_gastronomia_id' => $cuenta->id,
            'cliente_vip_gastronomia_id' => $vipId,
            'mozo_gastronomia_id' => $cuenta->mozo_gastronomia_id,
            'empresa_id' => (int) $cuenta->empresa_id,
            'descuento_gastronomia_id' => $cuenta->descuento_gastronomia_id,
            'identificador_pc' => GastronomiaIdentificadorPc::resolver(),
            'nrodocumento_vip' => (string) $vip->nrodocumento,
            'apellido_vip' => (string) $vip->apellido,
            'nombre_vip' => (string) $vip->nombre,
            'fechacanje' => Carbon::today()->toDateString(),
        ]);
    }

    public function previewTotalesParaCuenta(CuentaGastronomia $cuenta): array
    {
        return $this->facturaEmisionService->previewTotalesParaCuenta($cuenta);
    }
}
