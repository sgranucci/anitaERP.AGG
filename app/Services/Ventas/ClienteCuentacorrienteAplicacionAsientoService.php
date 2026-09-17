<?php

namespace App\Services\Ventas;

use App\Models\Contable\Asiento;
use App\Models\Contable\Cuentacontable;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionDcSupport;
use App\Support\Contable\AsientoReversoSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaEmisorSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Ventas\ClienteCuentacorrienteAplicacionFilaSupport;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Asiento al aplicar CC cliente (P&L vs AR).
 *
 * Motivos (combinables):
 * - Diferencia de cambio entre cotizaciones de deuda y crédito.
 * - Reclasificación si las cuentas AR (por código) no coinciden entre empresas.
 */
class ClienteCuentacorrienteAplicacionAsientoService
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
        Cliente_Cuentacorriente $deuda,
        Cliente_Cuentacorriente $credito,
        float $dc,
        string $fecha,
        ?array $liquidacion = null,
    ): ?int {
        $preview = $this->previsualizar($deuda, $credito, $dc, $fecha, $liquidacion);
        if ($preview === null) {
            return null;
        }

        $payload = $preview['payload'];
        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $payload['empresa_id'],
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_COBRANZA
        );

        $asiento = $this->asientoRepository->create($payload);
        if ($asiento === 'Error' || ! $asiento) {
            throw new RuntimeException('No se pudo grabar el asiento de la aplicación de cuenta corriente de cliente.');
        }

        $asientoId = (int) $asiento->id;
        $this->asientoMovimientoRepository->create($payload, $asientoId);

        return $asientoId;
    }

    /**
     * null = no hace falta asiento (misma cuenta AR y sin DC).
     *
     * @param  array{
     *   cruzada?:bool,
     *   valor_local_deuda?:float,
     *   valor_local_credito?:float,
     *   dc?:float
     * }|null  $liquidacion
     * @return array{payload: array<string, mixed>, reclasifica: bool, observacion: string}|null
     */
    public function previsualizar(
        Cliente_Cuentacorriente $deuda,
        Cliente_Cuentacorriente $credito,
        float $dc,
        string $fecha,
        ?array $liquidacion = null,
    ): ?array {
        $cruzada = (bool) ($liquidacion['cruzada'] ?? false);
        $lados = $this->cuentasDeLados($deuda, $credito);
        $cliente = $lados['cliente'];
        $cuentaArDeuda = $lados['cuenta_deuda'];
        $cuentaArCredito = $lados['cuenta_credito'];
        $reclasifica = $lados['reclasifica'];

        if (! $reclasifica && ! ProveedorCuentacorrienteAplicacionDcSupport::requiereAsiento($dc)) {
            return null;
        }

        $empresaId = (int) ($deuda->empresa_id ?: $credito->empresa_id);
        $tipoAsiento = $this->resolverTipoAsiento();
        $monedaLocalId = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);
        $obs = $this->observacion($deuda, $credito, $dc, $cruzada, $reclasifica);
        $emisor = MayorPlanoCuentaEmisorSupport::normalizarCodigo((string) ($cliente?->codigo ?? ''));

        $payload = [
            'empresa_id' => $empresaId,
            'tipoasiento_id' => (int) $tipoAsiento->id,
            'fecha' => $fecha,
            'observacion' => $obs,
            'usuario_id' => Auth::id(),
            'alcance_cierre_contable' => PeriodoContableCierreSupport::MODULO_VENTAS,
            'anita_emisor' => $emisor !== '' ? $emisor : null,
            'anita_sistema' => (int) ($credito->cobranza_id ?? 0) > 0 ? 'T' : 'V',
            'cuentacontable_ids' => [],
            'moneda_ids' => [],
            'centrocosto_ids' => [],
            'debes' => [],
            'haberes' => [],
            'cotizaciones' => [],
            'observaciones' => [],
        ];

        if ($reclasifica) {
            $valorDeuda = round(abs((float) ($liquidacion['valor_local_deuda'] ?? 0)), 4);
            $valorCredito = round(abs((float) ($liquidacion['valor_local_credito'] ?? 0)), 4);
            if ($valorDeuda <= 0 || $valorCredito <= 0) {
                throw new RuntimeException(
                    'Falta el valor en moneda local de la deuda o del crédito para reclasificar la aplicación.'
                );
            }

            $this->agregarLinea($payload, $cuentaArDeuda, $monedaLocalId, $valorDeuda, 0.0, $obs);
            $this->agregarLinea($payload, $cuentaArCredito, $monedaLocalId, 0.0, $valorCredito, $obs);

            $diferencia = round($valorDeuda - $valorCredito, 4);
            if (abs($diferencia) >= ProveedorCuentacorrienteAplicacionDcSupport::TOLERANCIA) {
                $importe = abs($diferencia);
                $this->agregarLinea(
                    $payload,
                    $this->resolverCuentaDc($cuentaArDeuda ?: $cuentaArCredito),
                    $monedaLocalId,
                    $diferencia < 0 ? $importe : 0.0,
                    $diferencia > 0 ? $importe : 0.0,
                    $obs
                );
            }
        } else {
            $importe = round(abs($dc), 4);
            $debeDc = ProveedorCuentacorrienteAplicacionDcSupport::esPerdida($dc);
            $this->agregarLinea(
                $payload,
                $this->resolverCuentaDc($cuentaArDeuda ?: $cuentaArCredito),
                $monedaLocalId,
                $debeDc ? $importe : 0.0,
                $debeDc ? 0.0 : $importe,
                $obs
            );
            $this->agregarLinea(
                $payload,
                $cuentaArDeuda,
                $monedaLocalId,
                $debeDc ? 0.0 : $importe,
                $debeDc ? $importe : 0.0,
                $obs
            );
        }

        return [
            'payload' => $payload,
            'reclasifica' => $reclasifica,
            'observacion' => $obs,
        ];
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
            'Revierte asiento aplicación CC cliente',
            alcanceCierre: PeriodoContableCierreSupport::ALCANCE_COBRANZA
        );
    }

    /**
     * @return array{cuenta_deuda:int, cuenta_credito:int, reclasifica:bool, cliente:Cliente|null}
     */
    public function cuentasDeLados(
        Cliente_Cuentacorriente $deuda,
        Cliente_Cuentacorriente $credito,
    ): array {
        $deuda->loadMissing(['clientes.cuentascontables']);
        $credito->loadMissing(['clientes.cuentascontables']);

        $cliente = $deuda->clientes ?? $credito->clientes;
        $cuentaDeuda = $this->resolverCuentaAr($cliente, (int) $deuda->empresa_id);
        $cuentaCredito = $this->resolverCuentaAr($cliente, (int) $credito->empresa_id);

        return [
            'cuenta_deuda' => $cuentaDeuda,
            'cuenta_credito' => $cuentaCredito,
            'reclasifica' => $this->cuentasDistintas($cuentaDeuda, $cuentaCredito),
            'cliente' => $cliente,
        ];
    }

    /**
     * Anticipo = crédito con cobranza y sin venta. En clientes la reclasificación es rara.
     */
    public function esReclasificacionNoAnticipo(
        Cliente_Cuentacorriente $deuda,
        Cliente_Cuentacorriente $credito,
    ): bool {
        if ((int) ($credito->cobranza_id ?? 0) > 0 && (int) ($credito->venta_id ?? 0) === 0) {
            return false;
        }

        return $this->cuentasDeLados($deuda, $credito)['reclasifica'];
    }

    /**
     * Compara por código: misma cuenta en planes de distintas empresas no reclasifica.
     */
    private function cuentasDistintas(int $cuentaA, int $cuentaB): bool
    {
        if ($cuentaA === $cuentaB) {
            return false;
        }

        $codigos = Cuentacontable::query()
            ->whereIn('id', [$cuentaA, $cuentaB])
            ->pluck('codigo', 'id');

        $codigoA = trim((string) ($codigos[$cuentaA] ?? ''));
        $codigoB = trim((string) ($codigos[$cuentaB] ?? ''));

        return $codigoA === '' || $codigoB === '' || $codigoA !== $codigoB;
    }

    private function resolverCuentaAr(?Cliente $cliente, int $empresaId): int
    {
        $cuentaId = (int) ($cliente?->cuentacontable_id ?? 0);
        if ($cuentaId <= 0) {
            throw new RuntimeException(
                'El cliente no tiene cuenta contable de deudores para asentar la liquidación / diferencia de cambio.'
            );
        }

        return $this->normalizarEmpresa($cuentaId, $empresaId);
    }

    private function normalizarEmpresa(int $cuentaId, int $empresaId): int
    {
        if ($cuentaId <= 0 || $empresaId <= 0) {
            return $cuentaId;
        }

        $cuenta = Cuentacontable::query()->find($cuentaId, ['id', 'empresa_id', 'codigo']);
        if ($cuenta === null || (int) $cuenta->empresa_id === $empresaId) {
            return $cuentaId;
        }

        $codigo = trim((string) $cuenta->codigo);
        if ($codigo === '') {
            return $cuentaId;
        }

        $idMismaEmpresa = (int) (Cuentacontable::query()
            ->where('codigo', $codigo)
            ->where('empresa_id', $empresaId)
            ->value('id') ?? 0);

        return $idMismaEmpresa > 0 ? $idMismaEmpresa : $cuentaId;
    }

    private function resolverCuentaDc(int $cuentaArId): int
    {
        $cuenta = Cuentacontable::query()->find($cuentaArId);
        $dcId = (int) ($cuenta->cuentacontable_difcambio_id ?? 0);
        if ($dcId > 0 && $dcId !== $cuentaArId) {
            return $dcId;
        }

        throw new RuntimeException(
            'Falta la cuenta de diferencia de cambio en la cuenta de deudores del cliente. Configúrela en el plan de cuentas (Dif. de cambio).'
        );
    }

    private function resolverTipoAsiento(): object
    {
        $tipo = $this->tipoasientoRepository->findPorAbreviatura('VEN')
            ?? $this->tipoasientoRepository->findPorAbreviatura('TES');
        if ($tipo === null) {
            throw new RuntimeException('No existe tipo de asiento VEN ni TES para la diferencia de cambio.');
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
        Cliente_Cuentacorriente $deuda,
        Cliente_Cuentacorriente $credito,
        float $dc,
        bool $cruzada = false,
        bool $reclasifica = false,
    ): string {
        $etiDeuda = ClienteCuentacorrienteAplicacionFilaSupport::etiqueta(
            $deuda,
            ClienteCuentacorrienteAplicacionFilaSupport::tipo($deuda, 'deuda')
        );
        $etiCredito = ClienteCuentacorrienteAplicacionFilaSupport::etiqueta(
            $credito,
            ClienteCuentacorrienteAplicacionFilaSupport::tipo($credito, 'credito')
        );
        $prefijo = match (true) {
            $cruzada => 'Liquidación cruzada CC cliente',
            $reclasifica => 'Reclasificación aplicación CC cliente',
            default => 'DC aplicación CC cliente',
        };
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
