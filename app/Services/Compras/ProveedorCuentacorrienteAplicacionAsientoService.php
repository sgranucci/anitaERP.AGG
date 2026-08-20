<?php

namespace App\Services\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Contable\Asiento;
use App\Models\Contable\Cuentacontable;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Compras\ProveedorCuentaContableMonedaSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionDcSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionFilaSupport;
use App\Support\Contable\AsientoReversoSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Asiento de diferencia de cambio al aplicar CC (P&L vs AP, sin ítem abierto).
 */
class ProveedorCuentacorrienteAplicacionAsientoService
{
    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly TipoasientoRepositoryInterface $tipoasientoRepository,
        private readonly AsientoReversoSupport $asientoReversoSupport,
    ) {}

    /**
     * @param  array{
     *   cruzada?:bool,
     *   valor_local_deuda?:float,
     *   valor_local_credito?:float,
     *   dc?:float
     * }|null  $liquidacion
     */
    public function generarSiCorresponde(
        Proveedor_Cuentacorriente $deuda,
        Proveedor_Cuentacorriente $credito,
        float $dc,
        string $fecha,
        ?array $liquidacion = null,
    ): ?int {
        $cruzada = (bool) ($liquidacion['cruzada'] ?? false);
        $deuda->loadMissing([
            'proveedores',
            'comprobante_proveedores.ordencompras.ordencompra_articulos',
            'comprobante_proveedores.proveedores',
            'comprobante_proveedores.tipotransaccion_compras',
        ]);
        $credito->loadMissing([
            'comprobante_proveedores.ordencompras.ordencompra_articulos',
            'comprobante_proveedores.proveedores',
            'comprobante_proveedores.tipotransaccion_compras',
            'pagoproveedores',
        ]);

        $proveedor = $deuda->proveedores ?? $credito->proveedores;
        $cuentaApDeuda = $this->resolverCuentaDeLado($deuda, $proveedor);
        $cuentaApCredito = $this->resolverCuentaDeLado($credito, $proveedor);
        $mismaCuenta = $cuentaApDeuda === $cuentaApCredito;

        if (! $cruzada && ! ProveedorCuentacorrienteAplicacionDcSupport::requiereAsiento($dc)) {
            return null;
        }
        if ($cruzada && $mismaCuenta && ! ProveedorCuentacorrienteAplicacionDcSupport::requiereAsiento($dc)) {
            return null;
        }

        $empresaId = (int) ($deuda->empresa_id ?: $credito->empresa_id);
        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fecha,
            PeriodoContableCierreSupport::MODULO_COMPRAS
        );

        $cuentaDcId = $this->resolverCuentaDc($cuentaApDeuda ?: $cuentaApCredito, $proveedor);
        $tipoAsiento = $this->resolverTipoAsiento();
        $monedaLocalId = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);
        $obs = $this->observacion($deuda, $credito, $dc, $cruzada);

        $payload = [
            'empresa_id' => $empresaId,
            'tipoasiento_id' => (int) $tipoAsiento->id,
            'fecha' => $fecha,
            'observacion' => $obs,
            'usuario_id' => Auth::id(),
            'alcance_cierre_contable' => PeriodoContableCierreSupport::MODULO_COMPRAS,
            'cuentacontable_ids' => [],
            'moneda_ids' => [],
            'centrocosto_ids' => [],
            'debes' => [],
            'haberes' => [],
            'cotizaciones' => [],
            'observaciones' => [],
        ];

        if ($cruzada) {
            $valorDeuda = round(abs((float) ($liquidacion['valor_local_deuda'] ?? 0)), 4);
            $valorCredito = round(abs((float) ($liquidacion['valor_local_credito'] ?? 0)), 4);
            $this->agregarLinea($payload, $cuentaApDeuda, $monedaLocalId, $valorDeuda, 0.0, $obs);
            $this->agregarLinea($payload, $cuentaApCredito, $monedaLocalId, 0.0, $valorCredito, $obs);
            if (ProveedorCuentacorrienteAplicacionDcSupport::requiereAsiento($dc)) {
                $importe = round(abs($dc), 4);
                $perdida = $dc > 0;
                $this->agregarLinea(
                    $payload,
                    $cuentaDcId,
                    $monedaLocalId,
                    $perdida ? $importe : 0.0,
                    $perdida ? 0.0 : $importe,
                    $obs
                );
            }
        } else {
            $importe = round(abs($dc), 4);
            $debeDc = ProveedorCuentacorrienteAplicacionDcSupport::esPerdida($dc);
            $this->agregarLinea(
                $payload,
                $cuentaDcId,
                $monedaLocalId,
                $debeDc ? $importe : 0.0,
                $debeDc ? 0.0 : $importe,
                $obs
            );
            $this->agregarLinea(
                $payload,
                $cuentaApDeuda,
                $monedaLocalId,
                $debeDc ? 0.0 : $importe,
                $debeDc ? $importe : 0.0,
                $obs
            );
        }

        $asiento = $this->asientoRepository->create($payload);
        if ($asiento === 'Error' || ! $asiento) {
            throw new RuntimeException('No se pudo grabar el asiento de diferencia de cambio.');
        }

        $asientoId = (int) $asiento->id;
        $this->asientoMovimientoRepository->create($payload, $asientoId);

        return $asientoId;
    }

    public function revertirSiCorresponde(?int $asientoId, string $fecha): void
    {
        if ($asientoId === null || $asientoId <= 0) {
            return;
        }

        $asiento = Asiento::query()->with('asiento_movimientos')->find($asientoId);
        if ($asiento === null) {
            return;
        }

        $this->asientoReversoSupport->generarDesdeAsiento(
            $asiento,
            $fecha,
            null,
            'Revierte DC aplicación CC'
        );
    }

    private function resolverCuentaDeLado(
        Proveedor_Cuentacorriente $lado,
        ?Proveedor $proveedor,
    ): int {
        $comprobante = $lado->comprobante_proveedores;
        if ($comprobante) {
            $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorDesdeComprobante(
                $comprobante,
                $proveedor ?? $comprobante->proveedores
            );
            if ($cuentaId > 0) {
                return $cuentaId;
            }
        }

        $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorId(
            $proveedor ?? $lado->proveedores,
            (int) ($lado->moneda_id ?: 1)
        );
        if ($cuentaId > 0) {
            return $cuentaId;
        }

        throw new RuntimeException(
            'El proveedor no tiene cuenta contable de proveedores para asentar la liquidación / diferencia de cambio.'
        );
    }

    private function resolverCuentaDc(int $cuentaApId, ?Proveedor $proveedor): int
    {
        $ids = [$cuentaApId];
        if ($proveedor) {
            $ids[] = (int) ($proveedor->cuentacontable_id ?? 0);
            $ids[] = (int) ($proveedor->cuentacontableme_id ?? 0);
            $ids[] = (int) ($proveedor->cuentacontablecompra_id ?? 0);
        }

        foreach (array_values(array_unique(array_filter($ids))) as $id) {
            $cuenta = Cuentacontable::query()->find($id);
            $dcId = (int) ($cuenta->cuentacontable_difcambio_id ?? 0);
            if ($dcId > 0 && $dcId !== $id) {
                return $dcId;
            }
        }

        throw new RuntimeException(
            'Falta la cuenta de diferencia de cambio en la cuenta de proveedores. Configúrela en el plan de cuentas (Dif. de cambio).'
        );
    }

    private function resolverTipoAsiento(): object
    {
        $tipo = $this->tipoasientoRepository->findPorAbreviatura('COM')
            ?? $this->tipoasientoRepository->findPorAbreviatura('TES');
        if ($tipo === null) {
            throw new RuntimeException('No existe tipo de asiento COM ni TES para la diferencia de cambio.');
        }

        return $tipo;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function agregarLinea(
        array &$payload,
        int $cuentaId,
        int $monedaId,
        float $debe,
        float $haber,
        string $observacion,
    ): void {
        $payload['cuentacontable_ids'][] = $cuentaId;
        $payload['moneda_ids'][] = $monedaId;
        $payload['centrocosto_ids'][] = 0;
        $payload['debes'][] = $debe > 0 ? $debe : '';
        $payload['haberes'][] = $haber > 0 ? $haber : '';
        $payload['cotizaciones'][] = 1;
        $payload['observaciones'][] = $observacion;
    }

    private function observacion(
        Proveedor_Cuentacorriente $deuda,
        Proveedor_Cuentacorriente $credito,
        float $dc,
        bool $cruzada = false,
    ): string {
        $etiDeuda = ProveedorCuentacorrienteAplicacionFilaSupport::etiqueta(
            $deuda,
            ProveedorCuentacorrienteAplicacionFilaSupport::tipo($deuda, 'deuda')
        );
        $etiCredito = ProveedorCuentacorrienteAplicacionFilaSupport::etiqueta(
            $credito,
            ProveedorCuentacorrienteAplicacionFilaSupport::tipo($credito, 'credito')
        );
        $prefijo = $cruzada ? 'Liquidación cruzada CC' : 'DC aplicación CC';
        $etiquetaDc = ProveedorCuentacorrienteAplicacionDcSupport::requiereAsiento($dc)
            ? ProveedorCuentacorrienteAplicacionDcSupport::etiqueta($dc)
            : 'sin DC';

        return sprintf(
            '%s %s / %s · %s %s',
            $prefijo,
            $etiCredito,
            $etiDeuda,
            number_format(abs($dc), 2, ',', '.'),
            $etiquetaDc
        );
    }
}
