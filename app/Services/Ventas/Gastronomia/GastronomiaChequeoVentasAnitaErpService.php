<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportBridgeSupport;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportEstacionamientoSupport;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportResvtaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaComprobantePkSupport;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport;
use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;
use App\Support\Ventas\KandikoAnitaVentaTipoSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Concilia cabecera venta ERP ↔ Informix (Anita) factura por factura para IVA / totales.
 */
final class GastronomiaChequeoVentasAnitaErpService
{
    private const CAMPOS_MONETARIOS = ['total', 'gravado', 'iva', 'exento'];

    /**
     * @return array{
     *   puntoventa: string,
     *   sucursal: int,
     *   fecha_jornada: string,
     *   resumen: array<string, mixed>,
     *   filas: list<array<string, mixed>>
     * }
     */
    public function chequear(
        int $puntoventaId,
        string $fechaJornada,
        float $tolerancia = 0.02,
        bool $soloDiferencias = true,
        int $limite = 0,
    ): array {
        $puntoventa = Puntoventa::query()->with('empresas')->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        if ($sucursal <= 0) {
            throw new \InvalidArgumentException('Código de punto de venta inválido: '.$puntoventa->codigo);
        }

        $fechaEntera = (int) str_replace('-', '', $fechaJornada);
        if ($fechaEntera <= 0) {
            throw new \InvalidArgumentException('Fecha de jornada inválida: '.$fechaJornada);
        }

        $anitaPorClave = $this->listarCabecerasAnitaPorJornada($sucursal, $fechaEntera, $puntoventa);
        $empresaCodigo = $puntoventa->empresas?->codigo ?? $puntoventa->empresa_id;
        $numerosAnitaJornada = array_values(array_unique(array_filter(array_map(
            static fn (object $cab): int => (int) ($cab->ven_nro ?? 0),
            $anitaPorClave,
        ), static fn (int $n): bool => $n > 0)));
        $empresaId = (int) $puntoventa->empresa_id;
        $numerosEstacionamiento = GastronomiaAnitaImportEstacionamientoSupport::numerosEstacionamientoEnSucursal(
            $sucursal,
            $empresaCodigo,
            $numerosAnitaJornada,
            $empresaId,
        );
        $numerosResvtaLegacy = GastronomiaAnitaImportResvtaSupport::numerosConResvtaEnSucursal(
            $sucursal,
            $empresaCodigo,
            $numerosAnitaJornada,
            $empresaId,
        );
        $numerosEstacionamientoErp = GastronomiaAnitaImportEstacionamientoSupport::numerosConEmisionErpEnJornada(
            $puntoventaId,
            $fechaJornada,
        );
        $numerosExcluidosConciliacion = $numerosResvtaLegacy + $numerosEstacionamientoErp;
        $ventasErp = $this->listarVentasErpPorJornada($puntoventaId, $fechaJornada);

        $filas = [];
        $clavesProcesadas = [];
        $conteo = [
            'ok' => 0,
            'diferencia' => 0,
            'solo_erp' => 0,
            'solo_anita' => 0,
            'excluido_estacionamiento' => 0,
            'excluido_resvta_legacy' => 0,
            'error' => 0,
        ];

        foreach ($ventasErp as $venta) {
            $clave = $this->claveComprobanteDesdeVenta($venta);
            if ($clave === null) {
                $conteo['error']++;
                $fila = $this->filaError($venta, 'Código de comprobante ERP no reconocido');
                if (! $soloDiferencias) {
                    $filas[] = $fila;
                }

                continue;
            }

            $clavesProcesadas[$clave] = true;
            foreach (GastronomiaAnitaComprobantePkSupport::clavesAliasConciliacionDesdeClave($clave) as $claveAlias) {
                $clavesProcesadas[$claveAlias] = true;
            }
            $anita = $anitaPorClave[$clave] ?? null;

            if ($anita === null) {
                $conteo['solo_erp']++;
                $fila = $this->filaBase($venta, $clave, null);
                $fila['estado'] = 'solo_erp';
                $fila['erp'] = $this->montosDesdeVentaErp($venta);
                $fila['diferencias'] = ['anita' => 'Sin cabecera en Informix'];
                $filas[] = $fila;

                continue;
            }

            $erpMontos = $this->montosDesdeVentaErp($venta);
            $anitaMontos = $this->montosDesdeCabeceraAnita($anita);
            $diferencias = $this->compararMontos($erpMontos, $anitaMontos, $tolerancia);

            $fila = $this->filaBase($venta, $clave, $anita);
            $fila['erp'] = $erpMontos;
            $fila['anita'] = $anitaMontos;
            $fila['diferencias'] = $diferencias;
            $fila['estado'] = $diferencias === [] ? 'ok' : 'diferencia';

            if ($fila['estado'] === 'ok') {
                $conteo['ok']++;
            } else {
                $conteo['diferencia']++;
            }

            if (! $soloDiferencias || $fila['estado'] !== 'ok') {
                $filas[] = $fila;
            }
        }

        foreach ($anitaPorClave as $clave => $anita) {
            if (isset($clavesProcesadas[$clave])) {
                continue;
            }

            $numeroAnita = (int) ($anita->ven_nro ?? 0);
            if ($numeroAnita > 0 && isset($numerosExcluidosConciliacion[$numeroAnita])) {
                if (isset($numerosResvtaLegacy[$numeroAnita])) {
                    $conteo['excluido_resvta_legacy']++;
                }
                if (isset($numerosEstacionamiento[$numeroAnita]) || isset($numerosEstacionamientoErp[$numeroAnita])) {
                    $conteo['excluido_estacionamiento']++;
                }

                continue;
            }

            $conteo['solo_anita']++;
            $filas[] = [
                'estado' => 'solo_anita',
                'clave' => $clave,
                'codigo_erp' => null,
                'venta_id' => null,
                'tipo' => (string) ($anita->ven_tipo ?? ''),
                'numero' => (int) ($anita->ven_nro ?? 0),
                'erp' => null,
                'anita' => $this->montosDesdeCabeceraAnita($anita),
                'diferencias' => ['erp' => 'Sin venta gastronomía en ERP'],
            ];
        }

        usort($filas, function (array $a, array $b): int {
            $estadoOrder = ['diferencia' => 0, 'solo_erp' => 1, 'solo_anita' => 2, 'ok' => 3, 'error' => 4];
            $ea = $estadoOrder[$a['estado'] ?? ''] ?? 9;
            $eb = $estadoOrder[$b['estado'] ?? ''] ?? 9;
            if ($ea !== $eb) {
                return $ea <=> $eb;
            }

            return ((int) ($a['numero'] ?? 0)) <=> ((int) ($b['numero'] ?? 0));
        });

        if ($limite > 0) {
            $filas = array_slice($filas, 0, $limite);
        }

        return [
            'puntoventa' => (string) $puntoventa->codigo,
            'sucursal' => $sucursal,
            'fecha_jornada' => $fechaJornada,
            'resumen' => $this->armarResumen($ventasErp, $anitaPorClave, $conteo, $tolerancia, $numerosExcluidosConciliacion),
            'filas' => $filas,
        ];
    }

