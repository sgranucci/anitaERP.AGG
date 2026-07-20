<?php

namespace App\Support\Caja\AnitaSync;

use App\ApiAnita;
use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRendicionAnitaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionCaeaCompartidoRendgSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionVendingRendgSupport;

/**
 * Lectura y reglas de portadora Z/NC en Informix rendgastro (bridge Anita).
 */
final class RendicionGastronomiaAnitaRendgastroSupport
{
    /** @var list<string> */
    public const SECUENCIA_TURNO_PORTADORA = ['N', 'T', 'M'];

    private const CAMPOS_CABECERA_DETALLE = 'rendg_nro_oper, rendg_sucursal, rendg_nro_rend_vta, rendg_turno, rendg_total_x, rendg_total_z, rendg_tot_nc, rendg_hora, rendg_fecha, rendg_fecha_alfa, rendg_host, rendg_suc_caea, rendg_tot_fc_caea, rendg_tot_nc_caea';

    /** @var array<int, string> */
    private array $letraTurnoPorTurnoOperativo = [];

    /** @var array<string, bool> */
    private array $sucursalEsEstacionamientoCache = [];

    /** @var array<int, list<string>> */
    private array $hostsEstacionamientoPorEmpresaCache = [];

    public static function letraTurnoDesdeNombre(?string $nombreTurno): string
    {
        $nombre = trim((string) $nombreTurno);
        if ($nombre === '') {
            return '?';
        }

        return mb_strtoupper(mb_substr($nombre, 0, 1));
    }

