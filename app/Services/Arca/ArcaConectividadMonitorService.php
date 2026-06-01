<?php

namespace App\Services\Arca;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Arca\ArcaFailoverStore;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use InvalidArgumentException;

/**
 * Probe de conectividad ARCA (último comprobante autorizado) y histéresis de failover CAEA.
 */
final class ArcaConectividadMonitorService
{
    public function __construct(
        private readonly ArcaWsfeFacturaElectronicaService $wsfeService,
        private readonly ArcaMtxcaFacturaElectronicaService $mtxcaService,
    ) {}

    /**
     * @return list<array{
     *     webservice: string,
     *     skipped: bool,
     *     skip_reason: ?string,
     *     ok: bool,
     *     error: ?string,
     *     ultimo_nro: ?int,
     *     probe: ?string,
     *     failover_active: bool
     * }>
     */
    public function ejecutarChequeos(): array
    {
        $resultados = [];

        if ((string) config('arca_wsfe.transporte', 'afip_php') === 'soap') {
            $resultados[] = $this->chequearWsfe();
        }

        if ((string) config('arca_mtxca.transporte', 'afip_php') === 'soap') {
            $resultados[] = $this->chequearMtxca();
        }

        return $resultados;
    }

    /**
     * @return array{
     *     webservice: string,
     *     skipped: bool,
     *     skip_reason: ?string,
     *     ok: bool,
     *     error: ?string,
     *     ultimo_nro: ?int,
     *     probe: ?string,
     *     failover_active: bool
     * }
     */
    private function chequearWsfe(): array
    {
        $base = [
            'webservice' => ArcaFailoverStore::WS_WSFE,
            'skipped' => false,
            'skip_reason' => null,
            'ok' => false,
            'error' => null,
            'ultimo_nro' => null,
            'probe' => null,
            'failover_active' => ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE),
        ];

        try {
            $params = $this->resolverParametrosProbe(ArcaFailoverStore::WS_WSFE);
        } catch (InvalidArgumentException $e) {
            return array_merge($base, [
                'skipped' => true,
                'skip_reason' => $e->getMessage(),
            ]);
        }

        $probe = sprintf(
            'FECompUltimoAutorizado empresa=%d pto=%d tipo=%d',
            $params['empresa_id'],
            $params['pto_vta'],
            $params['cbte_tipo'],
        );
        $base['probe'] = $probe;

        [$ok, $error, $ultimo] = $this->ejecutarProbe(
            fn () => $this->wsfeService->feCompUltimoAutorizado(
                $params['empresa_id'],
                $params['pto_vta'],
                $params['cbte_tipo'],
            ),
        );

        ArcaFailoverStore::registrarChequeo(
            ArcaFailoverStore::WS_WSFE,
            $ok,
            $error,
            ['ultimo_nro' => $ultimo, 'probe' => $probe],
        );

