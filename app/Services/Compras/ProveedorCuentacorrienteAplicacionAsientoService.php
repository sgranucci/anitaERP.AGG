<?php

namespace App\Services\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Contable\Asiento;
use App\Models\Contable\Cuentacontable;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Compras\ProveedorAnticipoCuentaContableSupport;
use App\Support\Compras\ProveedorCuentaContableMonedaSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionDcSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionFilaSupport;
use App\Support\Compras\ProveedorCuentacorrienteCuentaApImputadaSupport;
use App\Support\Contable\AsientoReversoSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaEmisorSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Asiento al aplicar CC (P&L vs AP, sin ítem abierto).
 *
 * Dos motivos para generarlo, combinables:
 * - Diferencia de cambio entre la cotización de la deuda y la del crédito.
 * - Reclasificación: los dos lados no comparten cuenta (liquidación cruzada MN/ME, o
 *   anticipo con cuenta propia según `pago.anticipo_proveedor`).
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
        $preview = $this->previsualizar($deuda, $credito, $dc, $fecha, $liquidacion);
        if ($preview === null) {
            return null;
        }

        $payload = $preview['payload'];
        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $payload['empresa_id'],
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_CUENTAS_PAGAR
        );

        $asiento = $this->asientoRepository->create($payload);
        if ($asiento === 'Error' || ! $asiento) {
            throw new RuntimeException('No se pudo grabar el asiento de la aplicación de cuenta corriente.');
        }

        $asientoId = (int) $asiento->id;
        $this->asientoMovimientoRepository->create($payload, $asientoId);

        return $asientoId;
    }

    /**
     * Payload del asiento sin persistir ni consumir numeración de Anita.
     * null = la aplicación no necesita asiento (mismo lado contable y sin DC).
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
        Proveedor_Cuentacorriente $deuda,
        Proveedor_Cuentacorriente $credito,
        float $dc,
        string $fecha,
        ?array $liquidacion = null,
    ): ?array {
        $cruzada = (bool) ($liquidacion['cruzada'] ?? false);
        $lados = $this->cuentasDeLados($deuda, $credito);
        $proveedor = $lados['proveedor'];
        $cuentaApDeuda = $lados['cuenta_deuda'];
        $cuentaApCredito = $lados['cuenta_credito'];
        $reclasifica = $lados['reclasifica'];

        if (! $reclasifica && ! ProveedorCuentacorrienteAplicacionDcSupport::requiereAsiento($dc)) {
            return null;
        }

        $empresaId = (int) ($deuda->empresa_id ?: $credito->empresa_id);
        $tipoAsiento = $this->resolverTipoAsiento();
        $monedaLocalId = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);
        $obs = $this->observacion($deuda, $credito, $dc, $cruzada, $reclasifica);
        $emisor = MayorPlanoCuentaEmisorSupport::normalizarCodigo((string) ($proveedor?->codigo ?? ''));

        $payload = [
            'empresa_id' => $empresaId,
            'tipoasiento_id' => (int) $tipoAsiento->id,
            'fecha' => $fecha,
            'observacion' => $obs,
            'usuario_id' => Auth::id(),
            'alcance_cierre_contable' => PeriodoContableCierreSupport::MODULO_COMPRAS,
            // El mayor plano lee anita_emisor; sin esto Emisor/CUIT quedan en blanco.
            'anita_emisor' => $emisor !== '' ? $emisor : null,
            'anita_sistema' => (int) ($credito->pagoproveedor_id ?? 0) > 0 ? 'T' : 'C',
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

            $this->agregarLinea($payload, $cuentaApDeuda, $monedaLocalId, $valorDeuda, 0.0, $obs);
            $this->agregarLinea($payload, $cuentaApCredito, $monedaLocalId, 0.0, $valorCredito, $obs);

            // La pierna de DC es la que balancea: el signo sale de los valores locales,
            // no de $dc (cruzada y misma moneda lo calculan con signo invertido).
            $diferencia = round($valorDeuda - $valorCredito, 4);
            if (abs($diferencia) >= ProveedorCuentacorrienteAplicacionDcSupport::TOLERANCIA) {
                $importe = abs($diferencia);
                $this->agregarLinea(
                    $payload,
                    $this->resolverCuentaDc($cuentaApDeuda ?: $cuentaApCredito, $proveedor),
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
                $this->resolverCuentaDc($cuentaApDeuda ?: $cuentaApCredito, $proveedor),
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
            'Revierte asiento aplicación CC',
            alcanceCierre: PeriodoContableCierreSupport::ALCANCE_CUENTAS_PAGAR
        );
    }

    /**
     * Cuenta de AP de cada lado. Distintas = la aplicación reclasifica
     * (anticipo con cuenta propia, o liquidación cruzada MN/ME).
     *
     * @return array{cuenta_deuda:int, cuenta_credito:int, reclasifica:bool, proveedor:Proveedor|null}
     */
    public function cuentasDeLados(
        Proveedor_Cuentacorriente $deuda,
        Proveedor_Cuentacorriente $credito,
    ): array {
        $deuda->loadMissing([
            'proveedores',
            'comprobante_proveedores.ordencompras.ordencompra_articulos',
            'comprobante_proveedores.proveedores',
            'comprobante_proveedores.tipotransaccion_compras',
        ]);
        $credito->loadMissing([
            'proveedores',
            'comprobante_proveedores.ordencompras.ordencompra_articulos',
            'comprobante_proveedores.proveedores',
            'comprobante_proveedores.tipotransaccion_compras',
            'pagoproveedores',
        ]);

        $proveedor = $deuda->proveedores ?? $credito->proveedores;
        $cuentaDeuda = $this->resolverCuentaDeLado($deuda, $proveedor);
        $cuentaCredito = $this->resolverCuentaDeLado($credito, $proveedor);

        return [
            'cuenta_deuda' => $cuentaDeuda,
            'cuenta_credito' => $cuentaCredito,
            'reclasifica' => $this->cuentasDistintas($cuentaDeuda, $cuentaCredito),
            'proveedor' => $proveedor,
        ];
    }

    /**
     * Se compara por código y no por id: una aplicación cruzada entre empresas usa la
     * misma cuenta (211010001) con id distinto en cada plan, y ahí no hay nada que
     * reclasificar — un asiento Debe y Haber a la misma cuenta se anula solo.
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

    /**
     * Reclasificación entre dos cuentas de proveedores que no es un anticipo:
     * el descalce viene de la moneda de la OC vs la del comprobante, y amerita
     * aviso a administración y contaduría.
     */
    public function esReclasificacionNoAnticipo(
        Proveedor_Cuentacorriente $deuda,
        Proveedor_Cuentacorriente $credito,
    ): bool {
        if (ProveedorAnticipoCuentaContableSupport::esCreditoAnticipo($credito)) {
            return false;
        }

        return $this->cuentasDeLados($deuda, $credito)['reclasifica'];
    }

    /**
     * Manda la cuenta que el documento tiene realmente imputada: una NC sin OC puede
     * haber ido a una cuenta distinta de la que sugiere su moneda, y el anticipo puede
     * haber nacido en la cuenta de proveedores aunque hoy exista cuenta de anticipos.
     * La deducción por moneda / configuración queda como respaldo.
     */
    private function resolverCuentaDeLado(
        Proveedor_Cuentacorriente $lado,
        ?Proveedor $proveedor,
    ): int {
        $imputada = ProveedorCuentacorrienteCuentaApImputadaSupport::cuenta($lado);
        if ($imputada !== null) {
            return $imputada;
        }

        $anticipo = ProveedorAnticipoCuentaContableSupport::cuentaParaCreditoAplicado($lado);
        if ($anticipo !== null) {
            return $anticipo;
        }

        $comprobante = $lado->comprobante_proveedores;
        if ($comprobante) {
            $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorDesdeComprobante(
                $comprobante,
                $proveedor ?? $comprobante->proveedores
            );
            if ($cuentaId > 0) {
                return ProveedorCuentacorrienteCuentaApImputadaSupport::normalizarEmpresa(
                    $cuentaId,
                    (int) $lado->empresa_id
                );
            }
        }

        $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorId(
            $proveedor ?? $lado->proveedores,
            (int) ($lado->moneda_id ?: 1)
        );
        if ($cuentaId > 0) {
            return ProveedorCuentacorrienteCuentaApImputadaSupport::normalizarEmpresa(
                $cuentaId,
                (int) $lado->empresa_id
            );
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
        bool $reclasifica = false,
    ): string {
        $etiDeuda = ProveedorCuentacorrienteAplicacionFilaSupport::etiqueta(
            $deuda,
            ProveedorCuentacorrienteAplicacionFilaSupport::tipo($deuda, 'deuda')
        );
        $etiCredito = ProveedorCuentacorrienteAplicacionFilaSupport::etiqueta(
            $credito,
            ProveedorCuentacorrienteAplicacionFilaSupport::tipo($credito, 'credito')
        );
        $prefijo = match (true) {
            $cruzada => 'Liquidación cruzada CC',
            $reclasifica && ProveedorAnticipoCuentaContableSupport::esCreditoAnticipo($credito) => 'Aplicación anticipo CC',
            $reclasifica => 'Reclasificación aplicación CC',
            default => 'DC aplicación CC',
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