    /**
     * @return list<object>
     */
    public function listarCabecerasPorSucursal(
        int $empresaId,
        int $fechaEntera,
        int $sucursalCae,
        ?string $tipoOper = null,
    ): array {
        $tipoOper = $tipoOper ?? (string) config('rendicion_gastronomia_anita.tipo_oper', 'F');
        $where = " WHERE rendg_empresa = '".$empresaId."'"
            ." AND rendg_tipo_oper = '".RendicionGastronomiaCabeceraAnitaMapper::texto($tipoOper, 1)."'"
            ." AND rendg_fecha = '".$fechaEntera."'"
            ." AND rendg_sucursal = '".$sucursalCae."' ";

        $api = new ApiAnita;

        return ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('rendicion_gastronomia_anita.sistema', 'caja'),
            'tabla' => (string) config('rendicion_gastronomia_anita.tabla_cabecera', 'rendgastro'),
            'campos' => 'rendg_nro_oper, rendg_sucursal, rendg_nro_rend_vta, rendg_turno, rendg_total_z, rendg_tot_nc, rendg_hora, rendg_fecha_alfa',
            'whereArmado' => $where,
        ]));
    }

    /**
     * @return list<object>
     */
    public function listarCabecerasEmpresaFecha(
        int $empresaId,
        int $fechaEntera,
        ?string $tipoOper = null,
    ): array {
        return $this->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera, $tipoOper);
    }

    /**
     * Cabeceras del día con host, sucursal CAEA y totales CAEA (conciliación por PC).
     *
     * @return list<object>
     */
    public function listarCabecerasEmpresaFechaDetalle(
        int $empresaId,
        int $fechaEntera,
        ?string $tipoOper = null,
    ): array {
        $tipoOper = $tipoOper ?? (string) config('rendicion_gastronomia_anita.tipo_oper', 'F');
        $where = " WHERE rendg_empresa = '".$empresaId."'"
            ." AND rendg_tipo_oper = '".RendicionGastronomiaCabeceraAnitaMapper::texto($tipoOper, 1)."'"
            ." AND rendg_fecha = '".$fechaEntera."' ";

        $api = new ApiAnita;

        return ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('rendicion_gastronomia_anita.sistema', 'caja'),
            'tabla' => (string) config('rendicion_gastronomia_anita.tabla_cabecera', 'rendgastro'),
            'campos' => self::CAMPOS_CABECERA_DETALLE,
            'whereArmado' => $where,
        ]));
    }

    /**
     * Cabecera rendgastro por nro_oper (p. ej. rendición proceso Waitry grabada días después).
     */
    public function listarCabeceraPorNroOper(int $empresaId, int $nroOper, ?string $tipoOper = null): ?object
    {
        if ($nroOper <= 0) {
            return null;
        }

        $tipoOper = $tipoOper ?? (string) config('rendicion_gastronomia_anita.tipo_oper', 'F');
        $where = " WHERE rendg_empresa = '".$empresaId."'"
            ." AND rendg_tipo_oper = '".RendicionGastronomiaCabeceraAnitaMapper::texto($tipoOper, 1)."'"
            ." AND rendg_nro_oper = '".$nroOper."' ";

        $api = new ApiAnita;
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('rendicion_gastronomia_anita.sistema', 'caja'),
            'tabla' => (string) config('rendicion_gastronomia_anita.tabla_cabecera', 'rendgastro'),
            'campos' => self::CAMPOS_CABECERA_DETALLE,
            'whereArmado' => $where,
        ]));

        return $filas[0] ?? null;
    }

    /**
     * Importe post-cierre Waitry en rendgastro (host CIERRE-WAITRY / snapshot nro_oper).
     *
     * @return array{
     *   total: float|null,
     *   total_x: float|null,
     *   tot_fc_caea: float|null,
     *   nro_oper: int|null,
     *   host: string|null
     * }
     */
    public function totalPostCierreWaitry(
        int $empresaId,
        int $fechaEntera,
        int $jornadaId,
        ?int $nroOperSnapshot = null,
        ?float $totalXSnapshot = null,
    ): array {
        if ($jornadaId > 0) {
            $porJornada = $this->listarCabecerasPostCierrePorJornada($empresaId, $jornadaId);
            if ($porJornada !== []) {
                return $this->sumarImportesPostCierreCabeceras($porJornada);
            }
        }

        if ($nroOperSnapshot !== null && $nroOperSnapshot > 0) {
            $cab = $this->listarCabeceraPorNroOper($empresaId, $nroOperSnapshot);
            if ($cab !== null && $this->esCabeceraPostCierreWaitry($cab)) {
                $cabJornadaId = (int) ($cab->rendg_nro_rend_vta ?? 0);
                if ($jornadaId > 0 && $cabJornadaId > 0 && $cabJornadaId !== $jornadaId) {
                    return $this->importePostCierreDesdeSnapshot($nroOperSnapshot, $totalXSnapshot);
                }

                return $this->mapImportePostCierreCabecera($cab);
            }
        }

        $cabeceras = $this->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);
        $waitry = array_values(array_filter(
            $cabeceras,
            fn (object $fila): bool => $this->esCabeceraPostCierreWaitry($fila),
        ));

        if ($waitry === []) {
            $desdeSnapshot = $this->importePostCierreDesdeSnapshot($nroOperSnapshot, $totalXSnapshot);
            if (($desdeSnapshot['total'] ?? null) !== null) {
                return array_merge($desdeSnapshot, [
                    'snapshot_total_x' => $desdeSnapshot['total_x'],
                    'snapshot_nro_oper' => $nroOperSnapshot,
                ]);
            }

            return array_merge($this->importePostCierreVacio(), [
                'snapshot_total_x' => ($totalXSnapshot !== null && $totalXSnapshot > 0)
                    ? round($totalXSnapshot, 2)
                    : null,
                'snapshot_nro_oper' => $nroOperSnapshot,
            ]);
        }

        if ($jornadaId > 0) {
            $porJornada = array_values(array_filter(
                $waitry,
                static fn (object $fila): bool => (int) ($fila->rendg_nro_rend_vta ?? 0) === $jornadaId,
            ));
            if ($porJornada !== []) {
                return $this->sumarImportesPostCierreCabeceras($porJornada);
            }
        }

        return $this->sumarImportesPostCierreCabeceras($waitry);
    }

    /**
     * @return list<object>
     */
    public function listarCabecerasPostCierrePorJornada(int $empresaId, int $jornadaId): array
    {
        if ($jornadaId <= 0) {
            return [];
        }

        $tipoOper = (string) config('rendicion_gastronomia_anita.tipo_oper', 'F');
        $host = CierreJornadaProcesoRendicionAnitaSupport::HOST;
        $where = " WHERE rendg_empresa = '".$empresaId."'"
            ." AND rendg_tipo_oper = '".RendicionGastronomiaCabeceraAnitaMapper::texto($tipoOper, 1)."'"
            ." AND rendg_nro_rend_vta = '".$jornadaId."' "
            ." AND rendg_host = '".RendicionGastronomiaCabeceraAnitaMapper::texto($host, 15)."' ";

        $api = new ApiAnita;

        return ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('rendicion_gastronomia_anita.sistema', 'caja'),
            'tabla' => (string) config('rendicion_gastronomia_anita.tabla_cabecera', 'rendgastro'),
            'campos' => self::CAMPOS_CABECERA_DETALLE,
            'whereArmado' => $where,
        ]));
    }

    public function esCabeceraPostCierreWaitry(object $fila): bool
    {
        return trim((string) ($fila->rendg_host ?? '')) === CierreJornadaProcesoRendicionAnitaSupport::HOST;
    }

    public function esCabeceraAgregadosCaea(object $fila): bool
    {
        return trim((string) ($fila->rendg_host ?? '')) === CierreJornadaProcesoRendicionAnitaSupport::HOST_AGREGADOS_CAEA;
    }

    /**
     * Cabecera rendgastro de estacionamiento (tabla compartida con gastronomía).
     * No debe modificarse desde reparación / limpieza legacy de gastronomía.
     */
    public function esCabeceraEstacionamiento(object $fila): bool
    {
        if ($this->esCabeceraPostCierreWaitry($fila)) {
            return false;
        }

        $sucursal = (int) ($fila->rendg_sucursal ?? 0);
        $empresaId = (int) ($fila->rendg_empresa ?? 0);
        if ($sucursal > 0 && $this->esSucursalDeEstacionamiento($empresaId, $sucursal)) {
            return true;
        }

        $host = mb_strtolower(trim((string) ($fila->rendg_host ?? '')));
        if ($host === '') {
            return false;
        }

        if ($this->esHostEstacionamiento($host, $empresaId)) {
            return true;
        }

        if (str_starts_with($host, 'estac')) {
            return true;
        }

        // Hosts legacy estacionamiento Rebisco / Kandiko en rendgastro compartido.
        if (in_array($host, ['192.168.40.151'], true)) {
            return true;
        }

        return str_starts_with($host, 'pc-caja');
    }

    public function esSucursalDeEstacionamiento(int $empresaId, int $sucursal): bool
    {
        if ($sucursal <= 0) {
            return false;
        }

        $key = $empresaId.':'.$sucursal;
        if (array_key_exists($key, $this->sucursalEsEstacionamientoCache)) {
            return $this->sucursalEsEstacionamientoCache[$key];
        }

        if (in_array($sucursal, $this->sucursalesEstacionamiento(), true)) {
            return $this->sucursalEsEstacionamientoCache[$key] = true;
        }

        if ($empresaId <= 0) {
            return $this->sucursalEsEstacionamientoCache[$key] = false;
        }

        $pv = $this->puntoventaPorSucursal($empresaId, $sucursal);
        $nombre = mb_strtolower(trim((string) ($pv?->nombre ?? '')));

        return $this->sucursalEsEstacionamientoCache[$key] = str_contains($nombre, 'estacionamiento')
            || str_contains($nombre, 'estac.');
    }

    private function esHostEstacionamiento(string $host, int $empresaId): bool
    {
        $host = trim($host);
        if ($host === '') {
            return false;
        }

        if ($empresaId <= 0) {
            return false;
        }

        $hosts = $this->hostsEstacionamientoPorEmpresa($empresaId);

        return in_array($host, $hosts, true);
    }

    private function esSucursalVendingRendg(int $empresaId, int $sucursal): bool
    {
        if ($sucursal <= 0 || $empresaId <= 0) {
            return false;
        }

        $map = app(GastronomiaConciliacionVendingRendgSupport::class)->puntoventasVendingPorSucursal($empresaId);

        return isset($map[$sucursal]);
    }

    /**
     * @return list<string>
     */
    private function hostsEstacionamientoPorEmpresa(int $empresaId): array
    {
        if (isset($this->hostsEstacionamientoPorEmpresaCache[$empresaId])) {
            return $this->hostsEstacionamientoPorEmpresaCache[$empresaId];
        }

        $hosts = ConfiguracionPuntoventaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->pluck('identificador_pc')
            ->map(static fn ($pc): string => trim((string) $pc))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->hostsEstacionamientoPorEmpresaCache[$empresaId] = $hosts;
    }

    private function puntoventaPorSucursal(int $empresaId, int $sucursal): ?Puntoventa
    {
        $codigo = str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT);

        $pv = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->first();

        if ($pv !== null) {
            return $pv;
        }

        foreach (Puntoventa::query()->where('empresa_id', $empresaId)->get(['id', 'codigo', 'nombre']) as $candidato) {
            if ((int) preg_replace('/\D+/', '', trim((string) $candidato->codigo)) === $sucursal) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public function sucursalesEstacionamiento(): array
    {
        $codigos = config('rendicion_gastronomia_anita.auditoria_diaria.puntoventa_codigos_solo_anita', []);
        $sucursales = [];
        foreach ($codigos as $codigo) {
            $suc = (int) preg_replace('/\D+/', '', trim((string) $codigo));
            if ($suc > 0) {
                $sucursales[] = $suc;
            }
        }

        return array_values(array_unique($sucursales));
    }

    /**
     * Suma Z portadora de cabeceras PC (rendg_host IP) + post-cierre Waitry del día Anita.
     */
    public function sumaZPortadorasPcMasPostCierre(
        int $empresaId,
        int $fechaEntera,
        int $jornadaId,
        ?int $nroOperSnapshot,
        ?float $totalXSnapshot = null,
    ): float {
        $cabeceras = $this->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);
        $suma = 0.0;

        /** @var array<string, list<object>> $porHost */
        $porHost = [];
        foreach ($cabeceras as $fila) {
            $host = trim((string) ($fila->rendg_host ?? ''));
            if ($host === '' || $host === CierreJornadaProcesoRendicionAnitaSupport::HOST) {
                continue;
            }
            if (! preg_match('/^\d{1,3}(?:\.\d{1,3}){3}$/', $host)) {
                continue;
            }
            $porHost[$host][] = $fila;
        }

        foreach ($porHost as $grupo) {
            $portadora = $this->elegirPortadora($grupo);
            $suma += round((float) ($portadora->rendg_total_z ?? 0), 2);
        }

        $post = $this->totalPostCierreWaitry($empresaId, $fechaEntera, $jornadaId, $nroOperSnapshot, $totalXSnapshot);

        return round($suma + (float) ($post['total'] ?? 0), 2);
    }

    /**
     * Suma NC portadora de cabeceras PC (rendg_host IP) + post-cierre Waitry del día Anita.
     *
     * Excluye estacionamiento (mismo criterio que el bruto ERP del control día gastronomía).
     * Hasta integrar estacionamiento en el ERP, mezclar NC estac. aquí generaba DIF falso vs neto salón.
     */
    public function sumaNcPortadorasPcMasPostCierre(int $empresaId, int $fechaEntera, int $jornadaId = 0): float
    {
        $cabeceras = $this->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);
        $suma = 0.0;

        /** @var array<string, list<object>> $porHost */
        $porHost = [];
        foreach ($cabeceras as $fila) {
            if ($this->esCabeceraEstacionamiento($fila)) {
                continue;
            }

            $host = trim((string) ($fila->rendg_host ?? ''));
            if ($host === '' || $this->esCabeceraPostCierreWaitry($fila)) {
                continue;
            }
            if (! preg_match('/^\d{1,3}(?:\.\d{1,3}){3}$/', $host)) {
                continue;
            }
            $porHost[$host][] = $fila;
        }

        foreach ($porHost as $grupo) {
            $portadora = $this->elegirPortadora($grupo);
            $suma += round((float) ($portadora->rendg_tot_nc ?? 0), 2);
        }

        $postCabeceras = $jornadaId > 0
            ? $this->listarCabecerasPostCierrePorJornada($empresaId, $jornadaId)
            : array_values(array_filter(
                $cabeceras,
                fn (object $fila): bool => $this->esCabeceraPostCierreWaitry($fila),
            ));

        foreach ($postCabeceras as $cab) {
            $suma += round((float) ($cab->rendg_tot_nc ?? 0), 2);
            $suma += round((float) ($cab->rendg_tot_nc_caea ?? 0), 2);
        }

        return round($suma, 2);
    }

    /**
     * Cabeceras rendgastro fuera de PCs configuradas (hosts legacy: pc-caja*, bingo, etc.)
     * o IPs no registradas. Anita puede sumarlas además del Z de la portadora IP → doble conteo.
     *
     * @param  list<string>  $hostsConfigurados
     * @return array{
     *   rendg_legacy_z: float,
     *   fc_caea_duplicado_portadora: float,
     *   rendg_pv_caea_z_inflado: float,
     *   filas_legacy: list<array{nro_oper:int, host:string, suc:int, z:float, fc_caea:float}>
     * }
     */
    public function auditarCabecerasHuérfanasLegacy(
        int $empresaId,
        int $fechaEntera,
        array $hostsConfigurados,
        float $tolerancia = 0.02,
    ): array {
        $hostsConfig = array_values(array_filter(array_map(
            static fn (string $host): string => trim($host),
            $hostsConfigurados,
        )));

        $cabeceras = $this->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);
        $legacyZ = 0.0;
        $fcCaeaDuplicado = 0.0;
        $pvCaeaZInflado = 0.0;
        $filasLegacy = [];

        foreach ($cabeceras as $fila) {
            if ($this->esCabeceraPostCierreWaitry($fila)) {
                $z = round((float) ($fila->rendg_total_z ?? 0), 2);
                $x = round((float) ($fila->rendg_total_x ?? 0), 2);
                if ($z > $x + $tolerancia) {
                    $pvCaeaZInflado += round($z - $x, 2);
                }

                continue;
            }

            if ($this->esCabeceraEstacionamiento($fila)) {
                continue;
            }

            if ($this->esCabeceraAgregadosCaea($fila)) {
                continue;
            }

            $host = trim((string) ($fila->rendg_host ?? ''));
            if ($host === '') {
                if ($this->esSucursalVendingRendg($empresaId, (int) ($fila->rendg_sucursal ?? 0))) {
                    continue;
                }

                continue;
            }

            if (in_array($host, $hostsConfig, true)) {
                continue;
            }

            $z = round((float) ($fila->rendg_total_z ?? 0), 2);
            $fcCaea = round((float) ($fila->rendg_tot_fc_caea ?? 0), 2);
            if ($z <= $tolerancia && $fcCaea <= $tolerancia) {
                continue;
            }

            $legacyZ += $z;
            $filasLegacy[] = [
                'nro_oper' => (int) ($fila->rendg_nro_oper ?? 0),
                'host' => $host,
                'suc' => (int) ($fila->rendg_sucursal ?? 0),
                'z' => $z,
                'fc_caea' => $fcCaea,
            ];
        }

        /** @var array<string, list<object>> $porHostConfig */
        $porHostConfig = [];
        foreach ($cabeceras as $fila) {
            if ($this->esCabeceraPostCierreWaitry($fila)) {
                continue;
            }
            $host = trim((string) ($fila->rendg_host ?? ''));
            if ($host === '' || ! in_array($host, $hostsConfig, true)) {
                continue;
            }
            $porHostConfig[$host][] = $fila;
        }

        foreach ($porHostConfig as $grupo) {
            $portadora = $this->elegirPortadora($grupo);
            $zPortadora = round((float) ($portadora->rendg_total_z ?? 0), 2);
            $fcCaeaPortadora = round((float) ($portadora->rendg_tot_fc_caea ?? 0), 2);
            if ($zPortadora > $tolerancia && $fcCaeaPortadora > $tolerancia) {
                $fcCaeaDuplicado += $fcCaeaPortadora;
            }
        }

        return [
            'rendg_legacy_z' => round($legacyZ, 2),
            'fc_caea_duplicado_portadora' => round($fcCaeaDuplicado, 2),
            'rendg_pv_caea_z_inflado' => round($pvCaeaZInflado, 2),
            'filas_legacy' => $filasLegacy,
        ];
    }

    /**
     * Total bruto rendgastro (CAE+CAEA) de la portadora del día para una PC (rendg_host).
     *
     * rendg_total_z en portadora suele ser el total del día por PC (recálculo caja).
     * Si quedó solo CAE (reparación por PV), se suma el neto CAEA de rendg_tot_fc_caea / rendg_tot_nc_caea.
     *
     * @return array{
     *   total: float|null,
     *   z_portadora: float|null,
     *   caea_neto: float,
     *   suc_caea: int|null,
     *   portadora_nro_oper: int|null
     * }
     */
    public function totalBrutoPorHost(
        int $empresaId,
        int $fechaEntera,
        string $identificadorPc,
        float $erpCae = 0.0,
        float $erpCaea = 0.0,
        float $tolerancia = 0.02,
    ): array {
        $host = trim($identificadorPc);
        if ($host === '') {
            return $this->totalBrutoVacio();
        }

        $cabeceras = $this->filtrarCabecerasPorHost(
            $this->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera),
            $host,
        );

        if ($cabeceras === []) {
            return $this->totalBrutoVacio();
        }

        $portadora = $this->elegirPortadora($cabeceras);
        $zPortadora = round((float) ($portadora->rendg_total_z ?? 0), 2);
        $caeaNeto = $this->totalCaeaNetoCabeceras($cabeceras);
        $total = $this->resolverTotalBrutoHost($zPortadora, $caeaNeto, $erpCae, $erpCaea, $tolerancia);
        $total = $this->ajustarTotalBrutoPorCaeaEstacionamientoAjena(
            $empresaId,
            $fechaEntera,
            $host,
            $total,
            $zPortadora,
            $erpCae,
            $erpCaea,
            $tolerancia,
        );

        return [
            'total' => $total,
            'z_portadora' => $zPortadora,
            'caea_neto' => $caeaNeto,
            'suc_caea' => (int) ($portadora->rendg_suc_caea ?? 0) ?: null,
            'portadora_nro_oper' => (int) ($portadora->rendg_nro_oper ?? 0) ?: null,
        ];
    }

    /**
     * @param  list<object>  $cabeceras
     * @return list<object>
     */
    public function filtrarCabecerasPorHost(array $cabeceras, string $identificadorPc): array
    {
        $host = trim($identificadorPc);
        if ($host === '') {
            return [];
        }

        return array_values(array_filter(
            $cabeceras,
            static fn (object $fila): bool => trim((string) ($fila->rendg_host ?? '')) === $host,
        ));
    }

    /**
     * @param  list<object>  $cabeceras
     */
    public function totalCaeaNetoCabeceras(array $cabeceras): float
    {
        $neto = 0.0;
        foreach ($cabeceras as $fila) {
            $neto += round((float) ($fila->rendg_tot_fc_caea ?? 0), 2);
            $neto -= round((float) ($fila->rendg_tot_nc_caea ?? 0), 2);
        }

        return round($neto, 2);
    }

    private function ajustarTotalBrutoPorCaeaEstacionamientoAjena(
        int $empresaId,
        int $fechaEntera,
        string $identificadorPc,
        float $totalRendg,
        float $zPortadora,
        float $erpCae,
        float $erpCaea,
        float $tolerancia,
    ): float {
        if ($fechaEntera <= 0) {
            return $totalRendg;
        }

        $fechaJornada = substr((string) $fechaEntera, 0, 4).'-'
            .substr((string) $fechaEntera, 4, 2).'-'
            .substr((string) $fechaEntera, 6, 2);

        return app(GastronomiaConciliacionCaeaCompartidoRendgSupport::class)
            ->ajustarTotalBrutoGastroExcluyendoCaeaEstacionamientoAjena(
                $empresaId,
                $fechaJornada,
                $identificadorPc,
                $totalRendg,
                $zPortadora,
                $erpCae,
                $erpCaea,
                $tolerancia,
            );
    }

    public function resolverTotalBrutoHost(
        float $zPortadora,
        float $caeaNeto,
        float $erpCae,
        float $erpCaea,
        float $tolerancia,
    ): float {
        $erpTotal = round($erpCae + $erpCaea, 2);

        if ($caeaNeto <= $tolerancia) {
            return $zPortadora;
        }

        if (abs($zPortadora - $erpTotal) <= $tolerancia) {
            return $zPortadora;
        }

        if (abs($zPortadora - $erpCae) <= $tolerancia && $erpCaea > $tolerancia) {
            return round($zPortadora + $caeaNeto, 2);
        }

        $conCaea = round($zPortadora + $caeaNeto, 2);
        if (abs($conCaea - $erpTotal) < abs($zPortadora - $erpTotal)) {
            return $conCaea;
        }

        return $zPortadora;
    }

    /**
     * @return array{total: null, z_portadora: null, caea_neto: float, suc_caea: null, portadora_nro_oper: null}
     */
    private function totalBrutoVacio(): array
    {
        return [
            'total' => null,
            'z_portadora' => null,
            'caea_neto' => 0.0,
            'suc_caea' => null,
            'portadora_nro_oper' => null,
        ];
    }

    /**
     * @param  list<object>  $cabeceras
     * @return array{total: float|null, total_x: float|null, tot_fc_caea: float|null, nro_oper: int|null, host: string|null}
     */
    private function sumarImportesPostCierreCabeceras(array $cabeceras): array
    {
        $total = 0.0;
        $totalX = 0.0;
        $totalFc = 0.0;
        $nroOper = null;
        foreach ($cabeceras as $cab) {
            $mapped = $this->mapImportePostCierreCabecera($cab);
            $total += (float) ($mapped['total'] ?? 0);
            $totalX += (float) ($mapped['total_x'] ?? 0);
            $totalFc += (float) ($mapped['tot_fc_caea'] ?? 0);
            $nroOper = $mapped['nro_oper'] ?? $nroOper;
        }

        return [
            'total' => round($total, 2),
            'total_x' => round($totalX, 2),
            'tot_fc_caea' => round($totalFc, 2),
            'nro_oper' => $nroOper,
            'host' => CierreJornadaProcesoRendicionAnitaSupport::HOST,
        ];
    }

    /**
     * @return array{total: float|null, total_x: float|null, tot_fc_caea: float|null, nro_oper: int|null, host: string|null}
     */
    private function mapImportePostCierreCabecera(object $cab): array
    {
        $z = round((float) ($cab->rendg_total_z ?? 0), 2);
        $x = round((float) ($cab->rendg_total_x ?? 0), 2);
        $fcCaea = round((float) ($cab->rendg_tot_fc_caea ?? 0), 2);
        // Batch CIERRE-WAITRY: total_x = post-cierre; Z puede arrastrar CAEA de salón.
        $total = $x > 0 ? $x : ($z > 0 ? $z : ($fcCaea > 0 ? $fcCaea : null));

        return [
            'total' => $total,
            'total_x' => $x > 0 ? $x : null,
            'tot_fc_caea' => $fcCaea > 0 ? $fcCaea : null,
            'nro_oper' => (int) ($cab->rendg_nro_oper ?? 0) ?: null,
            'host' => trim((string) ($cab->rendg_host ?? '')) ?: null,
        ];
    }

    /**
     * @return array{total: null, total_x: null, tot_fc_caea: null, nro_oper: null, host: null}
     */
    private function importePostCierreVacio(): array
    {
        return [
            'total' => null,
            'total_x' => null,
            'tot_fc_caea' => null,
            'nro_oper' => null,
            'host' => null,
        ];
    }

    /**
     * Cuando nro_oper del snapshot fue reutilizado por otra jornada, usar total del snapshot ERP.
     *
     * @return array{total: float|null, total_x: float|null, tot_fc_caea: float|null, nro_oper: int|null, host: string|null}
     */
    private function importePostCierreDesdeSnapshot(?int $nroOperSnapshot, ?float $totalXSnapshot): array
    {
        $total = $totalXSnapshot !== null && $totalXSnapshot > 0
            ? round($totalXSnapshot, 2)
            : null;

        if ($total === null) {
            return $this->importePostCierreVacio();
        }

        return [
            'total' => $total,
            'total_x' => $total,
            'tot_fc_caea' => $total,
            'nro_oper' => ($nroOperSnapshot !== null && $nroOperSnapshot > 0) ? $nroOperSnapshot : null,
            'host' => CierreJornadaProcesoRendicionAnitaSupport::HOST,
        ];
    }

    /**
     * Neto rendg por host/terminal: Z de la portadora (N→T→M) menos Σ rendg_tot_nc (+ CAEA) de todos los turnos del grupo.
     * Las NC en turno M/T deben restarse aunque la portadora sea turno N.
     *
     * Para grupos de estacionamiento usar $incluirNcEstacionamiento = true: sus cabeceras son estac y
     * {@see sumaNcCabeceras()} las excluye por default (regla del neto de gastronomía), lo que dejaría
     * la NC de estacionamiento sin restar e inflaría el neto (falso DIF en el circuito FLASH estac).
     *
     * @param  list<object>  $cabeceras
     */
    public function netoGrupoHost(array $cabeceras, bool $incluirNcEstacionamiento = false): float
    {
        if ($cabeceras === []) {
            return 0.0;
        }

        $portadora = $this->elegirPortadora($cabeceras);
        $z = round((float) ($portadora->rendg_total_z ?? 0), 2);

        return round($z - $this->sumaNcCabeceras($cabeceras, $incluirNcEstacionamiento), 2);
    }

    /**
     * Rendg neto del día (Informix rendgastro) desglosado por unidad de negocio para cuadre flash.
     * Gastro: salón (hosts PC) + post-cierre Waitry + agregados CAEA; excluye estacionamiento y vending.
     * Estacionamiento: cabeceras clasificadas por {@see esCabeceraEstacionamiento()}.
     *
     * @return array{gastro: float, estacionamiento: float}
     */
    public function totalesNetoRendgPorUnidadNegocio(int $empresaId, int $fechaEntera): array
    {
        if ($empresaId <= 0 || $fechaEntera <= 0) {
            return ['gastro' => 0.0, 'estacionamiento' => 0.0];
        }

        $cabeceras = $this->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);
        $vendingSupport = app(GastronomiaConciliacionVendingRendgSupport::class);

        /** @var array<string, list<object>> $gruposGastro */
        $gruposGastro = [];
        /** @var array<string, list<object>> $gruposEstacionamiento */
        $gruposEstacionamiento = [];

        foreach ($cabeceras as $fila) {
            if ($this->esCabeceraEstacionamiento($fila)) {
                $gruposEstacionamiento[$this->claveGrupoRendgHost($fila)][] = $fila;

                continue;
            }

            if ($vendingSupport->esCabeceraVendingCuadreJornada($fila, $empresaId)) {
                continue;
            }

            if ($this->esCabeceraPostCierreWaitry($fila) || $this->esCabeceraAgregadosCaea($fila)) {
                $gruposGastro['__sueltas__'.(int) ($fila->rendg_nro_oper ?? 0)][] = $fila;

                continue;
            }

            $host = trim((string) ($fila->rendg_host ?? ''));
            if ($host === '') {
                continue;
            }

            $gruposGastro[$this->claveGrupoRendgHost($fila)][] = $fila;
        }

        $gastro = 0.0;
        foreach ($gruposGastro as $grupo) {
            $gastro += $this->netoGrupoHost($grupo);
        }

        $estacionamiento = 0.0;
        foreach ($gruposEstacionamiento as $grupo) {
            $estacionamiento += $this->netoGrupoHost($grupo, true);
        }

        return [
            'gastro' => round($gastro, 2),
            'estacionamiento' => round($estacionamiento, 2),
        ];
    }

    private function claveGrupoRendgHost(object $fila): string
    {
        $host = trim((string) ($fila->rendg_host ?? ''));

        return $host !== ''
            ? 'host:'.$host
            : 'suc:'.(int) ($fila->rendg_sucursal ?? 0);
    }

    /**
     * Σ rendg_tot_nc (+ CAEA) de cabeceras del host.
     *
     * Por default excluye estacionamiento (para el neto de gastronomía no deben mezclarse las NC de estac).
     * Con $incluirEstacionamiento = true suma también las NC de estac (neto del propio grupo de estacionamiento).
     *
     * @param  list<object>  $cabeceras
     */
    public function sumaNcCabeceras(array $cabeceras, bool $incluirEstacionamiento = false): float
    {
        $nc = 0.0;
        foreach ($cabeceras as $fila) {
            if (! $incluirEstacionamiento && $this->esCabeceraEstacionamiento($fila)) {
                continue;
            }
            $nc += round((float) ($fila->rendg_tot_nc ?? 0), 2);
            $nc += round((float) ($fila->rendg_tot_nc_caea ?? 0), 2);
        }

        return round($nc, 2);
    }

    public function ncPorHost(int $empresaId, int $fechaEntera, string $identificadorPc): float
    {
        $host = trim($identificadorPc);
        if ($host === '') {
            return 0.0;
        }

        $cabeceras = $this->filtrarCabecerasPorHost(
            $this->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera),
            $host,
        );

        return $this->sumaNcCabeceras($cabeceras);
    }

    /**
     * Elige la cabecera que debe portar Z/NC del día: N → T → M.
     *
     * @param  list<object>  $cabeceras
     */
    public function elegirPortadora(array $cabeceras): object
    {
        /** @var array<string, list<object>> $porLetra */
        $porLetra = [];
        foreach ($cabeceras as $fila) {
            $letra = $this->letraTurnoDesdeCabecera($fila);
            $porLetra[$letra][] = $fila;
        }

        foreach (self::SECUENCIA_TURNO_PORTADORA as $letra) {
            if (! empty($porLetra[$letra])) {
                return $this->elegirUnaEntreMismoTurno($porLetra[$letra]);
            }
        }

        return $this->elegirUnaEntreMismoTurno($cabeceras);
    }

    /**
     * @param  list<object>  $cabeceras
     * @return list<array{nro_oper:int, turno:string, hora:string, turno_erp:string, z:float, tot_nc:float, portadora:bool}>
     */
    public function detalleCabecerasOrdenado(array $cabeceras, int $portadoraNroOper): array
    {
        $copia = $cabeceras;
        usort($copia, function (object $a, object $b) use ($portadoraNroOper): int {
            $aEsPortadora = (int) ($a->rendg_nro_oper ?? 0) === $portadoraNroOper;
            $bEsPortadora = (int) ($b->rendg_nro_oper ?? 0) === $portadoraNroOper;
            if ($aEsPortadora !== $bEsPortadora) {
                return $bEsPortadora <=> $aEsPortadora;
            }

            $prioA = $this->prioridadLetraTurno($this->letraTurnoDesdeCabecera($a));
            $prioB = $this->prioridadLetraTurno($this->letraTurnoDesdeCabecera($b));
            if ($prioA !== $prioB) {
                return $prioA <=> $prioB;
            }

            return $this->compararPorHoraYNroOper($a, $b);
        });

        $detalle = [];
        foreach ($copia as $fila) {
            $nroOper = (int) ($fila->rendg_nro_oper ?? 0);
            if ($nroOper <= 0) {
                continue;
            }
            $detalle[] = [
                'nro_oper' => $nroOper,
                'turno' => $this->letraTurnoDesdeCabecera($fila),
                'hora' => (string) ($fila->rendg_hora ?? ''),
                'turno_erp' => (string) ($fila->rendg_nro_rend_vta ?? ''),
                'z' => round((float) ($fila->rendg_total_z ?? 0), 2),
                'tot_nc' => round((float) ($fila->rendg_tot_nc ?? 0), 2),
                'portadora' => $nroOper === $portadoraNroOper,
            ];
        }

        return $detalle;
    }

    public function codigoPuntoventaEntero(?string $codigo): int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return 0;
        }

        return (int) preg_replace('/\D+/', '', $codigo);
    }

    /**
     * @param  list<object>  $grupo
     */
    private function elegirUnaEntreMismoTurno(array $grupo): object
    {
        $copia = $grupo;
        usort($copia, fn (object $a, object $b): int => $this->compararPorHoraYNroOper($a, $b));

        return $copia[0];
    }

    private function prioridadLetraTurno(string $letra): int
    {
        $idx = array_search($letra, self::SECUENCIA_TURNO_PORTADORA, true);

        return $idx === false ? 99 : $idx;
    }

    private function compararPorHoraYNroOper(object $a, object $b): int
    {
        $segA = $this->segundosDesdeHora((string) ($a->rendg_hora ?? ''));
        $segB = $this->segundosDesdeHora((string) ($b->rendg_hora ?? ''));
        if ($segA !== $segB) {
            return $segB <=> $segA;
        }

        return (int) ($b->rendg_nro_oper ?? 0) <=> (int) ($a->rendg_nro_oper ?? 0);
    }

    private function letraTurnoDesdeCabecera(object $fila): string
    {
        $letra = trim((string) ($fila->rendg_turno ?? ''));
        if ($letra !== '' && $letra !== ' ') {
            return mb_strtoupper(mb_substr($letra, 0, 1));
        }

        $turnoOperativoId = (int) ($fila->rendg_nro_rend_vta ?? 0);
        if ($turnoOperativoId <= 0) {
            return '?';
        }

        if (! array_key_exists($turnoOperativoId, $this->letraTurnoPorTurnoOperativo)) {
            $turno = TurnoOperativoGastronomia::query()
                ->with('turno')
                ->find($turnoOperativoId);
            $nombre = trim((string) ($turno?->turno?->nombre ?? ''));
            $this->letraTurnoPorTurnoOperativo[$turnoOperativoId] = self::letraTurnoDesdeNombre($nombre);
        }

        return $this->letraTurnoPorTurnoOperativo[$turnoOperativoId];
    }

    private function segundosDesdeHora(string $hora): int
    {
        $hora = trim($hora);
        if ($hora === '') {
            return 0;
        }

        $partes = array_map('intval', explode(':', $hora));

        return ($partes[0] * 3600) + (($partes[1] ?? 0) * 60) + ($partes[2] ?? 0);
    }
}