        return array_merge($base, [
            'ok' => $ok,
            'error' => $error,
            'ultimo_nro' => $ultimo,
            'failover_active' => ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE),
        ]);
    }

    /**
     * @return array{
     *     webservice: string,
     *     skipped: bool,
     *     skip_reason: ?string,
     *     ok: bool,
     *     error: ?string,
     *     ultimo_nro: ?int,
     *     probe: ?string,
     *     failover_active: bool
     * }
     */
    private function chequearMtxca(): array
    {
        $base = [
            'webservice' => ArcaFailoverStore::WS_MTXCA,
            'skipped' => false,
            'skip_reason' => null,
            'ok' => false,
            'error' => null,
            'ultimo_nro' => null,
            'probe' => null,
            'failover_active' => ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_MTXCA),
        ];

        try {
            $params = $this->resolverParametrosProbe(ArcaFailoverStore::WS_MTXCA);
        } catch (InvalidArgumentException $e) {
            return array_merge($base, [
                'skipped' => true,
                'skip_reason' => $e->getMessage(),
            ]);
        }

        $probe = sprintf(
            'consultarUltimoComprobanteAutorizado empresa=%d pto=%d tipo=%d',
            $params['empresa_id'],
            $params['pto_vta'],
            $params['cbte_tipo'],
        );
        $base['probe'] = $probe;

        [$ok, $error, $ultimo] = $this->ejecutarProbe(
            fn () => $this->mtxcaService->consultarUltimoComprobanteAutorizado(
                $params['empresa_id'],
                $params['pto_vta'],
                $params['cbte_tipo'],
            ),
        );

        ArcaFailoverStore::registrarChequeo(
            ArcaFailoverStore::WS_MTXCA,
            $ok,
            $error,
            ['ultimo_nro' => $ultimo, 'probe' => $probe],
        );

        return array_merge($base, [
            'ok' => $ok,
            'error' => $error,
            'ultimo_nro' => $ultimo,
            'failover_active' => ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_MTXCA),
        ]);
    }

    /**
     * @return array{0: bool, 1: ?string, 2: ?int}
     */
    private function ejecutarProbe(callable $probe): array
    {
        try {
            $ultimo = (int) $probe();

            return [true, null, $ultimo];
        } catch (\Throwable $e) {
            $mensaje = $e->getMessage();
            $clase = ArcaWsfeEmisionResiliencia::clasificarError($mensaje);

            if ($clase === ArcaWsfeEmisionResiliencia::CLASE_DATOS) {
                return [true, null, null];
            }

            return [false, $mensaje, null];
        }
    }

    /**
     * @return array{empresa_id: int, pto_vta: int, cbte_tipo: int}
     */
    public function resolverParametrosProbe(string $webservice): array
    {
        $empresaId = (int) config('arca.monitor_conectividad.empresa_id', 0);
        $puntoventaId = (int) config('arca.monitor_conectividad.puntoventa_id', 0);
        $cbteTipo = (int) config('arca.monitor_conectividad.cbte_tipo', 0);
        $tipotransaccionId = (int) config('arca.monitor_conectividad.tipotransaccion_id', 0);

        if ($empresaId <= 0) {
            $empresaId = $this->inferirEmpresaId($webservice);
        }

        if ($puntoventaId <= 0) {
            [$puntoventaId, $tipotransaccionId] = $this->inferirDesdeGastronomia($webservice, $tipotransaccionId);
        }

        if ($puntoventaId <= 0) {
            $puntoventaId = $this->inferirPuntoventaCae($webservice, $empresaId);
        }

        if ($cbteTipo <= 0) {
            $cbteTipo = $this->resolverCbteTipo($tipotransaccionId);
        }

        $pv = Puntoventa::query()->find($puntoventaId);
        if (! $pv) {
            throw new InvalidArgumentException(
                'ARCA monitor: punto de venta #'.$puntoventaId.' inexistente. Configure ARCA_MONITOR_PUNTOVENTA_ID.'
            );
        }

        $ptoVta = (int) $pv->codigo;
        if ($ptoVta <= 0) {
            throw new InvalidArgumentException(
                'ARCA monitor: punto de venta #'.$puntoventaId.' sin código numérico.'
            );
        }

        $wsEsperado = $webservice === ArcaFailoverStore::WS_MTXCA ? 'wsmtxca' : 'wsfev1';
        $pvWs = (string) ($pv->webservice ?? '');
        if ($pvWs !== '' && $pvWs !== $wsEsperado) {
            throw new InvalidArgumentException(
                "ARCA monitor: PV #{$puntoventaId} usa webservice «{$pvWs}», se esperaba «{$wsEsperado}»."
            );
        }

        if ($cbteTipo <= 0) {
            throw new InvalidArgumentException(
                'ARCA monitor: defina ARCA_MONITOR_CBTE_TIPO o ARCA_MONITOR_TIPOTRANSACCION_ID (código AFIP del tipo de comprobante).'
            );
        }

        return [
            'empresa_id' => $empresaId,
            'pto_vta' => $ptoVta,
            'cbte_tipo' => $cbteTipo,
        ];
    }

    private function inferirEmpresaId(string $webservice): int
    {
        $map = $webservice === ArcaFailoverStore::WS_MTXCA
            ? config('arca_mtxca.empresas', [])
            : config('arca_wsfe.empresas', []);

        if ($map !== []) {
            return (int) array_key_first($map);
        }

        throw new InvalidArgumentException(
            'ARCA monitor: configure ARCA_MONITOR_EMPRESA_ID o certificados en arca_wsfe.empresas.'
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function inferirDesdeGastronomia(string $webservice, int $tipotransaccionId): array
    {
        $wsPv = $webservice === ArcaFailoverStore::WS_MTXCA ? 'wsmtxca' : 'wsfev1';

        $cfg = ConfiguracionPuntoventaGastronomia::query()
            ->whereHas('puntoventaCae', function ($q) use ($wsPv) {
                $q->where('webservice', $wsPv)->where('modofacturacion', 'C');
            })
            ->with(['puntoventaCae', 'tipotransaccion'])
            ->orderBy('id')
            ->first();

        if (! $cfg || ! $cfg->puntoventaCae) {
            return [0, $tipotransaccionId];
        }

        $ttId = $tipotransaccionId > 0
            ? $tipotransaccionId
            : (int) ($cfg->tipotransaccion_id ?? config('gastronomia.tipotransaccion_factura_id', 0));

        return [(int) $cfg->puntoventa_cae_id, $ttId];
    }

    private function inferirPuntoventaCae(string $webservice, int $empresaId): int
    {
        $wsPv = $webservice === ArcaFailoverStore::WS_MTXCA ? 'wsmtxca' : 'wsfev1';

        $q = Puntoventa::query()
            ->where('webservice', $wsPv)
            ->where('modofacturacion', 'C')
            ->where('estado', 'A');

        if ($empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $pv = $q->orderBy('id')->first();

        return $pv ? (int) $pv->id : 0;
    }

    private function resolverCbteTipo(int $tipotransaccionId): int
    {
        if ($tipotransaccionId <= 0) {
            $tipotransaccionId = (int) config('gastronomia.tipotransaccion_factura_id', 0);
        }

        if ($tipotransaccionId <= 0) {
            return 0;
        }

        $tt = Tipotransaccion::query()->find($tipotransaccionId);
        if (! $tt || trim((string) $tt->codigo) === '') {
            return 0;
        }

        return (int) $tt->codigo;
    }
}
