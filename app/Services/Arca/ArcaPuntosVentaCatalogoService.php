<?php

namespace App\Services\Arca;

use Exception;
use Illuminate\Support\Facades\Cache;

/**
 * Puntos de venta habilitados en AFIP/ARCA para el ABM puntoventa.
 * Usa WSMTXCA o WSFE según el webservice de la empresa (misma lógica que tipos de comprobante).
 */
class ArcaPuntosVentaCatalogoService
{
    public const WS_WSFE = 'wsfev1';

    public const WS_MTXCA = 'wsmtxca';

    public function __construct(
        private ArcaWsfeFacturaElectronicaService $arcaWsfe,
        private ArcaMtxcaFacturaElectronicaService $arcaMtxca,
        private ArcaTiposComprobanteCatalogoService $arcaTiposComprobanteCatalogo,
    ) {}

    /**
     * @return array{
     *     puntos: list<array{
     *         codigo: string,
     *         numero: int,
     *         descripcion: string,
     *         emision_tipo: string,
     *         bloqueado: bool,
     *         fecha_baja: ?string
     *     }>,
     *     origen: string,
     *     webservice: string
     * }
     */
    public function obtenerPuntosVenta(
        int $empresaId,
        bool $refresh = false,
        ?string $webservice = null,
        ?string $modofacturacion = null,
    ): array {
        $webservice = $webservice !== null && $webservice !== ''
            ? $webservice
            : $this->arcaTiposComprobanteCatalogo->webserviceParaEmpresa($empresaId);

        $cacheKey = $this->cacheKey($empresaId, $webservice, $modofacturacion);
        $ttl = max(60, (int) config('arca.ptos_venta.cache_ttl', 1800));

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        if (! $refresh && Cache::has($cacheKey)) {
            return [
                'puntos' => Cache::get($cacheKey, []),
                'origen' => 'cache',
                'webservice' => $webservice,
            ];
        }

        $puntos = $this->consultarArca($empresaId, $webservice, $modofacturacion);
        Cache::put($cacheKey, $puntos, $ttl);

        return [
            'puntos' => $puntos,
            'origen' => 'arca',
            'webservice' => $webservice,
        ];
    }

    public function webserviceParaEmpresa(int $empresaId): string
    {
        return $this->arcaTiposComprobanteCatalogo->webserviceParaEmpresa($empresaId);
    }

    public function etiquetaWebservice(string $webservice): string
    {
        return $this->arcaTiposComprobanteCatalogo->etiquetaWebservice($webservice);
    }

    public function assertEmpresaConfigurada(int $empresaId, string $webservice): void
    {
        $this->arcaTiposComprobanteCatalogo->assertEmpresaConfigurada($empresaId, $webservice);
    }

    public function diagnosticoCertificado(int $empresaId, string $webservice): array
    {
        return $this->arcaTiposComprobanteCatalogo->diagnosticoCertificado($empresaId, $webservice);
    }

    /**
     * @return list<int>
     */
    public function empresasConCertificadoArca(): array
    {
        return $this->arcaTiposComprobanteCatalogo->empresasConCertificadoArca();
    }

    /**
     * @return list<array{
     *     codigo: string,
     *     numero: int,
     *     descripcion: string,
     *     emision_tipo: string,
     *     bloqueado: bool,
     *     fecha_baja: ?string
     * }>
     */
    public function consultarArca(int $empresaId, string $webservice, ?string $modofacturacion = null): array
    {
        $this->assertEmpresaConfigurada($empresaId, $webservice);

        if ($webservice === self::WS_MTXCA) {
            $puntos = $this->consultarPuntosVentaMtxca($empresaId, $modofacturacion);
        } else {
            $puntos = $this->arcaWsfe->feParamGetPtosVenta($empresaId);
        }

        return $this->filtrarHabilitados($puntos, $modofacturacion);
    }

    /**
     * @param  list<array{codigo: string, numero: int, descripcion: string, emision_tipo: string, bloqueado: bool, fecha_baja: ?string}>  $puntos
     * @return list<array{codigo: string, numero: int, descripcion: string, emision_tipo: string, bloqueado: bool, fecha_baja: ?string}>
     */
    public function filtrarHabilitados(array $puntos, ?string $modofacturacion = null): array
    {
        $habilitados = array_values(array_filter($puntos, function (array $pv): bool {
            if ($pv['bloqueado'] ?? false) {
                return false;
            }

            return ($pv['fecha_baja'] ?? null) === null;
        }));

        if ($modofacturacion === null || $modofacturacion === '') {
            return $habilitados;
        }

        return array_values(array_filter($habilitados, function (array $pv) use ($modofacturacion): bool {
            return $this->puntoCompatibleConModoFacturacion($pv, $modofacturacion);
        }));
    }

    /**
     * @param  array{emision_tipo?: string}  $punto
     */
    private function puntoCompatibleConModoFacturacion(array $punto, string $modofacturacion): bool
    {
        $emision = strtoupper(trim((string) ($punto['emision_tipo'] ?? '')));

        return match ($modofacturacion) {
            'C' => $emision === '' || (str_contains($emision, 'CAE') && ! str_contains($emision, 'CAEA')),
            'A' => $emision === '' || str_contains($emision, 'CAEA'),
            default => true,
        };
    }

    /**
     * @return list<array{codigo: string, numero: int, descripcion: string, emision_tipo: string, bloqueado: bool, fecha_baja: ?string}>
     */
    private function consultarPuntosVentaMtxca(int $empresaId, ?string $modofacturacion): array
    {
        if ($modofacturacion === 'A') {
            return $this->arcaMtxca->consultarPuntosVenta($empresaId, true);
        }
        if ($modofacturacion === 'C') {
            return $this->arcaMtxca->consultarPuntosVenta($empresaId, false);
        }

        $cae = $this->arcaMtxca->consultarPuntosVenta($empresaId, false);
        $caea = $this->arcaMtxca->consultarPuntosVenta($empresaId, true);

        $porNumero = [];
        foreach (array_merge($cae, $caea) as $pv) {
            $porNumero[(int) $pv['numero']] = $pv;
        }

        return array_values($porNumero);
    }

    private function cacheKey(int $empresaId, string $webservice, ?string $modofacturacion): string
    {
        $modo = $modofacturacion !== null && $modofacturacion !== '' ? $modofacturacion : 'all';

        return 'arca_ptos_venta:'.$empresaId.':'.$webservice.':'.$modo;
    }
}