    /**
     * @return array<string, object>
     */
    private function listarCabecerasAnitaPorJornada(int $sucursal, int $fechaEntera, ?Puntoventa $puntoventa = null): array
    {
        $where = " WHERE ven_sucursal = '".$sucursal."'"
            ." AND ven_fecha_vto = '".$fechaEntera."'"
            ." AND ven_letra = 'B' ";

        if ($puntoventa !== null) {
            $empresaCodigo = $puntoventa->empresas?->codigo ?? $puntoventa->empresa_id;
            $where .= GastronomiaAnitaImportEmpresaSupport::whereEmpresa('ven', $empresaCodigo);
        }

        $maxIntentos = max(1, (int) config('gastronomia.conciliacion_diaria_reporte.anita_reintentos_bridge', 3));
        $lista = [];
        $api = new ApiAnita;
        $empresaId = $puntoventa !== null ? (int) $puntoventa->empresa_id : 0;
        $ultimoError = null;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            $parsed = ApiAnita::parsearRespuestaLista($api->apiCall(
                GastronomiaAnitaImportBridgeSupport::mergePayloadVentaCabecera([
                'acc' => 'list',
                'tabla' => 'venta',
                'campos' => implode(',', [
                    'ven_tipo', 'ven_letra', 'ven_sucursal', 'ven_nro', 'ven_empresa',
                    'ven_fecha', 'ven_fecha_vto',
                    'ven_monto', 'ven_gravado', 'ven_exento', 'ven_impuesto1', 'ven_monto_desc',
                ]),
                'whereArmado' => $where,
                'orderBy' => 'ven_tipo, ven_nro',
            ], $empresaId)));

            if ($parsed['error_lectura'] !== null) {
                $ultimoError = $parsed['error_lectura'];
                Log::warning('gastronomia.chequeo_anita.lista_jornada_fallo', [
                    'sucursal' => $sucursal,
                    'fecha_jornada' => $fechaEntera,
                    'intento' => $intento,
                    'msg' => $ultimoError,
                ]);
                if ($intento < $maxIntentos) {
                    usleep(250_000 * $intento);
                    continue;
                }

                throw new \RuntimeException(
                    'No se pudo listar cabeceras Anita para la jornada: '.$ultimoError
                );
            }

            if ($parsed['filas'] === [] && $intento < $maxIntentos) {
                Log::warning('gastronomia.chequeo_anita.lista_jornada_vacia_reintento', [
                    'sucursal' => $sucursal,
                    'fecha_jornada' => $fechaEntera,
                    'intento' => $intento,
                ]);
                usleep(250_000 * $intento);
                continue;
            }

            $lista = $parsed['filas'];
            break;
        }
        $empresaCodigo = $puntoventa !== null
            ? Empresa::query()->whereKey($puntoventa->empresa_id)->value('codigo')
            : null;
        $codigoPv = $puntoventa !== null ? (string) $puntoventa->codigo : '';
        $modoFacturacion = $puntoventa !== null ? ($puntoventa->modofacturacion ?? null) : null;
        $esKandikoCaea = $puntoventa !== null
            && KandikoAnitaVentaTipoSupport::esPvCaeaKandiko($codigoPv, $empresaCodigo, $modoFacturacion);

        $map = [];
        foreach ($lista as $fila) {
            $tipo = trim((string) ($fila->ven_tipo ?? ''));
            $nro = (int) ($fila->ven_nro ?? 0);
            if ($tipo === '' || $nro <= 0) {
                continue;
            }

            if ($puntoventa !== null
                && ! KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv(
                    $tipo,
                    $codigoPv,
                    $empresaCodigo,
                    $modoFacturacion,
                )) {
                continue;
            }

            if ($esKandikoCaea) {
                foreach (GastronomiaAnitaComprobantePkSupport::clavesConciliacionDesdeCabeceraAnita($fila, true) as $clave) {
                    $existente = $map[$clave] ?? null;
                    $tipoExistente = $existente !== null
                        ? strtoupper(trim((string) ($existente->ven_tipo ?? '')))
                        : '';
                    if ($existente === null || ($tipo === KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE && $tipoExistente !== KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE)) {
                        $map[$clave] = $fila;
                    }
                }

                continue;
            }

            $clave = GastronomiaAnitaComprobantePkSupport::claveVentaDesdeCabeceraAnita($fila);
            if ($clave !== null) {
                $map[$clave] = $fila;
            }
        }

