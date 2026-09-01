<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Models\Ventas\ArcaCaea;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturaelectronicaService;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use App\Support\Ventas\ArcaCaeaAnitaIvaVentasSupport;
use App\Support\Ventas\ArcaCaeaAnitaTipoAfipSupport;
use App\Support\Ventas\ArcaCaeaAnitaVencaeConsultaSupport;
use App\Support\Ventas\ArcaCaeaInformeDatosDesdeAnitaSupport;
use App\Support\Ventas\ArcaCaeaInformeDatosDesdeVentaSupport;
use App\Support\Ventas\ArcaCaeaSucursalesInformeSupport;
use App\Support\Ventas\ArcaPuntoventaWebserviceSupport;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use App\Support\Ventas\CaeaQuincenaSupport;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Presentación quincenal de comprobantes emitidos bajo CAEA (WSFE FECAEARegInformativo / MTXCA informarComprobanteCAEA).
 */
class ArcaCaeaPresentacionService
{
    public function __construct(
        private FacturaelectronicaService $facturaelectronicaService,
        private ArcaCaeaQuincenalOrquestadorService $orquestador,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resumirPeriodo(ArcaCaea $registro, ?array $ultimosArca = null): array
    {
        $registro->loadMissing('empresa');
        $empresaId = (int) $registro->empresa_id;
        $nroCaea = trim((string) ($registro->nro_caea ?? ''));

        $query = $this->queryVentasPeriodo($registro);
        $total = (clone $query)->count();

        $ok = (clone $query)->where('venta.caea_informado_estado', 'ok')->count();
        $obs = (clone $query)->where('venta.caea_informado_estado', 'observacion')->count();
        $error = (clone $query)->where('venta.caea_informado_estado', 'error')->count();
        $pendientes = (clone $query)->whereNull('venta.caea_informado_estado')->count();

        $anita = $this->resumenAnitaIvaVentasPeriodo($registro, $ultimosArca ?? []);
        $total += $anita['total'];
        $ok += $anita['informados_ok'];
        $pendientes += $anita['pendientes'];

        $porTipo = $this->ultimosPorTipoPv($registro, $ultimosArca);
        $cola = $this->analizarColaInformeArca($registro, $ultimosArca ?? []);

        return [
            'total' => $total,
            'informados_ok' => $ok,
            'informados_obs' => $obs,
            'errores' => $error,
            'pendientes' => $pendientes,
            'informables_ahora' => $cola['informables_ahora'],
            'bloqueados_hueco' => $cola['bloqueados_hueco'],
            'cola_informe' => $cola['cola_informe'],
            'por_tipo_pv' => $porTipo,
            'ultimos_arca' => $ultimosArca ?? [],
            'webservice' => $this->orquestador->webserviceCaeaEmpresa($empresaId),
            'nro_caea' => $nroCaea,
        ];
    }

    /**
     * Determina cuántos comprobantes de la quincena pueden informarse ahora (número = último ARCA + 1).
     *
     * @param  list<array<string, mixed>>  $ultimosArca
     * @return array{
     *   informables_ahora: int,
     *   bloqueados_hueco: int,
     *   cola_informe: list<array<string, mixed>>
     * }
     */
    private function analizarColaInformeArca(ArcaCaea $registro, array $ultimosArca): array
    {
        if ($ultimosArca === []) {
            return ['informables_ahora' => 0, 'bloqueados_hueco' => 0, 'cola_informe' => []];
        }

        $ultimosMap = [];
        foreach ($ultimosArca as $ua) {
            $pto = (int) ($ua['pto_vta'] ?? 0);
            $tipo = (int) ($ua['tipo_afip'] ?? 0);
            if ($pto > 0 && $tipo > 0) {
                $ultimosMap[$this->claveGrupoInforme($pto, $tipo)] = (int) ($ua['ultimo_arca'] ?? 0);
            }
        }

        /** @var Collection<int, Venta> $ventas */
        $ventas = $this->queryVentasPeriodo($registro)
            ->with(['puntoventas', 'tipotransacciones'])
            ->where(function (Builder $q): void {
                $q->whereNull('venta.caea_informado_estado')
                    ->orWhere('venta.caea_informado_estado', 'error');
            })
            ->orderBy('venta.puntoventa_id')
            ->orderBy('venta.tipotransaccion_id')
            ->orderBy('venta.numerocomprobante')
            ->get();

        $informables = 0;
        $bloqueados = 0;
        $colaPorGrupo = [];

        foreach ($ventas as $venta) {
            $cbteTipo = $this->cbteTipoDesdeVenta($venta);
            $ptoVta = (int) ($venta->puntoventas->codigo ?? 0);
            if ($cbteTipo <= 0 || $ptoVta <= 0) {
                continue;
            }

            $clave = $this->claveGrupoInforme($ptoVta, $cbteTipo);
            if (! array_key_exists($clave, $ultimosMap)) {
                continue;
            }

            $ultimoArca = (int) $ultimosMap[$clave];
            $numero = (int) $venta->numerocomprobante;
            if ($numero <= $ultimoArca) {
                continue;
            }

            $proximoEsperado = $ultimoArca + 1;
            if ($numero === $proximoEsperado) {
                $informables++;
                $colaPorGrupo[$clave] = [
                    'pto_vta' => $ptoVta,
                    'tipo_afip' => $cbteTipo,
                    'proximo_numero' => $proximoEsperado,
                    'primer_pendiente_esta_quincena' => $numero,
                    'en_esta_quincena' => true,
                    'informable_ahora' => true,
                ];
            } else {
                $bloqueados++;
                if (! isset($colaPorGrupo[$clave])) {
                    // Ubicar el verdadero próximo esperado (puede estar en otra quincena o faltar en ERP).
                    $ubicacion = $this->ubicacionProximoComprobanteEmpresa(
                        (int) $registro->empresa_id,
                        $ptoVta,
                        $cbteTipo,
                        $proximoEsperado,
                        (int) $registro->periodo,
                        (int) $registro->orden,
                    ) ?? [
                        'pto_vta' => $ptoVta,
                        'tipo_afip' => $cbteTipo,
                        'proximo_numero' => $proximoEsperado,
                        'primer_pendiente' => $numero,
                        'en_esta_quincena' => false,
                        'informable_ahora' => false,
                        'falta_en_erp' => true,
                    ];
                    // Siempre dejar visible el 1º pendiente de ESTA quincena (aunque ARCA espere otro).
                    $ubicacion['primer_pendiente_esta_quincena'] = $numero;
                    $ubicacion['pendientes_esta_quincena'] = true;
                    $colaPorGrupo[$clave] = $ubicacion;
                }
            }
        }

        $this->analizarColaInformeAnita($registro, $ultimosMap, $colaPorGrupo, $informables, $bloqueados);

        // No rellenar la cola con PV/tipos de otras quincenas de la empresa:
        // en el index solo interesa qué falta informar de ESTA quincena.

        return [
            'informables_ahora' => $informables,
            'bloqueados_hueco' => $bloqueados,
            'cola_informe' => array_values($colaPorGrupo),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ubicacionProximoComprobanteEmpresa(
        int $empresaId,
        int $ptoVta,
        int $tipoAfip,
        int $proximoNumero,
        int $periodoActual,
        int $ordenActual,
    ): ?array {
        // 1) ¿Existe el número esperado en ERP aunque sin CAEA? (no es "falta en ERP").
        $sinCaea = $this->buscarVentaPvTipoSinFiltrarCae($empresaId, $ptoVta, $tipoAfip, $proximoNumero);
        if ($sinCaea !== null) {
            $tieneCae = trim((string) ($sinCaea->cae ?? '')) !== '';
            if (! $tieneCae) {
                $pq = CaeaQuincenaSupport::periodoOrdenDesdeFecha((string) $sinCaea->fecha);

                return [
                    'pto_vta' => $ptoVta,
                    'tipo_afip' => $tipoAfip,
                    'proximo_numero' => $proximoNumero,
                    'primer_pendiente' => $proximoNumero,
                    'en_esta_quincena' => (int) $pq['periodo'] === $periodoActual && (int) $pq['orden'] === $ordenActual,
                    'informable_ahora' => false,
                    'existe_sin_caea' => true,
                    'quincena_pendiente' => $pq,
                ];
            }
        }

        // 2) Solo desde el número que ARCA espera, con CAEA (informables / hueco real).
        $rows = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->where('puntoventa.codigo', $ptoVta)
            ->where('puntoventa.modofacturacion', 'A')
            ->whereNotNull('venta.cae')
            ->where('venta.cae', '!=', '')
            ->where('venta.numerocomprobante', '>=', $proximoNumero)
            ->where(function ($q): void {
                $q->whereNull('venta.caea_informado_estado')
                    ->orWhere('venta.caea_informado_estado', 'error');
            })
            ->select([
                'venta.numerocomprobante',
                'venta.fecha',
                'venta.codigo as codigo_venta',
                'tipotransaccion.codigo as tipo_codigo',
            ])
            ->orderBy('venta.numerocomprobante')
            ->limit(500)
            ->get();

        foreach ($rows as $row) {
            $tipo = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                (string) $row->tipo_codigo,
                (string) $row->codigo_venta,
            );
            if ($tipo !== $tipoAfip) {
                continue;
            }

            $numero = (int) $row->numerocomprobante;

            if ($numero === $proximoNumero) {
                $pq = CaeaQuincenaSupport::periodoOrdenDesdeFecha((string) $row->fecha);
                $enEstaQuincena = (int) $pq['periodo'] === $periodoActual && (int) $pq['orden'] === $ordenActual;

                return [
                    'pto_vta' => $ptoVta,
                    'tipo_afip' => $tipoAfip,
                    'proximo_numero' => $proximoNumero,
                    'primer_pendiente' => $numero,
                    'en_esta_quincena' => $enEstaQuincena,
                    'informable_ahora' => $enEstaQuincena,
                    'quincena_pendiente' => $enEstaQuincena ? null : $pq,
                ];
            }

            // Hay salto: el esperado no tiene CAEA (o no existe) y el siguiente con CAEA es mayor.
            $sinCaeaEnHueco = $this->buscarVentaPvTipoSinFiltrarCae($empresaId, $ptoVta, $tipoAfip, $proximoNumero);

            return [
                'pto_vta' => $ptoVta,
                'tipo_afip' => $tipoAfip,
                'proximo_numero' => $proximoNumero,
                'primer_pendiente' => $numero,
                'en_esta_quincena' => false,
                'informable_ahora' => false,
                'falta_en_erp' => $sinCaeaEnHueco === null,
                'existe_sin_caea' => $sinCaeaEnHueco !== null && trim((string) ($sinCaeaEnHueco->cae ?? '')) === '',
                'quincena_pendiente' => CaeaQuincenaSupport::periodoOrdenDesdeFecha((string) $row->fecha),
            ];
        }

        $sinCaeaFinal = $this->buscarVentaPvTipoSinFiltrarCae($empresaId, $ptoVta, $tipoAfip, $proximoNumero);
        $anita = ArcaCaeaAnitaVencaeConsultaSupport::buscarIvaVentasPorAfip($ptoVta, $proximoNumero, $tipoAfip);
        if ($anita !== null) {
            $enEstaQuincena = $this->caeaAnitaEsDeQuincena(
                $empresaId,
                (string) $anita['nro_caea'],
                $periodoActual,
                $ordenActual,
            );

            return [
                'pto_vta' => $ptoVta,
                'tipo_afip' => $tipoAfip,
                'proximo_numero' => $proximoNumero,
                'primer_pendiente' => $proximoNumero,
                'en_esta_quincena' => $enEstaQuincena,
                'informable_ahora' => $enEstaQuincena,
                'fuente' => 'anita',
                'tipo_anita' => $anita['tipo_anita'],
                'letra' => $anita['letra'],
                'falta_en_erp' => $sinCaeaFinal === null,
            ];
        }

        return [
            'pto_vta' => $ptoVta,
            'tipo_afip' => $tipoAfip,
            'proximo_numero' => $proximoNumero,
            'primer_pendiente' => null,
            'en_esta_quincena' => false,
            'informable_ahora' => false,
            'falta_en_erp' => $sinCaeaFinal === null,
            'existe_sin_caea' => $sinCaeaFinal !== null && trim((string) ($sinCaeaFinal->cae ?? '')) === '',
        ];
    }

    /**
     * Busca una venta del PV/tipo AFIP por número, con o sin CAEA.
     */
    private function buscarVentaPvTipoSinFiltrarCae(int $empresaId, int $ptoVta, int $tipoAfip, int $numero): ?object
    {
        $rows = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->where('puntoventa.codigo', $ptoVta)
            ->where('puntoventa.modofacturacion', 'A')
            ->where('venta.numerocomprobante', $numero)
            ->select([
                'venta.numerocomprobante',
                'venta.fecha',
                'venta.cae',
                'venta.codigo as codigo_venta',
                'tipotransaccion.codigo as tipo_codigo',
            ])
            ->limit(20)
            ->get();

        foreach ($rows as $row) {
            $tipo = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                (string) $row->tipo_codigo,
                (string) $row->codigo_venta,
            );
            if ($tipo === $tipoAfip) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Marca como informados los comprobantes de la quincena con número ≤ último autorizado en ARCA.
     * Si no se pasa $consultaEmpresa, consulta ARCA una sola vez a nivel empresa (PV+tipo).
     *
     * @param  array{ultimos_arca: list<array<string, mixed>>, ultimos_arca_map: array<string, int>, errores_consulta: list<array<string, mixed>>}|null  $consultaEmpresa
     * @return array{marcados: int, ultimos_arca: list<array<string, mixed>>, ultimos_arca_map: array<string, int>, errores_consulta: list<array<string, mixed>>}
     */
    public function sincronizarInformadosDesdeArca(ArcaCaea $registro, ?array $consultaEmpresa = null): array
    {
        $registro->loadMissing('empresa');
        $consulta = $consultaEmpresa ?? $this->consultarUltimosAutorizadosArcaEmpresa(
            (int) $registro->empresa_id,
            [$registro],
        );
        $ultimosArca = $consulta['ultimos_arca'];
        $ultimosMap = $consulta['ultimos_arca_map'];
        $marcados = 0;

        if ($ultimosMap === []) {
            return [
                'marcados' => 0,
                'ultimos_arca' => $ultimosArca,
                'ultimos_arca_map' => $ultimosMap,
                'errores_consulta' => $consulta['errores_consulta'],
            ];
        }

        /** @var Collection<int, Venta> $ventas */
        $ventas = $this->queryVentasPeriodo($registro)
            ->with(['puntoventas', 'tipotransacciones'])
            ->where(function (Builder $q): void {
                $q->whereNull('venta.caea_informado_estado')
                    ->orWhere('venta.caea_informado_estado', 'error');
            })
            ->orderBy('venta.puntoventa_id')
            ->orderBy('venta.tipotransaccion_id')
            ->orderBy('venta.numerocomprobante')
            ->get();

        foreach ($ventas as $venta) {
            $cbteTipo = $this->cbteTipoDesdeVenta($venta);
            if ($cbteTipo <= 0) {
                continue;
            }

            $ptoVta = (int) ($venta->puntoventas->codigo ?? 0);
            if ($ptoVta <= 0) {
                continue;
            }

            $clave = $this->claveGrupoInforme($ptoVta, $cbteTipo);
            if (! array_key_exists($clave, $ultimosMap)) {
                continue;
            }

            $ultimoArca = (int) $ultimosMap[$clave];
            $numero = (int) $venta->numerocomprobante;
            if ($numero <= $ultimoArca) {
                $this->marcarVentaInforme(
                    $venta,
                    'ok',
                    null,
                    sprintf('Ya informado en ARCA (último autorizado: %d)', $ultimoArca),
                );
                $marcados++;
            } elseif ($numero > $ultimoArca + 1 && $venta->caea_informado_estado === 'error') {
                $this->marcarVentaPendienteInforme(
                    $venta,
                    sprintf(
                        'Pendiente: ARCA espera el comprobante #%d (último autorizado: %d)',
                        $ultimoArca + 1,
                        $ultimoArca,
                    ),
                );
            }
        }

        return [
            'marcados' => $marcados,
            'ultimos_arca' => $ultimosArca,
            'ultimos_arca_map' => $ultimosMap,
            'errores_consulta' => $consulta['errores_consulta'],
        ];
    }

    /**
     * Consulta FECompUltimoAutorizado (o MTXCA) una sola vez por cada PV+tipo.
     * El último autorizado es global (no por quincena): con ese mapa se deduce hasta qué
     * período está transferido y cuál es el próximo comprobante a informar.
     *
     * @param  list<ArcaCaea>|null  $registros  Si se pasan, une combinaciones de esas quincenas (típico del index).
     * @return array{ultimos_arca: list<array<string, mixed>>, ultimos_arca_map: array<string, int>, errores_consulta: list<array<string, mixed>>}
     */
    public function consultarUltimosAutorizadosArcaEmpresa(int $empresaId, ?iterable $registros = null): array
    {
        if ($registros !== null) {
            $combinaciones = $this->listarCombinacionesTipoPvDeRegistros($registros);
        } else {
            $combinaciones = $this->listarCombinacionesTipoPvEmpresa($empresaId);
        }

        return $this->consultarUltimosAutorizadosArcaCombinaciones($empresaId, $combinaciones);
    }

    /**
     * @deprecated Preferir consultarUltimosAutorizadosArcaEmpresa (el último ARCA no es por quincena).
     *
     * @return array{ultimos_arca: list<array<string, mixed>>, ultimos_arca_map: array<string, int>, errores_consulta: list<array<string, mixed>>}
     */
    public function consultarUltimosAutorizadosArca(ArcaCaea $registro): array
    {
        return $this->consultarUltimosAutorizadosArcaEmpresa((int) $registro->empresa_id, [$registro]);
    }

    /**
     * @param  list<array<string, mixed>>  $combinaciones
     * @return array{ultimos_arca: list<array<string, mixed>>, ultimos_arca_map: array<string, int>, errores_consulta: list<array<string, mixed>>}
     */
    private function consultarUltimosAutorizadosArcaCombinaciones(int $empresaId, array $combinaciones): array
    {
        if ($combinaciones === []) {
            return ['ultimos_arca' => [], 'ultimos_arca_map' => [], 'errores_consulta' => []];
        }

        $pvIds = array_values(array_unique(array_column($combinaciones, 'puntoventa_id')));
        /** @var Collection<int, Puntoventa> $puntos */
        $puntos = Puntoventa::query()->whereIn('id', $pvIds)->get()->keyBy('id');

        $ultimosArca = [];
        $ultimosMap = [];
        $erroresConsulta = [];
        $consultadoAt = now()->toIso8601String();

        foreach ($combinaciones as $combo) {
            $puntoventa = $puntos->get((int) $combo['puntoventa_id']);
            if ($puntoventa === null) {
                continue;
            }

            $ptoVta = (int) $combo['pto_vta'];
            $tipoAfip = (int) $combo['tipo_afip'];
            $clave = $this->claveGrupoInforme($ptoVta, $tipoAfip);

            try {
                $ultimo = $this->facturaelectronicaService->ultimoComprobanteAutorizadoEnArca(
                    $puntoventa,
                    $tipoAfip,
                    ['notificar_failover_transporte_en_capa_superior' => true],
                );
                $ultimosMap[$clave] = $ultimo;
                $ultimosArca[] = [
                    'pto_vta' => $ptoVta,
                    'tipo_afip' => $tipoAfip,
                    'letra' => $combo['letra'] ?? '',
                    'ultimo_arca' => $ultimo,
                    'webservice' => (string) ($combo['webservice'] ?? ''),
                    'consultado_at' => $consultadoAt,
                ];
            } catch (\Throwable $e) {
                $erroresConsulta[] = [
                    'pto_vta' => $ptoVta,
                    'tipo_afip' => $tipoAfip,
                    'mensaje' => $e->getMessage(),
                ];
                Log::warning('arca:caea-informe — FECompUltimoAutorizado falló', [
                    'empresa_id' => $empresaId,
                    'pto_vta' => $ptoVta,
                    'tipo_afip' => $tipoAfip,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        return [
            'ultimos_arca' => $ultimosArca,
            'ultimos_arca_map' => $ultimosMap,
            'errores_consulta' => $erroresConsulta,
        ];
    }

    /**
     * @return list<array{venta_id:int, numero:int, pto_vta:int, codigo_error:?string, mensaje:string, informado_at:?string}>
     */
    public function listarErroresInforme(ArcaCaea $registro, int $limite = 50): array
    {
        $fechas = CaeaQuincenaSupport::fechasQuincena((int) $registro->periodo, (int) $registro->orden);
        $nroCaea = trim((string) ($registro->nro_caea ?? ''));

        $queryErrores = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('puntoventa.empresa_id', (int) $registro->empresa_id)
            ->where('puntoventa.modofacturacion', 'A')
            ->whereBetween('venta.fecha', [
                $fechas['desde']->toDateString(),
                $fechas['hasta']->toDateString(),
            ])
            ->whereNotNull('venta.cae')
            ->when($nroCaea !== '', fn ($q) => $q->where('venta.cae', $nroCaea))
            ->where('venta.caea_informado_estado', 'error')
            ->orderBy('venta.numerocomprobante')
            ->limit($limite);
        $this->aplicarFiltroSucursalesInforme($queryErrores, (int) $registro->empresa_id);
        $rows = $queryErrores->get([
                'venta.id as venta_id',
                'venta.numerocomprobante as numero',
                'puntoventa.codigo as pto_vta',
                'venta.caea_informado_codigo_error as codigo_error',
                'venta.caea_informado_mensaje as mensaje',
                'venta.caea_informado_at as informado_at',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'venta_id' => (int) $row->venta_id,
                'numero' => (int) $row->numero,
                'pto_vta' => (int) $row->pto_vta,
                'codigo_error' => $row->codigo_error !== null ? (string) $row->codigo_error : null,
                'mensaje' => trim((string) ($row->mensaje ?? '')),
                'informado_at' => $row->informado_at,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{codigo:?string, mensaje:string, cantidad:int}>
     */
    public function agruparErroresInforme(ArcaCaea $registro, int $limiteMensajes = 10): array
    {
        $fechas = CaeaQuincenaSupport::fechasQuincena((int) $registro->periodo, (int) $registro->orden);
        $nroCaea = trim((string) ($registro->nro_caea ?? ''));

        $queryAgrupados = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('puntoventa.empresa_id', (int) $registro->empresa_id)
            ->where('puntoventa.modofacturacion', 'A')
            ->whereBetween('venta.fecha', [
                $fechas['desde']->toDateString(),
                $fechas['hasta']->toDateString(),
            ])
            ->whereNotNull('venta.cae')
            ->when($nroCaea !== '', fn ($q) => $q->where('venta.cae', $nroCaea))
            ->where('venta.caea_informado_estado', 'error')
            ->select([
                'venta.caea_informado_codigo_error as codigo_error',
                'venta.caea_informado_mensaje as mensaje',
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->groupBy('venta.caea_informado_codigo_error', 'venta.caea_informado_mensaje')
            ->orderByDesc('cantidad')
            ->limit($limiteMensajes);
        $this->aplicarFiltroSucursalesInforme($queryAgrupados, (int) $registro->empresa_id);
        $rows = $queryAgrupados->get();

        $out = [];
        foreach ($rows as $row) {
            $mensaje = trim((string) ($row->mensaje ?? ''));
            $codigo = $row->codigo_error !== null && $row->codigo_error !== ''
                ? (string) $row->codigo_error
                : null;
            if ($codigo === null && preg_match('/\[(\d{3,5})\]/', $mensaje, $m)) {
                $codigo = $m[1];
            }
            $out[] = [
                'codigo' => $codigo,
                'mensaje' => $mensaje,
                'cantidad' => (int) $row->cantidad,
            ];
        }

        return $out;
    }

    /**
     * @return array{ok: bool, mensaje: string, resumen?: array<string, mixed>, detalle?: array<string, mixed>}
     */
    public function informarPeriodo(
        ArcaCaea $registro,
        ?int $usuarioId = null,
        bool $soloErrores = false,
        ?int $limite = null,
    ): array {
        if (! $registro->estaAutorizado()) {
            return ['ok' => false, 'mensaje' => 'El CAEA no está autorizado; no se puede informar comprobantes.'];
        }

        $registro->loadMissing('empresa');
        $empresa = $registro->empresa;
        if ($empresa === null || trim((string) $empresa->nroinscripcion) === '') {
            return ['ok' => false, 'mensaje' => 'Empresa sin CUIT configurado.'];
        }

        $nroCaea = trim((string) $registro->nro_caea);
        $caeaVigente = [
            'caea' => $nroCaea,
            'fechavencimientocae' => $registro->fecha_vigencia_hasta?->format('Ymd') ?? '',
        ];

        $limite = $limite ?? (int) config('arca.caea.informe_lote_max', 100);
        $limite = max(1, min(500, $limite));

        try {
            $sync = $this->sincronizarInformadosDesdeArca($registro);
        } catch (\Throwable $e) {
            if (ArcaWsfeEmisionResiliencia::clasificarError($e->getMessage()) === ArcaWsfeEmisionResiliencia::CLASE_TRANSPORTE) {
                return ['ok' => false, 'mensaje' => 'No se pudo consultar ARCA (último autorizado): '.$e->getMessage()];
            }

            $sync = [
                'marcados' => 0,
                'ultimos_arca' => [],
                'ultimos_arca_map' => [],
                'errores_consulta' => [['mensaje' => $e->getMessage()]],
            ];
        }

        /** @var array<string, int> $ultimosMemoria */
        $ultimosMemoria = $sync['ultimos_arca_map'];

        // Antes de enviar: si ARCA espera un número que no se puede informar, detener con mensaje claro.
        $bloqueoPrevio = $this->diagnosticarBloqueosColaAntesDeInformar($registro, $ultimosMemoria);
        if ($bloqueoPrevio !== null) {
            $resumen = $this->actualizarResumenPeriodo($registro, $usuarioId, false, $sync);

            return [
                'ok' => false,
                'mensaje' => $bloqueoPrevio,
                'resumen' => $resumen,
                'detalle' => [
                    'informados' => 0,
                    'con_observaciones' => 0,
                    'errores_lote' => 1,
                    'omitidos_ya_en_arca' => (int) ($sync['marcados'] ?? 0),
                    'omitidos_hueco_numeracion' => 0,
                    'sincronizados_arca' => (int) ($sync['marcados'] ?? 0),
                    'ultimos_arca' => $sync['ultimos_arca'] ?? [],
                    'errores_consulta_arca' => $sync['errores_consulta'] ?? [],
                    'errores_muestra' => [[
                        'venta_id' => 0,
                        'pto_vta' => null,
                        'numero' => 0,
                        'codigo' => null,
                        'mensaje' => $bloqueoPrevio,
                    ]],
                    'errores_agrupados' => [[
                        'codigo' => null,
                        'mensaje' => $bloqueoPrevio,
                        'cantidad' => 1,
                    ]],
                    'pendientes_restantes' => (int) ($resumen['pendientes'] ?? 0),
                    'errores_total' => (int) ($resumen['errores'] ?? 0),
                ],
            ];
        }

        $erroresConsulta = is_array($sync['errores_consulta'] ?? null) ? $sync['errores_consulta'] : [];
        if ($ultimosMemoria === [] && $erroresConsulta !== []) {
            $resumen = $this->actualizarResumenPeriodo($registro, $usuarioId, false, $sync);
            $msgs = [];
            foreach ($erroresConsulta as $err) {
                $m = trim((string) (is_array($err) ? ($err['mensaje'] ?? '') : ''));
                if ($m !== '' && ! in_array($m, $msgs, true)) {
                    $msgs[] = $m;
                }
            }

            return [
                'ok' => false,
                'mensaje' => 'No se pudo consultar el último autorizado en ARCA; no se informa a ciegas. '
                    .($msgs !== [] ? implode(' | ', $msgs) : 'Sin detalle de error.'),
                'resumen' => $resumen,
                'detalle' => [
                    'informados' => 0,
                    'errores_lote' => 1,
                    'omitidos_ya_en_arca' => (int) ($sync['marcados'] ?? 0),
                    'sincronizados_arca' => (int) ($sync['marcados'] ?? 0),
                    'ultimos_arca' => $sync['ultimos_arca'] ?? [],
                    'errores_consulta_arca' => $erroresConsulta,
                    'pendientes_restantes' => (int) ($resumen['pendientes'] ?? 0),
                    'errores_total' => (int) ($resumen['errores'] ?? 0),
                ],
            ];
        }

        $lote = $this->armarLoteInforme($registro, $soloErrores, $ultimosMemoria, $limite);

        if ($lote === []) {
            $resumen = $this->actualizarResumenPeriodo($registro, $usuarioId, false, $sync);
            $pendientes = (int) ($resumen['pendientes'] ?? 0);
            $erroresResumen = (int) ($resumen['errores'] ?? 0);
            if ($pendientes > 0 || $erroresResumen > 0) {
                return [
                    'ok' => false,
                    'mensaje' => 'Hay '.$pendientes.' comprobante(s) pendiente(s) pero no se armó lote. '
                        .'Sin último autorizado de ARCA el proceso arrancaría en #1 y no encuentra la factura. '
                        .'Usá la calculadora o reintentá el avión; si persiste, revisá MTXCA / sucursal 5.',
                    'resumen' => $resumen,
                    'detalle' => [
                        'informados' => 0,
                        'errores_lote' => 1,
                        'omitidos_ya_en_arca' => (int) ($sync['marcados'] ?? 0),
                        'sincronizados_arca' => (int) ($sync['marcados'] ?? 0),
                        'ultimos_arca' => $sync['ultimos_arca'] ?? [],
                        'errores_consulta_arca' => $erroresConsulta,
                        'pendientes_restantes' => $pendientes,
                        'errores_total' => $erroresResumen,
                    ],
                ];
            }

            return [
                'ok' => true,
                'mensaje' => $sync['marcados'] > 0
                    ? sprintf('No hay comprobantes pendientes. %d reconocido(s) ya informados en ARCA.', $sync['marcados'])
                    : 'No hay comprobantes pendientes de informar para esta quincena.',
                'resumen' => $resumen,
            ];
        }

        $informados = 0;
        $conError = 0;
        $conObs = 0;
        $omitidosArca = 0;
        $omitidosHueco = 0;
        $ultimoError = '';
        $erroresLote = [];
        $detenidoPorError = false;

        foreach ($lote as $item) {
            $resultadoItem = $this->informarItemLote(
                $registro,
                $empresa,
                $caeaVigente,
                $item,
                $ultimosMemoria,
            );

            $omitidosArca += (int) ($resultadoItem['omitidos_arca'] ?? 0);
            $omitidosHueco += (int) ($resultadoItem['omitidos_hueco'] ?? 0);
            $informados += (int) ($resultadoItem['informados'] ?? 0);
            $conObs += (int) ($resultadoItem['con_obs'] ?? 0);
            $conError += (int) ($resultadoItem['errores'] ?? 0);
            if (($resultadoItem['error_fila'] ?? null) !== null) {
                $erroresLote[] = $resultadoItem['error_fila'];
            }
            if (($resultadoItem['ultimo_error'] ?? '') !== '') {
                $ultimoError = (string) $resultadoItem['ultimo_error'];
            }
            if (! empty($resultadoItem['detener'])) {
                $detenidoPorError = true;
                break;
            }
        }

        $sync['marcados'] = (int) ($sync['marcados'] ?? 0) + $omitidosArca;
        $sync['ultimos_arca_map'] = $ultimosMemoria;
        $resumen = $this->actualizarResumenPeriodo($registro, $usuarioId, false, $sync);
        $detalle = [
            'informados' => $informados,
            'con_observaciones' => $conObs,
            'errores_lote' => $conError,
            'omitidos_ya_en_arca' => $omitidosArca,
            'omitidos_hueco_numeracion' => $omitidosHueco,
            'sincronizados_arca' => (int) ($sync['marcados'] ?? 0),
            'ultimos_arca' => $sync['ultimos_arca'] ?? [],
            'errores_consulta_arca' => $sync['errores_consulta'] ?? [],
            'errores_muestra' => $erroresLote,
            'errores_agrupados' => $this->agruparErroresInforme($registro, 8),
            'pendientes_restantes' => (int) ($resumen['pendientes'] ?? 0),
            'errores_total' => (int) ($resumen['errores'] ?? 0),
            'detenido_por_error' => $detenidoPorError,
        ];

        if ($conError > 0) {
            return [
                'ok' => false,
                'mensaje' => $detenidoPorError
                    ? 'Proceso detenido. '.$ultimoError
                    : 'No se pudo informar ningún comprobante. Último error: '.$ultimoError,
                'resumen' => $resumen,
                'detalle' => $detalle,
            ];
        }

        $msg = sprintf(
            'Informados: %d (%d con observaciones). Errores en lote: %d. Pendientes restantes: %d.',
            $informados,
            $conObs,
            $conError,
            (int) ($resumen['pendientes'] ?? 0),
        );
        if ($sync['marcados'] > 0) {
            $msg .= sprintf(' Reconocidos en ARCA: %d.', $sync['marcados']);
        }
        if ($omitidosHueco > 0) {
            $msg .= sprintf(' Omitidos por hueco de numeración: %d.', $omitidosHueco);
        }

        return ['ok' => true, 'mensaje' => $msg, 'resumen' => $resumen, 'detalle' => $detalle];
    }

    /**
     * @return array{venta_id:int, pto_vta:?int, numero:int, codigo:?string, mensaje:string}
     */
    private function filaErrorDetalle(Venta $venta, mixed $ptoVta, string $mensaje, ?string $codigo = null): array
    {
        return [
            'venta_id' => (int) $venta->id,
            'pto_vta' => $ptoVta !== null ? (int) $ptoVta : null,
            'numero' => (int) $venta->numerocomprobante,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
        ];
    }

    private function extraerCodigoError(string $mensaje): ?string
    {
        if (preg_match('/\[(\d{3,5})\]/', $mensaje, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Si el próximo número que ARCA espera no se puede informar (sin CAEA / no existe),
     * devolver mensaje y no iniciar el lote.
     *
     * @param  array<string, int>  $ultimosMemoria
     */
    private function diagnosticarBloqueosColaAntesDeInformar(ArcaCaea $registro, array $ultimosMemoria): ?string
    {
        if ($ultimosMemoria === []) {
            return null;
        }

        foreach ($ultimosMemoria as $clave => $ultimoArca) {
            [$ptoVta, $tipoAfip] = array_map('intval', explode('|', (string) $clave));
            if ($ptoVta <= 0 || $tipoAfip <= 0) {
                continue;
            }

            $proximo = (int) $ultimoArca + 1;
            $tienePendientesMayores = $this->hayPendienteMayorEnPeriodo(
                $registro,
                $ptoVta,
                $tipoAfip,
                $proximo,
            );
            $ubicacion = $this->ubicacionProximoComprobanteEmpresa(
                (int) $registro->empresa_id,
                $ptoVta,
                $tipoAfip,
                $proximo,
                (int) $registro->periodo,
                (int) $registro->orden,
            );

            if ($ubicacion !== null && ! empty($ubicacion['informable_ahora'])) {
                continue;
            }

            if (! $tienePendientesMayores) {
                continue;
            }

            if ($ubicacion === null) {
                continue;
            }

            if (! empty($ubicacion['existe_sin_caea']) || ! empty($ubicacion['falta_en_erp'])) {
                $primerConCaea = $this->primerNumeroConCaeaPendiente(
                    (int) $registro->empresa_id,
                    $ptoVta,
                    $tipoAfip,
                    $proximo + 1,
                );

                return $this->mensajeBloqueoProximoEsperado(
                    (int) $registro->empresa_id,
                    $ptoVta,
                    $tipoAfip,
                    $proximo,
                    $primerConCaea,
                );
            }
        }

        return null;
    }

    private function mensajeBloqueoProximoEsperado(
        int $empresaId,
        int $ptoVta,
        int $tipoAfip,
        int $proximo,
        int $primerConCaea = 0,
    ): string {
        $prefijo = sprintf(
            'PV %s %s #%d',
            str_pad((string) $ptoVta, 5, '0', STR_PAD_LEFT),
            $this->etiquetaTipoAfipCorta($tipoAfip),
            $proximo,
        );

        $row = $this->buscarVentaPvTipoSinFiltrarCae($empresaId, $ptoVta, $tipoAfip, $proximo);
        if ($row === null) {
            $msg = $prefijo.' no está en el ERP ni en Anita (IVA-ventas); ARCA espera ese número y no se puede continuar.';
        } elseif (trim((string) ($row->cae ?? '')) === '') {
            $msg = $prefijo.' existe en el ERP pero sin CAEA; no se puede informar ni continuar la correlatividad.';
        } else {
            $msg = $prefijo.' bloquea la correlatividad (ARCA espera ese número).';
        }

        if ($primerConCaea > $proximo) {
            $msg .= sprintf(' Primer comprobante con CAEA pendiente: #%d.', $primerConCaea);
        }

        return $msg;
    }

    private function etiquetaTipoAfipCorta(int $tipo): string
    {
        return match ($tipo) {
            1 => 'FA',
            2 => 'ND',
            3 => 'NC',
            6 => 'FB',
            7 => 'NDB',
            8 => 'NCB',
            11 => 'FC',
            12 => 'NDC',
            13 => 'NCC',
            default => 'T'.$tipo,
        };
    }

    /**
     * Primer número >= $desde con CAEA y pendiente de informar (mismo PV/tipo AFIP).
     */
    private function primerNumeroConCaeaPendiente(int $empresaId, int $ptoVta, int $tipoAfip, int $desde): int
    {
        $rows = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->where('puntoventa.codigo', $ptoVta)
            ->where('puntoventa.modofacturacion', 'A')
            ->whereNotNull('venta.cae')
            ->where('venta.cae', '!=', '')
            ->where('venta.numerocomprobante', '>=', $desde)
            ->where(function ($q): void {
                $q->whereNull('venta.caea_informado_estado')
                    ->orWhere('venta.caea_informado_estado', 'error');
            })
            ->select([
                'venta.numerocomprobante',
                'venta.codigo as codigo_venta',
                'tipotransaccion.codigo as tipo_codigo',
            ])
            ->orderBy('venta.numerocomprobante')
            ->limit(200)
            ->get();

        foreach ($rows as $row) {
            $tipo = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                (string) $row->tipo_codigo,
                (string) $row->codigo_venta,
            );
            if ($tipo === $tipoAfip) {
                return (int) $row->numerocomprobante;
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>|null  $syncPrevio  Resultado de sincronizarInformadosDesdeArca si ya se ejecutó
     * @param  array{ultimos_arca: list<array<string, mixed>>, ultimos_arca_map: array<string, int>, errores_consulta: list<array<string, mixed>>}|null  $consultaEmpresa
     * @return array<string, mixed>
     */
    public function actualizarResumenPeriodo(
        ArcaCaea $registro,
        ?int $usuarioId = null,
        bool $sincronizarArca = true,
        ?array $syncPrevio = null,
        ?array $consultaEmpresa = null,
    ): array {
        $syncMeta = ['marcados' => 0, 'ultimos_arca' => [], 'errores_consulta' => []];
        if ($syncPrevio !== null) {
            $syncMeta = [
                'marcados' => (int) ($syncPrevio['marcados'] ?? 0),
                'ultimos_arca' => is_array($syncPrevio['ultimos_arca'] ?? null) ? $syncPrevio['ultimos_arca'] : [],
                'errores_consulta' => is_array($syncPrevio['errores_consulta'] ?? null) ? $syncPrevio['errores_consulta'] : [],
            ];
        } elseif ($sincronizarArca) {
            $syncMeta = $this->sincronizarInformadosDesdeArca($registro, $consultaEmpresa);
        } elseif (is_array($registro->informe_resumen)) {
            $prev = $registro->informe_resumen;
            $syncMeta = [
                'marcados' => (int) ($prev['sincronizados_arca'] ?? 0),
                // El último ARCA es global por PV+tipo: no quedar pegado a un valor viejo
                // de esta quincena si otra quincena de la empresa ya consultó uno mayor.
                'ultimos_arca' => $this->ultimosArcaLocalEnriquecidos(
                    (int) $registro->empresa_id,
                    (int) $registro->id,
                    is_array($prev['ultimos_arca'] ?? null) ? $prev['ultimos_arca'] : [],
                ),
                'errores_consulta' => is_array($prev['errores_consulta_arca'] ?? null) ? $prev['errores_consulta_arca'] : [],
            ];
        }

        $resumen = $this->resumirPeriodo($registro, $syncMeta['ultimos_arca']);
        $resumen['sincronizados_arca'] = $syncMeta['marcados'];
        $resumen['errores_consulta_arca'] = $syncMeta['errores_consulta'];
        $estado = $this->resolverEstadoInforme($resumen);

        $registro->informe_estado = $estado;
        $registro->informe_resumen = $resumen;
        $registro->informe_procesado_at = now();
        if ($usuarioId !== null) {
            $registro->informe_usuario_id = $usuarioId;
        }
        $registro->save();

        return $resumen;
    }

    /**
     * Actualiza resúmenes de varias quincenas de la misma empresa consultando ARCA una sola vez.
     *
     * @param  iterable<int, ArcaCaea>  $registros
     * @return array<int, array<string, mixed>>  resumen por arca_caea.id
     */
    public function actualizarResumenesEmpresa(iterable $registros, ?int $usuarioId = null, bool $sincronizarArca = true): array
    {
        $porId = [];
        $empresaId = null;
        /** @var list<ArcaCaea> $autorizados */
        $autorizados = [];

        foreach ($registros as $registro) {
            if (! $registro instanceof ArcaCaea || ! $registro->estaAutorizado()) {
                continue;
            }
            $eid = (int) $registro->empresa_id;
            if ($empresaId === null) {
                $empresaId = $eid;
            } elseif ($eid !== $empresaId) {
                throw new \InvalidArgumentException('actualizarResumenesEmpresa requiere registros de una sola empresa.');
            }
            $autorizados[] = $registro;
        }

        if ($autorizados === [] || $empresaId === null) {
            return [];
        }

        $consultaEmpresa = null;
        if ($sincronizarArca) {
            $consultaEmpresa = $this->consultarUltimosAutorizadosArcaEmpresa($empresaId, $autorizados);
        }

        foreach ($autorizados as $registro) {
            $porId[(int) $registro->id] = $this->actualizarResumenPeriodo(
                $registro,
                $usuarioId,
                $sincronizarArca,
                null,
                $consultaEmpresa,
            );
        }

        return $porId;
    }

    /**
     * @return Builder<Venta>
     */
    private function queryVentasPeriodo(ArcaCaea $registro): Builder
    {
        $fechas = CaeaQuincenaSupport::fechasQuincena((int) $registro->periodo, (int) $registro->orden);
        $nroCaea = trim((string) ($registro->nro_caea ?? ''));
        $excluidas = ArcaCaeaAnitaIvaVentasSupport::tiposQueNoVanAlIvaVentas();

        $query = Venta::query()
            ->select('venta.*')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->leftJoin('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
            ->where('puntoventa.empresa_id', (int) $registro->empresa_id)
            ->where('puntoventa.modofacturacion', 'A')
            ->whereIn('puntoventa.webservice', ArcaPuntoventaWebserviceSupport::valoresWhereInSoapCaea())
            ->whereBetween('venta.fecha', [
                $fechas['desde']->toDateString(),
                $fechas['hasta']->toDateString(),
            ])
            ->whereNotNull('venta.cae')
            ->where('venta.cae', '!=', '')
            ->when($nroCaea !== '', fn (Builder $q) => $q->where('venta.cae', $nroCaea))
            ->when($excluidas !== [], fn (Builder $q) => $q->where(function (Builder $w) use ($excluidas): void {
                $w->whereNull('tipotransaccion.abreviatura')
                    ->orWhereNotIn('tipotransaccion.abreviatura', $excluidas);
            }));

        $this->aplicarFiltroSucursalesInforme($query, (int) $registro->empresa_id);

        return $query;
    }

    /**
     * Último número informado en ERP por PV+tipo AFIP de ESTA quincena.
     * No agrupa por venta.codigo (único por comprobante): eso generaba una fila por factura.
     *
     * @param  list<array<string, mixed>>|null  $ultimosArca
     * @return list<array<string, mixed>>
     */
    private function ultimosPorTipoPv(ArcaCaea $registro, ?array $ultimosArca = null): array
    {
        $fechas = CaeaQuincenaSupport::fechasQuincena((int) $registro->periodo, (int) $registro->orden);
        $nroCaea = trim((string) ($registro->nro_caea ?? ''));

        $arcaPorClave = [];
        foreach ($ultimosArca ?? [] as $ua) {
            $pto = (int) ($ua['pto_vta'] ?? 0);
            $tipo = (int) ($ua['tipo_afip'] ?? 0);
            if ($pto > 0 && $tipo > 0) {
                $arcaPorClave[$this->claveGrupoInforme($pto, $tipo)] = $ua;
            }
        }

        // Solo PV/tipos con comprobantes de esta quincena (no relleno con mapa empresa).
        $clavesPeriodo = [];
        foreach ($this->listarCombinacionesTipoPv($registro) as $combo) {
            $clave = $this->claveGrupoInforme((int) $combo['pto_vta'], (int) $combo['tipo_afip']);
            $clavesPeriodo[$clave] = $combo;
        }

        $rows = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
            ->where('puntoventa.empresa_id', (int) $registro->empresa_id)
            ->where('puntoventa.modofacturacion', 'A')
            ->whereBetween('venta.fecha', [
                $fechas['desde']->toDateString(),
                $fechas['hasta']->toDateString(),
            ])
            ->whereNotNull('venta.cae')
            ->when($nroCaea !== '', fn ($q) => $q->where('venta.cae', $nroCaea))
            ->whereIn('venta.caea_informado_estado', ['ok', 'observacion'])
            ->select([
                'puntoventa.codigo as pto_vta',
                'venta.codigo as codigo_venta',
                'tipotransaccion.codigo as tipo_codigo',
                'venta.numerocomprobante',
                'venta.caea_informado_at',
            ])
            ->orderBy('puntoventa.codigo')
            ->get();

        /** @var array<string, array<string, mixed>> $agg */
        $agg = [];
        foreach ($rows as $row) {
            $ptoVta = (int) $row->pto_vta;
            $tipoAfip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                (string) $row->tipo_codigo,
                (string) $row->codigo_venta,
            );
            if ($tipoAfip <= 0) {
                continue;
            }
            $clave = $this->claveGrupoInforme($ptoVta, $tipoAfip);
            $numero = (int) $row->numerocomprobante;
            if (! isset($agg[$clave]) || $numero > (int) $agg[$clave]['ultimo_numero']) {
                $arca = $arcaPorClave[$clave] ?? null;
                $agg[$clave] = [
                    'pto_vta' => $ptoVta,
                    'tipo_afip' => $tipoAfip,
                    'letra' => LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $row->codigo_venta),
                    'ultimo_numero' => $numero,
                    'ultimo_informado_at' => $row->caea_informado_at,
                    'ultimo_arca' => $arca !== null ? (int) ($arca['ultimo_arca'] ?? 0) : null,
                    'ultimo_arca_consultado_at' => $arca['consultado_at'] ?? null,
                ];
            } elseif (
                isset($agg[$clave])
                && $row->caea_informado_at !== null
                && (
                    $agg[$clave]['ultimo_informado_at'] === null
                    || (string) $row->caea_informado_at > (string) $agg[$clave]['ultimo_informado_at']
                )
            ) {
                $agg[$clave]['ultimo_informado_at'] = $row->caea_informado_at;
            }
        }

        foreach ($clavesPeriodo as $clave => $combo) {
            if (isset($agg[$clave])) {
                continue;
            }
            $arca = $arcaPorClave[$clave] ?? null;
            $agg[$clave] = [
                'pto_vta' => (int) $combo['pto_vta'],
                'tipo_afip' => (int) $combo['tipo_afip'],
                'letra' => (string) ($combo['letra'] ?? ''),
                'ultimo_numero' => null,
                'ultimo_informado_at' => null,
                'ultimo_arca' => $arca !== null ? (int) ($arca['ultimo_arca'] ?? 0) : null,
                'ultimo_arca_consultado_at' => $arca['consultado_at'] ?? null,
            ];
        }

        $out = array_values($agg);
        usort($out, fn (array $a, array $b): int => [$a['pto_vta'], $a['tipo_afip']] <=> [$b['pto_vta'], $b['tipo_afip']]);

        return $out;
    }

    /**
     * Une PV+tipo de varias quincenas (una sola pasada de SOAP por combinación).
     *
     * @param  iterable<int, ArcaCaea>  $registros
     * @return list<array<string, mixed>>
     */
    private function listarCombinacionesTipoPvDeRegistros(iterable $registros): array
    {
        $out = [];
        $seen = [];
        foreach ($registros as $registro) {
            if (! $registro instanceof ArcaCaea) {
                continue;
            }
            foreach ($this->listarCombinacionesTipoPv($registro) as $combo) {
                $clave = $this->claveGrupoInforme((int) $combo['pto_vta'], (int) $combo['tipo_afip']);
                if (isset($seen[$clave])) {
                    continue;
                }
                $seen[$clave] = true;
                $out[] = $combo;
            }
        }

        return $out;
    }

    /**
     * Combinaciones PV+tipo AFIP con comprobantes CAEA pendientes/error de la empresa.
     * Fallback cuando no hay quincenas concretas (el último ARCA sigue siendo global).
     *
     * @return list<array<string, mixed>>
     */
    private function listarCombinacionesTipoPvEmpresa(int $empresaId): array
    {
        $query = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->where('puntoventa.modofacturacion', 'A')
            ->whereIn('puntoventa.webservice', ArcaPuntoventaWebserviceSupport::valoresWhereInSoapCaea())
            ->whereNotNull('venta.cae')
            ->where('venta.cae', '!=', '')
            ->where(function ($q): void {
                $q->whereNull('venta.caea_informado_estado')
                    ->orWhere('venta.caea_informado_estado', 'error');
            })
            ->select([
                'puntoventa.id as puntoventa_id',
                'puntoventa.codigo as pto_vta',
                'puntoventa.webservice',
                'venta.codigo as codigo_venta',
                'tipotransaccion.codigo as tipo_codigo',
            ])
            ->distinct()
            ->orderBy('puntoventa.codigo');
        $this->aplicarFiltroSucursalesInforme($query, $empresaId);

        return $this->mapearCombinacionesTipoPv($query->get());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarCombinacionesTipoPv(ArcaCaea $registro): array
    {
        $fechas = CaeaQuincenaSupport::fechasQuincena((int) $registro->periodo, (int) $registro->orden);
        $nroCaea = trim((string) ($registro->nro_caea ?? ''));

        $query = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
            ->where('puntoventa.empresa_id', (int) $registro->empresa_id)
            ->where('puntoventa.modofacturacion', 'A')
            ->whereIn('puntoventa.webservice', ArcaPuntoventaWebserviceSupport::valoresWhereInSoapCaea())
            ->whereBetween('venta.fecha', [
                $fechas['desde']->toDateString(),
                $fechas['hasta']->toDateString(),
            ])
            ->whereNotNull('venta.cae')
            ->where('venta.cae', '!=', '')
            ->when($nroCaea !== '', fn ($q) => $q->where('venta.cae', $nroCaea))
            ->select([
                'puntoventa.id as puntoventa_id',
                'puntoventa.codigo as pto_vta',
                'puntoventa.webservice',
                'venta.codigo as codigo_venta',
                'tipotransaccion.codigo as tipo_codigo',
            ])
            ->distinct()
            ->orderBy('puntoventa.codigo');
        $this->aplicarFiltroSucursalesInforme($query, (int) $registro->empresa_id);

        return $this->fusionarCombinacionesAnita($registro, $this->mapearCombinacionesTipoPv($query->get()));
    }

    /**
     * @param  Collection<int, object>|iterable<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapearCombinacionesTipoPv(iterable $rows): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $ptoVta = (int) $row->pto_vta;
            $tipoAfip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                (string) $row->tipo_codigo,
                (string) $row->codigo_venta,
            );
            if ($tipoAfip <= 0) {
                continue;
            }

            $clave = $this->claveGrupoInforme($ptoVta, $tipoAfip);
            if (isset($seen[$clave])) {
                continue;
            }
            $seen[$clave] = true;

            $out[] = [
                'puntoventa_id' => (int) $row->puntoventa_id,
                'pto_vta' => $ptoVta,
                'tipo_afip' => $tipoAfip,
                'webservice' => (string) $row->webservice,
                'letra' => LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $row->codigo_venta),
            ];
        }

        return $out;
    }

    /**
     * Sin SOAP: el último autorizado es global por PV+tipo. Si otra quincena de la
     * misma empresa ya guardó un valor mayor (p. ej. junio Q2 = 14212 y julio Q1
     * quedó con 14068 de mitad de proceso), usar el máximo conocido para no bloquear
     * la presentación al solo recargar el index.
     *
     * @param  list<array<string, mixed>>  $ultimosLocales
     * @return list<array<string, mixed>>
     */
    private function ultimosArcaLocalEnriquecidos(int $empresaId, int $registroIdActual, array $ultimosLocales): array
    {
        /** @var array<string, array<string, mixed>> $porClave */
        $porClave = [];
        foreach ($ultimosLocales as $ua) {
            if (! is_array($ua)) {
                continue;
            }
            $pto = (int) ($ua['pto_vta'] ?? 0);
            $tipo = (int) ($ua['tipo_afip'] ?? 0);
            if ($pto <= 0 || $tipo <= 0) {
                continue;
            }
            $porClave[$this->claveGrupoInforme($pto, $tipo)] = $ua;
        }

        $hermanos = ArcaCaea::query()
            ->where('empresa_id', $empresaId)
            ->where('id', '!=', $registroIdActual)
            ->whereNotNull('informe_resumen')
            ->get(['id', 'informe_resumen']);

        foreach ($hermanos as $hermano) {
            $resumen = is_array($hermano->informe_resumen) ? $hermano->informe_resumen : null;
            if ($resumen === null) {
                continue;
            }
            foreach ($resumen['ultimos_arca'] ?? [] as $ua) {
                if (! is_array($ua)) {
                    continue;
                }
                $pto = (int) ($ua['pto_vta'] ?? 0);
                $tipo = (int) ($ua['tipo_afip'] ?? 0);
                if ($pto <= 0 || $tipo <= 0) {
                    continue;
                }
                $clave = $this->claveGrupoInforme($pto, $tipo);
                $candidato = (int) ($ua['ultimo_arca'] ?? 0);
                $actual = (int) ($porClave[$clave]['ultimo_arca'] ?? -1);
                if ($candidato > $actual) {
                    $porClave[$clave] = $ua;
                }
            }
        }

        return array_values($porClave);
    }

    /**
     * @param  list<array<string, mixed>>  $out
     * @return list<array<string, mixed>>
     */
    private function fusionarCombinacionesAnita(ArcaCaea $registro, array $out): array
    {
        $seen = [];
        foreach ($out as $combo) {
            $seen[$this->claveGrupoInforme((int) $combo['pto_vta'], (int) $combo['tipo_afip'])] = true;
        }

        $pvs = $this->puntosVentaCaeaPorCodigo((int) $registro->empresa_id);
        foreach ($this->listarAnitaIvaVentasPeriodo($registro) as $item) {
            $pto = (int) $item['sucursal'];
            $tipoAfip = (int) $item['tipo_afip'];
            $clave = $this->claveGrupoInforme($pto, $tipoAfip);
            if (isset($seen[$clave])) {
                continue;
            }
            $pv = $pvs[$pto] ?? null;
            if ($pv === null) {
                continue;
            }
            $seen[$clave] = true;
            $out[] = [
                'puntoventa_id' => (int) $pv->id,
                'pto_vta' => $pto,
                'tipo_afip' => $tipoAfip,
                'webservice' => (string) $pv->webservice,
                'letra' => (string) $item['letra'],
            ];
        }

        return $out;
    }

    /**
     * @return array<int, Puntoventa>
     */
    private function puntosVentaCaeaPorCodigo(int $empresaId): array
    {
        $rows = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('modofacturacion', 'A')
            ->whereIn('webservice', ArcaPuntoventaWebserviceSupport::valoresWhereInSoapCaea())
            ->get();

        $out = [];
        foreach ($rows as $pv) {
            $out[(int) $pv->codigo] = $pv;
        }

        return ArcaCaeaSucursalesInformeSupport::filtrarPorCodigo($out, $empresaId);
    }

    /**
     * El Bierzo: solo sucursal 5. Villafranca no informa CAEA.
     */
    private function aplicarFiltroSucursalesInforme(Builder|\Illuminate\Database\Query\Builder $query, int $empresaId): void
    {
        $ids = [];
        foreach ($this->puntosVentaCaeaPorCodigo($empresaId) as $pv) {
            $ids[] = (int) $pv->id;
        }
        if ($ids === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn('puntoventa.id', $ids);
    }

    /**
     * @return list<int>
     */
    private function sucursalesCaeaEmpresa(int $empresaId): array
    {
        return array_values(array_filter(
            array_map(static fn (Puntoventa $pv): int => (int) $pv->codigo, array_values($this->puntosVentaCaeaPorCodigo($empresaId))),
            static fn (int $codigo): bool => $codigo > 0,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarAnitaIvaVentasPeriodo(ArcaCaea $registro): array
    {
        $nroCaea = trim((string) ($registro->nro_caea ?? ''));
        if ($nroCaea === '') {
            return [];
        }

        return ArcaCaeaAnitaVencaeConsultaSupport::listarPorCaeaIvaVentas(
            $nroCaea,
            $this->sucursalesCaeaEmpresa((int) $registro->empresa_id),
        );
    }

    /**
     * @return array<string, true>
     */
    private function clavesComprobanteErpPeriodo(ArcaCaea $registro): array
    {
        $ventas = $this->queryVentasPeriodo($registro)
            ->with(['puntoventas', 'tipotransacciones'])
            ->get();

        $out = [];
        foreach ($ventas as $venta) {
            $pto = (int) ($venta->puntoventas->codigo ?? 0);
            $tipo = $this->cbteTipoDesdeVenta($venta);
            $numero = (int) $venta->numerocomprobante;
            if ($pto > 0 && $tipo > 0 && $numero > 0) {
                $out[$pto.'|'.$tipo.'|'.$numero] = true;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $ultimosArca
     * @return array{total: int, informados_ok: int, pendientes: int}
     */
    private function resumenAnitaIvaVentasPeriodo(ArcaCaea $registro, array $ultimosArca): array
    {
        $ultimosMap = [];
        foreach ($ultimosArca as $ua) {
            $pto = (int) ($ua['pto_vta'] ?? 0);
            $tipo = (int) ($ua['tipo_afip'] ?? 0);
            if ($pto > 0 && $tipo > 0) {
                $ultimosMap[$this->claveGrupoInforme($pto, $tipo)] = (int) ($ua['ultimo_arca'] ?? 0);
            }
        }

        $erp = $this->clavesComprobanteErpPeriodo($registro);
        $total = 0;
        $ok = 0;
        $pendientes = 0;
        foreach ($this->listarAnitaIvaVentasPeriodo($registro) as $item) {
            $claveNumero = $item['sucursal'].'|'.$item['tipo_afip'].'|'.$item['numero'];
            if (isset($erp[$claveNumero])) {
                continue;
            }
            $total++;
            $ultimo = (int) ($ultimosMap[$this->claveGrupoInforme((int) $item['sucursal'], (int) $item['tipo_afip'])] ?? 0);
            if ((int) $item['numero'] <= $ultimo) {
                $ok++;
            } else {
                $pendientes++;
            }
        }

        return ['total' => $total, 'informados_ok' => $ok, 'pendientes' => $pendientes];
    }

    /**
     * @param  array<string, int>  $ultimosMap
     * @param  array<string, array<string, mixed>>  $colaPorGrupo
     */
    private function analizarColaInformeAnita(
        ArcaCaea $registro,
        array $ultimosMap,
        array &$colaPorGrupo,
        int &$informables,
        int &$bloqueados,
    ): void {
        $erp = $this->clavesComprobanteErpPeriodo($registro);
        foreach ($this->listarAnitaIvaVentasPeriodo($registro) as $item) {
            $pto = (int) $item['sucursal'];
            $tipo = (int) $item['tipo_afip'];
            $numero = (int) $item['numero'];
            $claveNumero = $pto.'|'.$tipo.'|'.$numero;
            if (isset($erp[$claveNumero])) {
                continue;
            }

            $clave = $this->claveGrupoInforme($pto, $tipo);
            if (! array_key_exists($clave, $ultimosMap)) {
                continue;
            }

            $ultimoArca = (int) $ultimosMap[$clave];
            if ($numero <= $ultimoArca) {
                continue;
            }

            $proximoEsperado = $ultimoArca + 1;
            if ($numero === $proximoEsperado) {
                if (! isset($colaPorGrupo[$clave]) || empty($colaPorGrupo[$clave]['informable_ahora'])) {
                    $informables++;
                    $colaPorGrupo[$clave] = [
                        'pto_vta' => $pto,
                        'tipo_afip' => $tipo,
                        'proximo_numero' => $proximoEsperado,
                        'primer_pendiente_esta_quincena' => $numero,
                        'en_esta_quincena' => true,
                        'informable_ahora' => true,
                        'fuente' => 'anita',
                    ];
                }
            } elseif (! isset($colaPorGrupo[$clave])) {
                $bloqueados++;
                $colaPorGrupo[$clave] = [
                    'pto_vta' => $pto,
                    'tipo_afip' => $tipo,
                    'proximo_numero' => $proximoEsperado,
                    'primer_pendiente_esta_quincena' => $numero,
                    'en_esta_quincena' => true,
                    'informable_ahora' => false,
                    'fuente' => 'anita',
                ];
            }
        }
    }

    private function hayPendienteMayorEnPeriodo(ArcaCaea $registro, int $ptoVta, int $tipoAfip, int $proximo): bool
    {
        $erpPendiente = $this->primerNumeroConCaeaPendiente(
            (int) $registro->empresa_id,
            $ptoVta,
            $tipoAfip,
            $proximo + 1,
        );
        if ($erpPendiente > $proximo) {
            return true;
        }

        foreach ($this->listarAnitaIvaVentasPeriodo($registro) as $item) {
            if ((int) $item['sucursal'] === $ptoVta
                && (int) $item['tipo_afip'] === $tipoAfip
                && (int) $item['numero'] > $proximo
            ) {
                return true;
            }
        }

        return false;
    }

    private function caeaAnitaEsDeQuincena(int $empresaId, string $nroCaea, int $periodo, int $orden): bool
    {
        $nroCaea = trim($nroCaea);
        if ($nroCaea === '') {
            return false;
        }

        return ArcaCaea::query()
            ->where('empresa_id', $empresaId)
            ->where('nro_caea', $nroCaea)
            ->where('periodo', $periodo)
            ->where('orden', $orden)
            ->exists();
    }

    /**
     * @param  array<string, int>  $ultimosMemoria
     * @return list<array<string, mixed>>
     */
    private function armarLoteInforme(ArcaCaea $registro, bool $soloErrores, array $ultimosMemoria, int $limite): array
    {
        $query = $this->queryVentasPeriodo($registro)
            ->with(['puntoventas', 'tipotransacciones', 'venta_impuestos', 'venta_emisiones', 'clientes.tipodocumentos', 'monedas'])
            ->orderBy('venta.puntoventa_id')
            ->orderBy('venta.tipotransaccion_id')
            ->orderBy('venta.numerocomprobante');

        if ($soloErrores) {
            $query->where('venta.caea_informado_estado', 'error');
        } else {
            $query->where(function (Builder $q): void {
                $q->whereNull('venta.caea_informado_estado')
                    ->orWhere('venta.caea_informado_estado', 'error');
            });
        }

        /** @var array<string, array<int, Venta>> $erpMap */
        $erpMap = [];
        /** @var Collection<int, Venta> $ventas */
        $ventas = $query->limit(max(500, $limite * 10))->get();
        foreach ($ventas as $venta) {
            $pto = (int) ($venta->puntoventas->codigo ?? 0);
            $tipo = $this->cbteTipoDesdeVenta($venta);
            $numero = (int) $venta->numerocomprobante;
            if ($pto < 1 || $tipo < 1 || $numero < 1) {
                continue;
            }
            $erpMap[$this->claveGrupoInforme($pto, $tipo)][$numero] = $venta;
        }

        /** @var array<string, array<int, array<string, mixed>>> $anitaMap */
        $anitaMap = [];
        if (! $soloErrores) {
            $erpTodas = $this->clavesComprobanteErpPeriodo($registro);
            foreach ($this->listarAnitaIvaVentasPeriodo($registro) as $item) {
                $pto = (int) $item['sucursal'];
                $tipo = (int) $item['tipo_afip'];
                $numero = (int) $item['numero'];
                $claveNumero = $pto.'|'.$tipo.'|'.$numero;
                if (isset($erpTodas[$claveNumero])) {
                    continue;
                }
                $anitaMap[$this->claveGrupoInforme($pto, $tipo)][$numero] = $item;
            }
        }

        $grupos = array_unique(array_merge(
            array_keys($erpMap),
            array_keys($anitaMap),
            array_keys($ultimosMemoria),
        ));
        sort($grupos);

        $lote = [];
        foreach ($grupos as $clave) {
            if (count($lote) >= $limite) {
                break;
            }
            $ultimo = (int) ($ultimosMemoria[$clave] ?? 0);
            $n = $ultimo + 1;
            while (count($lote) < $limite) {
                if (isset($erpMap[$clave][$n])) {
                    $venta = $erpMap[$clave][$n];
                    $lote[] = [
                        'fuente' => 'erp',
                        'venta' => $venta,
                        'pto_vta' => (int) ($venta->puntoventas->codigo ?? 0),
                        'tipo_afip' => $this->cbteTipoDesdeVenta($venta),
                        'numero' => $n,
                    ];
                    $n++;

                    continue;
                }
                if (isset($anitaMap[$clave][$n])) {
                    $item = $anitaMap[$clave][$n];
                    $lote[] = [
                        'fuente' => 'anita',
                        'venta' => null,
                        'pto_vta' => (int) $item['sucursal'],
                        'tipo_afip' => (int) $item['tipo_afip'],
                        'numero' => $n,
                        'tipo_anita' => (string) $item['tipo_anita'],
                        'letra' => (string) $item['letra'],
                    ];
                    $n++;

                    continue;
                }

                break;
            }
        }

        return $lote;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, int>  $ultimosMemoria
     * @return array<string, mixed>
     */
    private function informarItemLote(
        ArcaCaea $registro,
        object $empresa,
        array $caeaVigente,
        array $item,
        array &$ultimosMemoria,
    ): array {
        $fuente = (string) ($item['fuente'] ?? 'erp');
        if ($fuente === 'anita') {
            return $this->informarItemAnita($registro, $empresa, $caeaVigente, $item, $ultimosMemoria);
        }

        /** @var Venta $venta */
        $venta = $item['venta'];
        $puntoventa = $venta->puntoventas;
        if ($puntoventa === null) {
            $msg = 'Sin punto de venta';
            $this->marcarVentaInforme($venta, 'error', null, $msg);

            return [
                'errores' => 1,
                'detener' => true,
                'ultimo_error' => $msg,
                'error_fila' => $this->filaErrorDetalle($venta, null, $msg),
            ];
        }

        try {
            $datos = ArcaCaeaInformeDatosDesdeVentaSupport::construir($venta);
        } catch (\Throwable $e) {
            $this->marcarVentaInforme($venta, 'error', null, $e->getMessage());

            return [
                'errores' => 1,
                'detener' => true,
                'ultimo_error' => $e->getMessage(),
                'error_fila' => $this->filaErrorDetalle($venta, $puntoventa->codigo ?? null, $e->getMessage()),
            ];
        }

        $cbteTipo = (int) ($datos['cbte_tipo'] ?? 0);
        $ptoVta = (int) ($puntoventa->codigo ?? 0);
        $numero = (int) $venta->numerocomprobante;
        $claveGrupo = $this->claveGrupoInforme($ptoVta, $cbteTipo);
        $ultimoArca = (int) ($ultimosMemoria[$claveGrupo] ?? 0);

        if ($numero <= $ultimoArca) {
            $this->marcarVentaInforme(
                $venta,
                'ok',
                null,
                sprintf('Ya informado en ARCA (último autorizado: %d)', $ultimoArca),
            );

            return ['omitidos_arca' => 1];
        }

        if ($numero > $ultimoArca + 1) {
            $proximo = $ultimoArca + 1;
            $msgHueco = $this->mensajeBloqueoProximoEsperado(
                (int) $registro->empresa_id,
                $ptoVta,
                $cbteTipo,
                $proximo,
                $numero,
            );

            return [
                'errores' => 1,
                'omitidos_hueco' => 1,
                'detener' => true,
                'ultimo_error' => $msgHueco,
                'error_fila' => $this->filaErrorDetalle($venta, $ptoVta, $msgHueco),
            ];
        }

        unset($datos['cbte_tipo'], $datos['letra']);

        $resultado = $this->facturaelectronicaService->informarComprobanteCaea(
            $empresa->nroinscripcion,
            $cbteTipo,
            $puntoventa,
            $datos,
            $caeaVigente,
        );

        if (! ($resultado['ok'] ?? false)) {
            $msg = (string) ($resultado['error'] ?? 'Error desconocido');
            $codigo = $this->extraerCodigoError($msg);
            $this->marcarVentaInforme($venta, 'error', $codigo, $msg);
            Log::warning('arca:caea-informe — error al informar, se detiene el lote', [
                'arca_caea_id' => $registro->id,
                'venta_id' => $venta->id,
                'pto_vta' => $ptoVta,
                'numero' => $numero,
                'fuente' => 'erp',
                'msg' => $msg,
            ]);

            return [
                'errores' => 1,
                'detener' => true,
                'ultimo_error' => $msg,
                'error_fila' => $this->filaErrorDetalle($venta, $puntoventa->codigo ?? null, $msg, $codigo),
            ];
        }

        $resArca = (string) ($resultado['resultado'] ?? 'A');
        $obs = trim((string) ($resultado['observaciones'] ?? ''));
        $estado = $resArca === 'P' || $obs !== '' ? 'observacion' : 'ok';
        $this->marcarVentaInforme($venta, $estado, null, $obs !== '' ? $obs : null);
        $ultimosMemoria[$claveGrupo] = $numero;

        return [
            'informados' => 1,
            'con_obs' => $estado === 'observacion' ? 1 : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, int>  $ultimosMemoria
     * @return array<string, mixed>
     */
    private function informarItemAnita(
        ArcaCaea $registro,
        object $empresa,
        array $caeaVigente,
        array $item,
        array &$ultimosMemoria,
    ): array {
        $ptoVta = (int) ($item['pto_vta'] ?? 0);
        $tipoAfip = (int) ($item['tipo_afip'] ?? 0);
        $numero = (int) ($item['numero'] ?? 0);
        $tipoAnita = (string) ($item['tipo_anita'] ?? '');
        $letra = (string) ($item['letra'] ?? '');
        $claveGrupo = $this->claveGrupoInforme($ptoVta, $tipoAfip);
        $ultimoArca = (int) ($ultimosMemoria[$claveGrupo] ?? 0);

        if ($numero <= $ultimoArca) {
            return ['omitidos_arca' => 1];
        }

        if ($numero > $ultimoArca + 1) {
            $msgHueco = $this->mensajeBloqueoProximoEsperado(
                (int) $registro->empresa_id,
                $ptoVta,
                $tipoAfip,
                $ultimoArca + 1,
                $numero,
            );

            return [
                'errores' => 1,
                'omitidos_hueco' => 1,
                'detener' => true,
                'ultimo_error' => $msgHueco,
                'error_fila' => $this->filaErrorDetalleLibre($ptoVta, $numero, $msgHueco),
            ];
        }

        if (! ArcaCaeaAnitaIvaVentasSupport::vaAlSubdiarioIvaVentas($tipoAnita)) {
            $msg = sprintf(
                'PV %05d %s #%d tipo Anita %s no va al subdiario IVA-ventas; se omite del CAEA.',
                $ptoVta,
                $this->etiquetaTipoAfipCorta($tipoAfip),
                $numero,
                $tipoAnita,
            );

            return [
                'errores' => 1,
                'detener' => true,
                'ultimo_error' => $msg,
                'error_fila' => $this->filaErrorDetalleLibre($ptoVta, $numero, $msg),
            ];
        }

        $puntoventa = $this->puntosVentaCaeaPorCodigo((int) $registro->empresa_id)[$ptoVta] ?? null;
        if ($puntoventa === null) {
            $msg = sprintf('Punto de venta %d no es CAEA de la empresa.', $ptoVta);

            return [
                'errores' => 1,
                'detener' => true,
                'ultimo_error' => $msg,
                'error_fila' => $this->filaErrorDetalleLibre($ptoVta, $numero, $msg),
            ];
        }

        try {
            $armado = ArcaCaeaInformeDatosDesdeAnitaSupport::construir(
                $tipoAnita,
                $letra,
                $ptoVta,
                $numero,
                (int) $registro->empresa_id,
                trim((string) $registro->nro_caea),
            );
            $datos = $armado['datos'];
            unset($datos['cbte_tipo'], $datos['letra'], $datos['caea']);
        } catch (\Throwable $e) {
            return [
                'errores' => 1,
                'detener' => true,
                'ultimo_error' => $e->getMessage(),
                'error_fila' => $this->filaErrorDetalleLibre($ptoVta, $numero, $e->getMessage()),
            ];
        }

        $pvSoap = ArcaPuntoventaWebserviceSupport::puntoventaParaSoap($puntoventa);
        $resultado = $this->facturaelectronicaService->informarComprobanteCaea(
            $empresa->nroinscripcion,
            $tipoAfip,
            $pvSoap,
            $datos,
            $caeaVigente,
        );

        if (! ($resultado['ok'] ?? false)) {
            $msg = (string) ($resultado['error'] ?? 'Error desconocido');
            $codigo = $this->extraerCodigoError($msg);
            Log::warning('arca:caea-informe — error al informar Anita, se detiene el lote', [
                'arca_caea_id' => $registro->id,
                'pto_vta' => $ptoVta,
                'numero' => $numero,
                'tipo_anita' => $tipoAnita,
                'fuente' => 'anita',
                'msg' => $msg,
            ]);

            return [
                'errores' => 1,
                'detener' => true,
                'ultimo_error' => $msg,
                'error_fila' => $this->filaErrorDetalleLibre($ptoVta, $numero, $msg, $codigo),
            ];
        }

        $resArca = (string) ($resultado['resultado'] ?? 'A');
        $obs = trim((string) ($resultado['observaciones'] ?? ''));
        $estado = $resArca === 'P' || $obs !== '' ? 'observacion' : 'ok';
        $ultimosMemoria[$claveGrupo] = $numero;

        return [
            'informados' => 1,
            'con_obs' => $estado === 'observacion' ? 1 : 0,
        ];
    }

    /**
     * @return array{venta_id:int, pto_vta:?int, numero:int, codigo:?string, mensaje:string}
     */
    private function filaErrorDetalleLibre(int $ptoVta, int $numero, string $mensaje, ?string $codigo = null): array
    {
        return [
            'venta_id' => 0,
            'pto_vta' => $ptoVta,
            'numero' => $numero,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
        ];
    }

    private function claveGrupoInforme(int $ptoVta, int $cbteTipo): string
    {
        return $ptoVta.'|'.$cbteTipo;
    }

    private function cbteTipoDesdeVenta(Venta $venta): int
    {
        $tipo = $venta->tipotransacciones;
        if ($tipo === null) {
            return 0;
        }

        return TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
            (string) $tipo->codigo,
            (string) $venta->codigo,
        );
    }

    /**
     * @param  array<string, mixed>  $resumen
     */
    private function resolverEstadoInforme(array $resumen): string
    {
        $total = (int) ($resumen['total'] ?? 0);
        $pendientes = (int) ($resumen['pendientes'] ?? 0);
        $errores = (int) ($resumen['errores'] ?? 0);
        $obs = (int) ($resumen['informados_obs'] ?? 0);
        $ok = (int) ($resumen['informados_ok'] ?? 0);

        // Sin comprobantes CAEA en la quincena: no hay nada pendiente de informar.
        if ($total === 0) {
            return ArcaCaea::INFORME_ESTADO_OK;
        }

        if ($pendientes > 0 || $errores > 0) {
            if ($ok > 0 || $obs > 0) {
                return ArcaCaea::INFORME_ESTADO_PARCIAL;
            }
            if ($errores > 0) {
                return ArcaCaea::INFORME_ESTADO_ERROR;
            }

            return ArcaCaea::INFORME_ESTADO_PENDIENTE;
        }

        if ($obs > 0) {
            return ArcaCaea::INFORME_ESTADO_OBSERVACION;
        }

        return ArcaCaea::INFORME_ESTADO_OK;
    }

    private function marcarVentaInforme(Venta $venta, string $estado, ?string $codigo, ?string $mensaje): void
    {
        $venta->caea_informado_estado = $estado;
        $venta->caea_informado_at = now();
        $venta->caea_informado_codigo_error = $codigo;
        $venta->caea_informado_mensaje = $mensaje;
        $venta->save();
    }

    private function marcarVentaPendienteInforme(Venta $venta, ?string $mensaje = null): void
    {
        $venta->caea_informado_estado = null;
        $venta->caea_informado_at = null;
        $venta->caea_informado_codigo_error = null;
        $venta->caea_informado_mensaje = $mensaje;
        $venta->save();
    }
}