        return $map;
    }

    /**
     * Concilia por fecha calendario de emisión (`venta.fecha`), buscando cada comprobante en Anita por clave.
     *
     * @return array{
     *   puntoventa: string,
     *   sucursal: int,
     *   fecha_calendario: string,
     *   resumen: array<string, mixed>,
     *   filas: list<array<string, mixed>>
     * }
     */
    public function chequearPorFechaCalendario(
        int $puntoventaId,
        string $fechaCalendario,
        float $tolerancia = 0.02,
        bool $soloDiferencias = true,
    ): array {
        $puntoventa = Puntoventa::query()->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        if ($sucursal <= 0) {
            throw new \InvalidArgumentException('Código de punto de venta inválido: '.$puntoventa->codigo);
        }

        $ventasErp = $this->listarVentasErpPorFechaCalendario($puntoventaId, $fechaCalendario);
        $anitaPorClave = [];

        $filas = [];
        $conteo = [
            'ok' => 0,
            'diferencia' => 0,
            'solo_erp' => 0,
            'solo_anita' => 0,
            'error' => 0,
        ];

        foreach ($ventasErp as $venta) {
            $clave = $this->claveComprobanteDesdeVenta($venta);
            if ($clave === null) {
                $conteo['error']++;
                $fila = $this->filaError($venta, 'Código de comprobante ERP no reconocido');
                if (! $soloDiferencias) {
                    $filas[] = $fila;
                }

                continue;
            }

            $consulta = $this->consultarCabeceraAnitaDesdeClaveErp($clave, $puntoventa);
            if ($consulta['error_lectura'] !== null) {
                $conteo['error']++;
                $fila = $this->filaBase($venta, $clave, null);
                $fila['estado'] = 'error';
                $fila['diferencias'] = [
                    'anita' => 'Error de lectura Anita (no se asume faltante): '.$consulta['error_lectura'],
                ];
                $filas[] = $fila;

                continue;
            }

            $anita = $consulta['cabecera'];
            if ($anita !== null) {
                $anitaPorClave[$clave] = $anita;
            }

            if ($anita === null) {
                $conteo['solo_erp']++;
                $fila = $this->filaBase($venta, $clave, null);
                $fila['estado'] = 'solo_erp';
                $fila['diferencias'] = ['anita' => 'Sin cabecera en Informix (búsqueda por comprobante)'];
                $filas[] = $fila;

                continue;
            }

            $erpMontos = $this->montosDesdeVentaErp($venta);
            $anitaMontos = $this->montosDesdeCabeceraAnita($anita);
            $diferencias = $this->compararMontos($erpMontos, $anitaMontos, $tolerancia);

            $fila = $this->filaBase($venta, $clave, $anita);
            $fila['erp'] = $erpMontos;
            $fila['anita'] = $anitaMontos;
            $fila['diferencias'] = $diferencias;
            $fila['estado'] = $diferencias === [] ? 'ok' : 'diferencia';

            if ($fila['estado'] === 'ok') {
                $conteo['ok']++;
            } else {
                $conteo['diferencia']++;
            }

            if (! $soloDiferencias || $fila['estado'] !== 'ok') {
                $filas[] = $fila;
            }
        }

        usort($filas, function (array $a, array $b): int {
            $estadoOrder = ['diferencia' => 0, 'solo_erp' => 1, 'solo_anita' => 2, 'ok' => 3, 'error' => 4];
            $ea = $estadoOrder[$a['estado'] ?? ''] ?? 9;
            $eb = $estadoOrder[$b['estado'] ?? ''] ?? 9;
            if ($ea !== $eb) {
                return $ea <=> $eb;
            }

            return ((int) ($a['numero'] ?? 0)) <=> ((int) ($b['numero'] ?? 0));
        });

        $resumen = $this->armarResumen($ventasErp, $anitaPorClave, $conteo, $tolerancia);
        $resumen['filtro_anita'] = 'ven_sucursal + ven_tipo + ven_nro + ven_letra=B (por comprobante)';

        return [
            'puntoventa' => (string) $puntoventa->codigo,
            'sucursal' => $sucursal,
            'fecha_calendario' => $fechaCalendario,
            'resumen' => $resumen,
            'filas' => $filas,
        ];
    }

    /**
     * Auditoría global por fecha calendario (todos los PV gastronomía de la empresa).
     *
     * @return array{
     *   fecha_calendario: string,
     *   por_puntoventa: list<array<string, mixed>>,
     *   resumen_global: array<string, mixed>
     * }
     */
    public function auditoriaPorFechaCalendario(
        string $fechaCalendario,
        int $empresaId,
        float $tolerancia = 0.02,
        ?string $codigoPv = null,
    ): array {
        $combinaciones = $this->listarCombinacionesPvFechaCalendario($fechaCalendario, $empresaId, $codigoPv);
        $porPv = [];
        $conteoGlobal = [
            'ok' => 0,
            'diferencia' => 0,
            'solo_erp' => 0,
            'solo_anita' => 0,
            'error' => 0,
        ];
        $totalesErp = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];
        $totalesAnita = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];

        foreach ($combinaciones as $combo) {
            $resultado = $this->chequearPorFechaCalendario(
                (int) $combo['puntoventa_id'],
                $fechaCalendario,
                $tolerancia,
                true,
            );
            $porPv[] = $resultado;

            $res = $resultado['resumen'];
            foreach ($conteoGlobal as $k => $_) {
                $conteoGlobal[$k] += (int) ($res['conteo'][$k] ?? 0);
            }
            foreach (self::CAMPOS_MONETARIOS as $c) {
                $totalesErp[$c] += (float) ($res['totales_erp'][$c] ?? 0);
                $totalesAnita[$c] += (float) ($res['totales_anita_signo_erp'][$c] ?? 0);
            }
        }

        foreach ($totalesErp as $k => $v) {
            $totalesErp[$k] = round($v, 2);
        }
        foreach ($totalesAnita as $k => $v) {
            $totalesAnita[$k] = round($v, 2);
        }

        $delta = [];
        foreach (self::CAMPOS_MONETARIOS as $c) {
            $delta[$c] = round($totalesErp[$c] - $totalesAnita[$c], 2);
        }

        return [
            'fecha_calendario' => $fechaCalendario,
            'por_puntoventa' => $porPv,
            'resumen_global' => [
                'puntoventas' => count($porPv),
                'ventas_erp' => array_sum(array_map(
                    fn (array $r) => (int) ($r['resumen']['ventas_erp'] ?? 0),
                    $porPv,
                )),
                'tolerancia' => $tolerancia,
                'conteo' => $conteoGlobal,
                'totales_erp' => $totalesErp,
                'totales_anita_signo_erp' => $totalesAnita,
                'delta_totales' => $delta,
                'filtro_erp' => 'venta.fecha (calendario) + gastronomia_emision',
                'filtro_anita' => 'ven_sucursal + ven_tipo + ven_nro + ven_letra=B',
            ],
        ];
    }

    /**
     * Auditoría global por fecha de jornada (todos los PV gastronomía de la empresa).
     *
     * @return array{
     *   fecha_jornada: string,
     *   por_puntoventa: list<array<string, mixed>>,
     *   resumen_global: array<string, mixed>
     * }
     */
    public function auditoriaPorFechaJornada(
        string $fechaJornada,
        int $empresaId,
        float $tolerancia = 0.02,
        ?string $codigoPv = null,
    ): array {
        $combinaciones = $this->listarCombinacionesPvJornada(
            $fechaJornada,
            $fechaJornada,
            $empresaId,
            $codigoPv,
        );
        $porPv = [];
        $conteoGlobal = [
            'ok' => 0,
            'diferencia' => 0,
            'solo_erp' => 0,
            'solo_anita' => 0,
            'error' => 0,
        ];
        $totalesErp = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];
        $totalesAnita = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];

        foreach ($combinaciones as $combo) {
            if ((string) ($combo['fecha_jornada'] ?? '') !== $fechaJornada) {
                continue;
            }

            $resultado = $this->chequear(
                (int) $combo['puntoventa_id'],
                $fechaJornada,
                $tolerancia,
                true,
            );
            $porPv[] = $resultado;

            $res = $resultado['resumen'];
            foreach ($conteoGlobal as $k => $_) {
                if (! array_key_exists($k, $res['conteo'] ?? [])) {
                    continue;
                }
                $conteoGlobal[$k] += (int) ($res['conteo'][$k] ?? 0);
            }
            foreach (self::CAMPOS_MONETARIOS as $c) {
                $totalesErp[$c] += (float) ($res['totales_erp'][$c] ?? 0);
                $totalesAnita[$c] += (float) ($res['totales_anita_signo_erp'][$c] ?? 0);
            }
        }

        foreach ($totalesErp as $k => $v) {
            $totalesErp[$k] = round($v, 2);
        }
        foreach ($totalesAnita as $k => $v) {
            $totalesAnita[$k] = round($v, 2);
        }

        $delta = [];
        foreach (self::CAMPOS_MONETARIOS as $c) {
            $delta[$c] = round($totalesErp[$c] - $totalesAnita[$c], 2);
        }

        return [
            'fecha_jornada' => $fechaJornada,
            'por_puntoventa' => $porPv,
            'resumen_global' => [
                'puntoventas' => count($porPv),
                'ventas_erp' => array_sum(array_map(
                    fn (array $r) => (int) ($r['resumen']['ventas_erp'] ?? 0),
                    $porPv,
                )),
                'tolerancia' => $tolerancia,
                'conteo' => $conteoGlobal,
                'totales_erp' => $totalesErp,
                'totales_anita_signo_erp' => $totalesAnita,
                'delta_totales' => $delta,
                'filtro_erp' => 'venta.fechajornada + gastronomia_emision',
                'filtro_anita' => 'ven_sucursal + ven_fecha_vto (jornada) + ven_tipo + ven_nro + ven_letra=B',
            ],
        ];
    }

    /**
     * @return Collection<int, Venta>
     */
    public function listarVentasErpPorJornada(int $puntoventaId, string $fechaJornada): Collection
    {
        return Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->whereDate('fechajornada', $fechaJornada)
            ->whereHas('gastronomiaEmision')
            ->orderBy('numerocomprobante')
            ->get(['id', 'puntoventa_id', 'codigo', 'numerocomprobante', 'total', 'fechajornada', 'fecha', 'tipotransaccion_id']);
    }

    /**
     * Ventas gastronomía del ERP sin cabecera en Informix para un PV y fecha de jornada.
     *
     * @return Collection<int, Venta>
     */
    public function listarVentasErpSinCabeceraAnita(int $puntoventaId, string $fechaJornada): Collection
    {
        $puntoventa = Puntoventa::query()->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        $ventasErp = $this->listarVentasErpPorJornada($puntoventaId, $fechaJornada);

        return $ventasErp->filter(function (Venta $venta) use ($puntoventa): bool {
            $clave = $this->claveComprobanteDesdeVenta($venta);
            if ($clave === null) {
                return false;
            }

            $consulta = $this->consultarCabeceraAnitaDesdeClaveErp($clave, $puntoventa);
            if ($consulta['error_lectura'] !== null) {
                Log::warning('gastronomia.chequeo_anita.omitir_faltante_lectura_fallida', [
                    'venta_id' => $venta->id,
                    'codigo' => $venta->codigo,
                    'msg' => $consulta['error_lectura'],
                ]);

                return false;
            }

            return $consulta['cabecera'] === null;
        })->values();
    }

    /**
     * Indica si ya existe cabecera venta en Informix (incluye equivalencia FAK/FAC en PV CAEA).
     */
    public function existeCabeceraEnAnita(
        string $tipoAnita,
        string $letra,
        int $sucursal,
        int $numero,
    ): bool {
        if ($sucursal <= 0 || $numero <= 0) {
            return false;
        }

        foreach (GastronomiaAnitaImportEmpresaSupport::tiposDetalleAnita($tipoAnita) as $tipo) {
            $consulta = $this->consultarCabeceraAnitaPorComprobante($sucursal, $tipo, $numero, $letra);
            if ($consulta['error_lectura'] !== null) {
                Log::warning('gastronomia.chequeo_anita.cabecera_lectura_fallo', [
                    'tipo' => $tipo,
                    'sucursal' => $sucursal,
                    'numero' => $numero,
                    'msg' => $consulta['error_lectura'],
                ]);

                continue;
            }

            if ($consulta['cabecera'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indica si ya existe una fila vengrav en Informix (incluye equivalencia FAK/FAC en PV CAEA).
     */
    public function existeVengravLineaAnita(
        string $tipoAnita,
        string $letra,
        int $sucursal,
        int $numero,
        string $codigoTasa,
    ): bool {
        if ($sucursal <= 0 || $numero <= 0 || trim($codigoTasa) === '') {
            return false;
        }

        foreach (GastronomiaAnitaImportEmpresaSupport::tiposDetalleAnita($tipoAnita) as $tipo) {
            $where = " WHERE veng_sucursal = '".$sucursal."'"
                ." AND veng_tipo = '".addslashes($tipo)."'"
                ." AND veng_nro = '".$numero."'"
                ." AND veng_letra = '".addslashes($letra)."'"
                ." AND veng_codigo_tasa = '".addslashes($codigoTasa)."'";

            $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
                'acc' => 'list',
                'tabla' => 'vengrav',
                'campos' => 'veng_tipo',
                'whereArmado' => $where,
            ]));

            if ($parsed['error_lectura'] !== null) {
                Log::warning('gastronomia.chequeo_anita.vengrav_lectura_fallo', [
                    'tipo' => $tipo,
                    'sucursal' => $sucursal,
                    'numero' => $numero,
                    'codigo_tasa' => $codigoTasa,
                    'msg' => $parsed['error_lectura'],
                ]);

                continue;
            }

            if ($parsed['filas'] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indica si ya existe vencae en Informix para el comprobante.
     */
    public function existeVencaeEnAnita(
        string $tipoAnita,
        string $letra,
        int $sucursal,
        int $numero,
    ): bool {
        if ($sucursal <= 0 || $numero <= 0) {
            return false;
        }

        foreach (GastronomiaAnitaImportEmpresaSupport::tiposDetalleAnita($tipoAnita) as $tipo) {
            $where = " WHERE venc_sucursal = '".$sucursal."'"
                ." AND venc_tipo = '".addslashes($tipo)."'"
                ." AND venc_nro = '".$numero."'"
                ." AND venc_letra = '".addslashes($letra)."'";

            $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
                'acc' => 'list',
                'tabla' => 'vencae',
                'campos' => 'venc_tipo',
                'whereArmado' => $where,
            ]));

            if ($parsed['error_lectura'] !== null) {
                Log::warning('gastronomia.chequeo_anita.vencae_lectura_fallo', [
                    'tipo' => $tipo,
                    'sucursal' => $sucursal,
                    'numero' => $numero,
                    'msg' => $parsed['error_lectura'],
                ]);

                continue;
            }

            if ($parsed['filas'] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Combinaciones PV + fecha de jornada con ventas gastronomía en el rango indicado.
     *
     * @return list<array{puntoventa_id:int, codigo_pv:string, fecha_jornada:string}>
     */
    public function listarCombinacionesPvJornada(
        string $fechaDesde,
        ?string $fechaHasta,
        int $empresaId,
        ?string $codigoPv = null,
    ): array {
        $query = Venta::query()
            ->selectRaw('venta.puntoventa_id, DATE(venta.fechajornada) as fecha_jornada, puntoventa.codigo as codigo_pv')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->whereDate('venta.fechajornada', '>=', $fechaDesde)
            ->whereHas('gastronomiaEmision')
            ->where('puntoventa.modofacturacion', '!=', 'M')
            ->where('puntoventa.empresa_id', $empresaId)
            ->groupBy('venta.puntoventa_id', 'fecha_jornada', 'codigo_pv')
            ->orderBy('fecha_jornada')
            ->orderBy('codigo_pv');

        if ($fechaHasta !== null && $fechaHasta !== '') {
            $query->whereDate('venta.fechajornada', '<=', $fechaHasta);
        }

        if ($codigoPv !== null && trim($codigoPv) !== '') {
            $query->where('puntoventa.codigo', trim($codigoPv));
        }

        $filas = [];
        foreach ($query->get() as $row) {
            $filas[] = [
                'puntoventa_id' => (int) $row->puntoventa_id,
                'codigo_pv' => (string) $row->codigo_pv,
                'fecha_jornada' => (string) $row->fecha_jornada,
            ];
        }

        return $filas;
    }

    /**
     * @return Collection<int, Venta>
     */
    public function listarVentasErpPorFechaCalendario(int $puntoventaId, string $fechaCalendario): Collection
    {
        return Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->whereDate('fecha', $fechaCalendario)
            ->whereHas('gastronomiaEmision')
            ->orderBy('numerocomprobante')
            ->get(['id', 'codigo', 'numerocomprobante', 'total', 'fechajornada', 'fecha', 'tipotransaccion_id']);
    }

    /**
     * @return Collection<int, Venta>
     */
    public function listarVentasErpSinCabeceraAnitaPorFechaCalendario(int $puntoventaId, string $fechaCalendario): Collection
    {
        $puntoventa = Puntoventa::query()->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);

        return $this->listarVentasErpPorFechaCalendario($puntoventaId, $fechaCalendario)
            ->filter(function (Venta $venta) use ($puntoventa): bool {
                $clave = $this->claveComprobanteDesdeVenta($venta);
                if ($clave === null) {
                    return false;
                }

                $consulta = $this->consultarCabeceraAnitaDesdeClaveErp($clave, $puntoventa);
                if ($consulta['error_lectura'] !== null) {
                    Log::warning('gastronomia.chequeo_anita.omitir_faltante_lectura_fallida', [
                        'venta_id' => $venta->id,
                        'codigo' => $venta->codigo,
                        'msg' => $consulta['error_lectura'],
                    ]);

                    return false;
                }

                return $consulta['cabecera'] === null;
            })
            ->values();
    }

    /**
     * @return list<array{puntoventa_id:int, codigo_pv:string, fecha_calendario:string}>
     */
    public function listarCombinacionesPvFechaCalendario(
        string $fechaCalendario,
        int $empresaId,
        ?string $codigoPv = null,
    ): array {
        $query = Venta::query()
            ->selectRaw('venta.puntoventa_id, DATE(venta.fecha) as fecha_calendario, puntoventa.codigo as codigo_pv')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->whereDate('venta.fecha', $fechaCalendario)
            ->whereHas('gastronomiaEmision')
            ->where('puntoventa.modofacturacion', '!=', 'M')
            ->where('puntoventa.empresa_id', $empresaId)
            ->groupBy('venta.puntoventa_id', 'fecha_calendario', 'codigo_pv')
            ->orderBy('codigo_pv');

        if ($codigoPv !== null && trim($codigoPv) !== '') {
            $query->where('puntoventa.codigo', trim($codigoPv));
        }

        $filas = [];
        foreach ($query->get() as $row) {
            $filas[] = [
                'puntoventa_id' => (int) $row->puntoventa_id,
                'codigo_pv' => (string) $row->codigo_pv,
                'fecha_calendario' => (string) $row->fecha_calendario,
            ];
        }

        return $filas;
    }

    public function leerCabeceraAnitaPorComprobante(
        int $sucursal,
        string $tipo,
        int $numero,
        string $letra = 'B',
    ): ?object {
        return $this->consultarCabeceraAnitaPorComprobante($sucursal, $tipo, $numero, $letra)['cabecera'];
    }

    /**
     * @return array{cabecera: ?object, error_lectura: ?string}
     */
    public function consultarCabeceraAnitaPorComprobante(
        int $sucursal,
        string $tipo,
        int $numero,
        string $letra = 'B',
    ): array {
        if ($sucursal <= 0 || $tipo === '' || $numero <= 0) {
            return ['cabecera' => null, 'error_lectura' => null];
        }

        $api = new ApiAnita;
        $where = " WHERE ven_sucursal = '".$sucursal."'"
            ." AND ven_tipo = '".addslashes($tipo)."'"
            ." AND ven_nro = '".$numero."'"
            ." AND ven_letra = '".addslashes($letra)."' ";

        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => implode(',', [
                'ven_tipo', 'ven_letra', 'ven_sucursal', 'ven_nro',
                'ven_fecha', 'ven_fecha_vto',
                'ven_monto', 'ven_gravado', 'ven_exento', 'ven_impuesto1', 'ven_monto_desc',
            ]),
            'whereArmado' => $where,
        ]));

        return [
            'cabecera' => $parsed['filas'][0] ?? null,
            'error_lectura' => $parsed['error_lectura'],
        ];
    }

    /**
     * @return array{cabecera: ?object, error_lectura: ?string}
     */
    public function claveComprobanteDesdeVentaErp(Venta $venta): ?string
    {
        return $this->claveComprobanteDesdeVenta($venta);
    }

    /**
     * @return array{
     *   estado: string,
     *   erp: array{total: float, gravado: float, iva: float, exento: float},
     *   anita: ?array{total: float, gravado: float, iva: float, exento: float},
     *   diferencias: array<string, string>
     * }
     */
    public function conciliarVentaConCabeceraAnita(Venta $venta, ?object $cabeceraAnita, float $tolerancia = 0.02): array
    {
        $erpMontos = $this->montosDesdeVentaErp($venta);

        if ($cabeceraAnita === null) {
            return [
                'estado' => 'solo_erp',
                'erp' => $erpMontos,
                'anita' => null,
                'diferencias' => ['anita' => 'Sin cabecera en Informix'],
            ];
        }

        $anitaMontos = $this->montosDesdeCabeceraAnita($cabeceraAnita);
        $diferencias = $this->compararMontos($erpMontos, $anitaMontos, $tolerancia);

        return [
            'estado' => $diferencias === [] ? 'ok' : 'diferencia',
            'erp' => $erpMontos,
            'anita' => $anitaMontos,
            'diferencias' => $diferencias,
        ];
    }

    public function consultarCabeceraAnitaDesdeVenta(Venta $venta, string $letra = 'B'): array
    {
        $venta->loadMissing('puntoventas');
        $puntoventa = $venta->puntoventas;
        if (! $puntoventa) {
            return ['cabecera' => null, 'error_lectura' => 'Punto de venta no encontrado'];
        }

        $clave = $this->claveComprobanteDesdeVenta($venta);
        if ($clave === null) {
            return ['cabecera' => null, 'error_lectura' => 'Código de comprobante ERP no reconocido'];
        }

        return $this->consultarCabeceraAnitaDesdeClaveErp($clave, $puntoventa);
    }

    private function tipoAnitaConsultaDesdeErp(string $tipoErp, Puntoventa $puntoventa): string
    {
        $empresaCodigo = Empresa::query()->whereKey($puntoventa->empresa_id)->value('codigo');

        return KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge(
            $tipoErp,
            (string) $puntoventa->codigo,
            $empresaCodigo,
            $puntoventa->modofacturacion ?? null,
        );
    }

    /**
     * Montos ERP alineados con Anita (ven_gravado / ven_exento / ven_impuesto1).
     *
     * Gravado ERP = neto gravado (base IVA o Subtotal); debe coincidir con ven_gravado Anita.
     *
     * @return array{total: float, gravado: float, iva: float, exento: float}
     */
    public function montosDesdeVentaErp(Venta $venta): array
    {
        $impuestos = Venta_Impuesto::query()
            ->where('venta_id', (int) $venta->id)
            ->get(['concepto', 'importe', 'baseimponible']);

        $conceptos = [];
        $iva = 0.0;
        $exento = 0.0;

        foreach ($impuestos as $row) {
            $concepto = trim((string) ($row->concepto ?? ''));
            $importe = round((float) ($row->importe ?? 0), 2);
            $base = round((float) ($row->baseimponible ?? 0), 2);

            $conceptos[] = [
                'concepto' => $concepto,
                'importe' => $importe,
                'baseimponible' => $base,
            ];

            if (preg_match('/^Iva/i', $concepto)) {
                $iva += $importe;
            } elseif (stripos($concepto, 'exento') !== false) {
                $exento += $importe;
            }
        }

        $total = round((float) $venta->total, 2);
        $gravado = GastronomiaAnitaVenGravadoSupport::gravadoDesdeConceptosTotales($conceptos, $total);

        return [
            'total' => $total,
            'gravado' => $gravado,
            'iva' => round($iva, 2),
            'exento' => round($exento, 2),
        ];
    }

    /**
     * @return array{total: float, gravado: float, iva: float, exento: float}
     */
    private function montosDesdeCabeceraAnita(object $cab): array
    {
        return [
            'total' => round((float) ($cab->ven_monto ?? 0), 2),
            'gravado' => round((float) ($cab->ven_gravado ?? 0), 2),
            'iva' => round((float) ($cab->ven_impuesto1 ?? 0), 2),
            'exento' => round((float) ($cab->ven_exento ?? 0), 2),
        ];
    }

    /**
     * @param  array{total: float, gravado: float, iva: float, exento: float}  $erp
     * @param  array{total: float, gravado: float, iva: float, exento: float}  $anita
     * @return array<string, string>
     */
    private function compararMontos(array $erp, array $anita, float $tolerancia): array
    {
        $diffs = [];
        $esCortesia = GastronomiaAnitaVenGravadoSupport::esCortesiaMinima((float) ($erp['total'] ?? 0));

        foreach (self::CAMPOS_MONETARIOS as $campo) {
            $ev = (float) ($erp[$campo] ?? 0);
            $av = (float) ($anita[$campo] ?? 0);
            $toleranciaCampo = $esCortesia && in_array($campo, ['total', 'exento'], true)
                ? 0.001
                : $tolerancia;

            if ($this->coincideMonetario($ev, $av, $toleranciaCampo)) {
                continue;
            }

            $diffs[$campo] = 'ERP '.$this->fmt($ev).' vs Anita '.$this->fmt($av);
        }

        return $diffs;
    }

    private function coincideMonetario(float $erp, float $anita, float $tolerancia): bool
    {
        if (abs($erp - $anita) <= $tolerancia) {
            return true;
        }

        // NC: ERP negativo, Anita positivo (convención Informix).
        return abs(abs($erp) - abs($anita)) <= $tolerancia;
    }

    private function claveComprobanteDesdeVenta(Venta $venta): ?string
    {
        $desdeCodigo = GastronomiaAnitaComprobantePkSupport::claveVentaDesdeCodigoErp((string) ($venta->codigo ?? ''));
        if ($desdeCodigo !== null) {
            return $desdeCodigo;
        }

        $venta->loadMissing('puntoventas');
        $puntoventa = $venta->puntoventas;
        if ($puntoventa === null || (int) ($venta->numerocomprobante ?? 0) <= 0) {
            return null;
        }

        return GastronomiaAnitaComprobantePkSupport::claveVenta(
            'FAC',
            'B',
            $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo),
            (int) $venta->numerocomprobante,
        );
    }

    /**
     * @return array{cabecera: ?object, error_lectura: ?string}
     */
    private function consultarCabeceraAnitaDesdeClaveErp(string $clave, Puntoventa $puntoventa): array
    {
        $partes = GastronomiaAnitaComprobantePkSupport::parseClaveVenta($clave);
        if ($partes === null) {
            return ['cabecera' => null, 'error_lectura' => 'Clave de comprobante ERP no reconocida'];
        }

        $tipoAnita = $this->tipoAnitaConsultaDesdeErp($partes['tipo'], $puntoventa);
        $ultimoError = null;

        foreach (GastronomiaAnitaImportEmpresaSupport::tiposDetalleAnita($tipoAnita) as $tipo) {
            $consulta = $this->consultarCabeceraAnitaPorComprobante(
                $partes['sucursal'],
                $tipo,
                $partes['numero'],
                $partes['letra'],
            );
            if ($consulta['error_lectura'] !== null) {
                $ultimoError = $consulta['error_lectura'];

                continue;
            }

            if ($consulta['cabecera'] !== null) {
                return $consulta;
            }
        }

        return ['cabecera' => null, 'error_lectura' => $ultimoError];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaBase(Venta $venta, string $clave, ?object $anita): array
    {
        $partes = GastronomiaAnitaComprobantePkSupport::parseClaveVenta($clave);

        return [
            'estado' => 'ok',
            'clave' => $clave,
            'codigo_erp' => (string) $venta->codigo,
            'venta_id' => (int) $venta->id,
            'tipo' => $partes['tipo'] ?? '',
            'numero' => (int) ($partes['numero'] ?? 0),
            'ven_fecha_anita' => $anita !== null ? (string) ($anita->ven_fecha ?? '') : null,
            'erp' => null,
            'anita' => null,
            'diferencias' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaError(Venta $venta, string $mensaje): array
    {
        return [
            'estado' => 'error',
            'clave' => null,
            'codigo_erp' => (string) $venta->codigo,
            'venta_id' => (int) $venta->id,
            'tipo' => null,
            'numero' => (int) ($venta->numerocomprobante ?? 0),
            'erp' => $this->montosDesdeVentaErp($venta),
            'anita' => null,
            'diferencias' => ['parse' => $mensaje],
        ];
    }

    /**
     * @param  Collection<int, Venta>  $ventasErp
     * @param  array<string, object>  $anitaPorClave
     * @param  array<string, int>  $conteo
     * @return array<string, mixed>
     */
    private function armarResumen(
        Collection $ventasErp,
        array $anitaPorClave,
        array $conteo,
        float $tolerancia,
        array $numerosExcluidosConciliacion = [],
    ): array {
        $totalesErp = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];
        foreach ($ventasErp as $venta) {
            $m = $this->montosDesdeVentaErp($venta);
            $esNc = $this->esNotaCreditoErp($venta);
            foreach (self::CAMPOS_MONETARIOS as $c) {
                $totalesErp[$c] += $esNc ? -abs($m[$c]) : $m[$c];
            }
        }
        foreach ($totalesErp as $k => $v) {
            $totalesErp[$k] = round($v, 2);
        }

        $totalesAnitaBruto = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];
        $totalesAnitaSignoErp = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];
        $cabecerasAnitaUnicas = GastronomiaAnitaComprobantePkSupport::cabecerasUnicasDesdeMapa($anitaPorClave);
        foreach ($cabecerasAnitaUnicas as $cab) {
            $numeroAnita = (int) ($cab->ven_nro ?? 0);
            if ($numeroAnita > 0 && isset($numerosExcluidosConciliacion[$numeroAnita])) {
                continue;
            }
            $m = $this->montosDesdeCabeceraAnita($cab);
            $esNc = $this->esNotaCreditoAnita($cab);
            foreach (self::CAMPOS_MONETARIOS as $c) {
                $totalesAnitaBruto[$c] += $m[$c];
                $totalesAnitaSignoErp[$c] += $esNc ? -abs($m[$c]) : $m[$c];
            }
        }
        foreach ($totalesAnitaBruto as $k => $v) {
            $totalesAnitaBruto[$k] = round($v, 2);
        }
        foreach ($totalesAnitaSignoErp as $k => $v) {
            $totalesAnitaSignoErp[$k] = round($v, 2);
        }

        return [
            'ventas_erp' => $ventasErp->count(),
            'cabeceras_anita' => count($cabecerasAnitaUnicas),
            'tolerancia' => $tolerancia,
            'conteo' => $conteo,
            'totales_erp' => $totalesErp,
            'totales_anita_bruto' => $totalesAnitaBruto,
            'totales_anita_signo_erp' => $totalesAnitaSignoErp,
            'delta_totales' => [
                'total' => round($totalesErp['total'] - $totalesAnitaSignoErp['total'], 2),
                'gravado' => round($totalesErp['gravado'] - $totalesAnitaSignoErp['gravado'], 2),
                'iva' => round($totalesErp['iva'] - $totalesAnitaSignoErp['iva'], 2),
                'exento' => round($totalesErp['exento'] - $totalesAnitaSignoErp['exento'], 2),
            ],
            'filtro_anita' => 'ven_sucursal + ven_fecha_vto (fecha jornada) + ven_letra=B',
        ];
    }

    /**
     * Facturación bruta Anita (cabeceras venta, sin NC) por PV y fecha de jornada.
     */
    public function totalFacturacionBrutaAnitaPorJornada(int $puntoventaId, string $fechaJornada): float
    {
        $puntoventa = Puntoventa::query()->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        $fechaEntera = (int) str_replace('-', '', $fechaJornada);
        if ($sucursal <= 0 || $fechaEntera <= 0) {
            return 0.0;
        }

        $cabeceras = $this->listarCabecerasAnitaPorJornada($sucursal, $fechaEntera, $puntoventa);
        $total = 0.0;
        foreach ($cabeceras as $cab) {
            if ($this->esNotaCreditoAnita($cab)) {
                continue;
            }
            $total += (float) ($cab->ven_monto ?? 0);
        }

        return round($total, 2);
    }

    /**
     * Suma ven_monto Anita de facturas ERP (sin NC) emparejadas por clave comprobante.
     * Solo ventas con emisión gastronomía; opcionalmente excluye claves ajenas al circuito gastro.
     *
     * @param  list<int>  $ventaIds
     * @param  array<int, array<string, object>>  $cacheCabecerasPorPv
     * @param  list<string>  $clavesExcluir
     */
    public function totalFacturacionBrutaAnitaParaVentasIds(
        array $ventaIds,
        string $fechaJornada,
        array &$cacheCabecerasPorPv = [],
        array $clavesExcluir = [],
        ?array $indiceAnitaBulk = null,
    ): float {
        if ($ventaIds === []) {
            return 0.0;
        }

        $clavesExcluirSet = array_fill_keys($clavesExcluir, true);

        $ventas = Venta::query()
            ->whereIn('id', $ventaIds)
            ->whereHas('gastronomiaEmision', fn ($q) => $q->whereNull('venta_factura_origen_id'))
            ->get(['id', 'codigo', 'numerocomprobante', 'puntoventa_id']);

        $total = 0.0;
        foreach ($ventas as $venta) {
            $clave = $this->claveComprobanteDesdeVenta($venta);
            if ($clave === null || isset($clavesExcluirSet[$clave])) {
                continue;
            }

            $pvId = (int) ($venta->puntoventa_id ?? 0);
            if ($pvId <= 0) {
                continue;
            }

            if (! isset($cacheCabecerasPorPv[$pvId])) {
                $cacheCabecerasPorPv[$pvId] = $this->cabecerasAnitaMapPorPuntoventa(
                    $pvId,
                    $fechaJornada,
                    $clavesExcluir,
                    $indiceAnitaBulk,
                );
            }

            $cab = $cacheCabecerasPorPv[$pvId][$clave] ?? null;
            if ($cab === null || $this->esNotaCreditoAnita($cab)) {
                continue;
            }

            $total += (float) ($cab->ven_monto ?? 0);
        }

        return round($total, 2);
    }

    /**
     * @param  list<string>  $clavesExcluir
     * @return array<string, object>
     */
    public function cabecerasAnitaMapPorPuntoventa(
        int $puntoventaId,
        string $fechaJornada,
        array $clavesExcluir = [],
        ?array $indiceAnitaBulk = null,
    ): array {
        if ($indiceAnitaBulk !== null) {
            return $this->cabecerasAnitaMapDesdeIndiceBulk($puntoventaId, $fechaJornada, $clavesExcluir, $indiceAnitaBulk);
        }

        $puntoventa = Puntoventa::query()->with('empresas')->find($puntoventaId);
        if ($puntoventa === null) {
            return [];
        }

        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        $fechaEntera = (int) str_replace('-', '', $fechaJornada);
        if ($sucursal <= 0 || $fechaEntera <= 0) {
            return [];
        }

        $map = $this->listarCabecerasAnitaPorJornada($sucursal, $fechaEntera, $puntoventa);

        if ($clavesExcluir === []) {
            return $map;
        }

        foreach ($clavesExcluir as $clave) {
            unset($map[$clave]);
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, array<string, object>>>  $indiceAnitaBulk
     * @return array<string, object>
     */
    private function cabecerasAnitaMapDesdeIndiceBulk(
        int $puntoventaId,
        string $fechaJornada,
        array $clavesExcluir,
        array $indiceAnitaBulk,
    ): array {
        $puntoventa = Puntoventa::query()->find($puntoventaId);
        if ($puntoventa === null) {
            return [];
        }

        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        $map = $indiceAnitaBulk[$sucursal][$fechaJornada] ?? [];

        if ($clavesExcluir === []) {
            return $map;
        }

        foreach ($clavesExcluir as $clave) {
            unset($map[$clave]);
        }

        return $map;
    }

    private function esNotaCreditoAnita(object $cab): bool
    {
        $tipo = strtoupper(trim((string) ($cab->ven_tipo ?? '')));

        return str_starts_with($tipo, 'NC') || (float) ($cab->ven_monto ?? 0) < 0;
    }

    private function esNotaCreditoErp(Venta $venta): bool
    {
        $codigo = strtoupper(trim((string) ($venta->codigo ?? '')));
        if (str_starts_with($codigo, 'NCD') || str_starts_with($codigo, 'NC ')) {
            return true;
        }

        return round((float) $venta->total, 2) < 0;
    }

    private function fmt(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }

    public function sucursalDesdeCodigoPuntoventa(string $codigo): int
    {
        return (int) preg_replace('/\D+/', '', trim($codigo));
    }

    /**
     * Actualiza ven_gravado en Anita para ventas ERP ya emparejadas con cabecera.
     *
     * @param  iterable<int, Venta>  $ventas
     * @return array{revisadas:int, actualizadas:int, errores:list<array<string, mixed>>}
     */
    public function repararVenGravadoEnAnitaPorVentasErp(iterable $ventas, float $tolerancia = 0.02): array
    {
        return GastronomiaAnitaVenGravadoSupport::repararGravadoDesdeVentasErp(
            $ventas,
            fn (Venta $venta): array => $this->montosDesdeVentaErp($venta),
            function (Venta $venta): ?object {
                $consulta = $this->consultarCabeceraAnitaDesdeVenta($venta);

                return $consulta['cabecera'];
            },
            $tolerancia,
        );
    }

    /**
     * @return Collection<int, Venta>
     */
    public function listarVentasGastronomiaEmpresaRangoJornada(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
    ): Collection {
        return Venta::query()
            ->with(['puntoventas.empresas'])
            ->whereHas('gastronomiaEmision')
            ->whereHas('puntoventas', function ($q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId)
                    ->where('modofacturacion', '!=', 'M');
            })
            ->whereDate('fechajornada', '>=', $fechaDesde)
            ->whereDate('fechajornada', '<=', $fechaHasta)
            ->orderBy('fechajornada')
            ->orderBy('numerocomprobante')
            ->get();
    }

    /**
     * Índice sucursal → jornada → clave tipo|letra|sucursal|nro desde filas venta Anita (cache bulk).
     *
     * @param  list<object>  $filasAnita
     * @param  iterable<int, Puntoventa>  $puntoventas
     * @return array<int, array<string, array<string, object>>>
     */
    public function indexarCabecerasAnitaDesdeFilas(array $filasAnita, iterable $puntoventas): array
    {
        /** @var array<int, Puntoventa> $pvPorSucursal */
        $pvPorSucursal = [];
        foreach ($puntoventas as $pv) {
            $suc = $this->sucursalDesdeCodigoPuntoventa((string) $pv->codigo);
            if ($suc > 0) {
                $pvPorSucursal[$suc] = $pv;
            }
        }

        $map = [];

        foreach ($filasAnita as $fila) {
            $sucursal = (int) preg_replace('/\D+/', '', (string) ($fila->ven_sucursal ?? ''));
            $pv = $pvPorSucursal[$sucursal] ?? null;
            if ($pv === null) {
                continue;
            }

            $tipo = strtoupper(trim((string) ($fila->ven_tipo ?? '')));
            $nro = (int) ($fila->ven_nro ?? 0);
            if ($tipo === '' || $nro <= 0) {
                continue;
            }

            $empresaCodigo = $pv->empresas?->codigo ?? $pv->empresa_id;
            if (! GastronomiaAnitaImportEmpresaSupport::cabeceraCorrespondeAlPv(
                $fila,
                $pv,
                $empresaCodigo,
            )) {
                continue;
            }

            $fechaJornada = $this->fechaJornadaDesdeAnitaEntera((string) ($fila->ven_fecha_vto ?? ''));
            if ($fechaJornada === null) {
                continue;
            }

            $esKandikoCaea = KandikoAnitaVentaTipoSupport::esPvCaeaKandiko(
                (string) $pv->codigo,
                $empresaCodigo,
                $pv->modofacturacion ?? null,
            );

            if ($esKandikoCaea && in_array($tipo, KandikoAnitaVentaTipoSupport::tiposAnitaEquivalentesFacErp(), true)) {
                foreach (GastronomiaAnitaComprobantePkSupport::clavesConciliacionDesdeCabeceraAnita($fila, true) as $clave) {
                    $existente = $map[$sucursal][$fechaJornada][$clave] ?? null;
                    $tipoExistente = $existente !== null
                        ? strtoupper(trim((string) ($existente->ven_tipo ?? '')))
                        : '';
                    if ($existente === null || ($tipo === KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE && $tipoExistente !== KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE)) {
                        $map[$sucursal][$fechaJornada][$clave] = $fila;
                    }
                }

                continue;
            }

            $clave = GastronomiaAnitaComprobantePkSupport::claveVentaDesdeCabeceraAnita($fila);
            if ($clave !== null) {
                $map[$sucursal][$fechaJornada][$clave] = $fila;
            }
        }

        return $map;
    }

    /**
     * @param  list<object>  $filasVengrav
     * @return array<string, true>
     */
    public function indexarVengravDesdeFilas(array $filasVengrav): array
    {
        $map = [];
        foreach ($filasVengrav as $fila) {
            $tipo = strtoupper(trim((string) ($fila->veng_tipo ?? '')));
            $sucursal = (int) preg_replace('/\D+/', '', (string) ($fila->veng_sucursal ?? ''));
            $nro = (int) ($fila->veng_nro ?? 0);
            $letra = strtoupper(trim((string) ($fila->veng_letra ?? 'B')));
            $codigoTasa = trim((string) ($fila->veng_codigo_tasa ?? ''));
            if ($tipo === '' || $sucursal <= 0 || $nro <= 0 || $codigoTasa === '') {
                continue;
            }

            $map[$this->claveVengravIndice($tipo, $sucursal, $letra, $nro, $codigoTasa)] = true;
        }

        return $map;
    }

    /**
     * @param  list<object>  $filasVencae
     * @return array<string, true>
     */
    public function indexarVencaeDesdeFilas(array $filasVencae): array
    {
        $map = [];

        foreach ($filasVencae as $fila) {
            $tipo = strtoupper(trim((string) ($fila->venc_tipo ?? '')));
            $sucursal = (int) preg_replace('/\D+/', '', (string) ($fila->venc_sucursal ?? ''));
            $nro = (int) ($fila->venc_nro ?? 0);
            $letra = strtoupper(trim((string) ($fila->venc_letra ?? 'B')));
            if ($tipo === '' || $sucursal <= 0 || $nro <= 0) {
                continue;
            }

            $map[$this->claveVencaeIndice($tipo, $sucursal, $letra, $nro)] = true;
        }

        return $map;
    }

    public function cabeceraExisteEnIndiceAnita(
        array $anitaPorPvJornada,
        Puntoventa $puntoventa,
        string $fechaJornada,
        string $claveErp,
    ): bool {
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);

        return ($anitaPorPvJornada[$sucursal][$fechaJornada][$claveErp] ?? null) !== null;
    }

    /**
     * Cabecera Anita en cualquier jornada del PV (mismo comprobante).
     */
    public function cabeceraExisteEnIndiceAnitaPorComprobante(
        array $anitaPorPvJornada,
        Puntoventa $puntoventa,
        string $claveErp,
    ): bool {
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        if (! isset($anitaPorPvJornada[$sucursal])) {
            return false;
        }

        foreach ($anitaPorPvJornada[$sucursal] as $cabeceras) {
            if (isset($cabeceras[$claveErp])) {
                return true;
            }
        }

        return false;
    }

    public function existeVengravEnIndiceAnita(
        array $vengravIndice,
        string $tipoAnita,
        int $sucursal,
        string $letra,
        int $numero,
        string $codigoTasa,
    ): bool {
        if ($sucursal <= 0 || $numero <= 0 || trim($codigoTasa) === '') {
            return false;
        }

        foreach (GastronomiaAnitaImportEmpresaSupport::tiposDetalleAnita($tipoAnita) as $tipo) {
            if (isset($vengravIndice[$this->claveVengravIndice($tipo, $sucursal, $letra, $numero, $codigoTasa)])) {
                return true;
            }
        }

        return false;
    }

    public function existeVencaeEnIndiceAnita(
        array $vencaeIndice,
        string $tipoAnita,
        int $sucursal,
        string $letra,
        int $numero,
    ): bool {
        if ($sucursal <= 0 || $numero <= 0) {
            return false;
        }

        foreach (GastronomiaAnitaImportEmpresaSupport::tiposDetalleAnita($tipoAnita) as $tipo) {
            if (isset($vencaeIndice[$this->claveVencaeIndice($tipo, $sucursal, $letra, $numero)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * PK Informix (tipo|letra|sucursal|nro) tal como se graba en venta.unl.
     */
    public function pkComprobanteDesdeVentaErp(Venta $venta, string $letra = 'B'): ?string
    {
        $venta->loadMissing('puntoventas.empresas');
        $puntoventa = $venta->puntoventas;
        if ($puntoventa === null) {
            return null;
        }

        $partes = GastronomiaAnitaComprobantePkSupport::parseClaveVenta($this->claveComprobanteDesdeVenta($venta) ?? '');
        if ($partes === null) {
            return null;
        }

        $empresaCodigo = $puntoventa->empresas?->codigo ?? $puntoventa->empresa_id;
        $tipoAnita = KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge(
            $partes['tipo'],
            (string) $puntoventa->codigo,
            $empresaCodigo,
            $puntoventa->modofacturacion ?? null,
        );

        return GastronomiaAnitaComprobantePkSupport::claveVenta(
            $tipoAnita,
            $partes['letra'],
            $partes['sucursal'],
            $partes['numero'],
        );
    }

    /**
     * Ventas ERP sin cabecera en Anita (PK tipo|letra|sucursal|nro).
     *
     * @param  array<string, true>  $ventaPkIndice
     * @return Collection<int, Venta>
     */
    public function listarVentasErpSinCabeceraAnitaEnRango(
        int $empresaId,
        string $fechaDesde,
        ?string $fechaHasta,
        ?string $codigoPv,
        array $ventaPkIndice,
    ): Collection {
        $hasta = $fechaHasta !== null && $fechaHasta !== '' ? $fechaHasta : $fechaDesde;
        $ventas = $this->listarVentasGastronomiaEmpresaRangoJornada($empresaId, $fechaDesde, $hasta);

        if ($codigoPv !== null && trim($codigoPv) !== '') {
            $codigoPv = trim($codigoPv);
            $ventas = $ventas->filter(
                static fn (Venta $venta): bool => $venta->puntoventas !== null
                    && (string) $venta->puntoventas->codigo === $codigoPv,
            );
        }

        return $ventas->filter(function (Venta $venta) use ($ventaPkIndice): bool {
            $pk = $this->pkComprobanteDesdeVentaErp($venta);

            return $pk !== null && ! isset($ventaPkIndice[$pk]);
        })->values();
    }

    private function claveVengravIndice(string $tipo, int $sucursal, string $letra, int $numero, string $codigoTasa): string
    {
        return strtoupper(trim($tipo)).'|'.$sucursal.'|'.strtoupper(trim($letra)).'|'.$numero.'|'.trim($codigoTasa);
    }

    private function claveVencaeIndice(string $tipo, int $sucursal, string $letra, int $numero): string
    {
        return strtoupper(trim($tipo)).'|'.$sucursal.'|'.strtoupper(trim($letra)).'|'.$numero;
    }

    private function fechaJornadaDesdeAnitaEntera(string $fechaEntera): ?string
    {
        $fechaEntera = preg_replace('/\D+/', '', $fechaEntera);
        if ($fechaEntera === null || strlen($fechaEntera) !== 8) {
            return null;
        }

        return substr($fechaEntera, 0, 4).'-'.substr($fechaEntera, 4, 2).'-'.substr($fechaEntera, 6, 2);
    }
}
