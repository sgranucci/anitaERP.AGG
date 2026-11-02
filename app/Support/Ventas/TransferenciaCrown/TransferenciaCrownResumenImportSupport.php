<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Ventas\Puntoventa;
use Illuminate\Support\Facades\DB;

/**
 * Resumen de lo cargado en una transferencia Crown (éxitos + maestros usados + links a mayor).
 */
final class TransferenciaCrownResumenImportSupport
{
    /**
     * @param  array<string, mixed>  $contexto  origen, titulo, sala, desde, hasta, empresa_id, preview, resultado
     * @return array<string, mixed>
     */
    public static function armar(array $contexto): array
    {
        $empresaId = max(1, (int) ($contexto['empresa_id'] ?? 1));
        $desde = (string) ($contexto['desde'] ?? '');
        $hasta = (string) ($contexto['hasta'] ?? '');
        $preview = is_array($contexto['preview'] ?? null) ? $contexto['preview'] : [];
        $resultado = is_array($contexto['resultado'] ?? null) ? $contexto['resultado'] : [];
        $filas = is_array($preview['filas'] ?? null) ? $preview['filas'] : [];
        $resumenPreview = is_array($preview['resumen'] ?? null) ? $preview['resumen'] : [];

        $grabados = (int) ($resultado['grabados'] ?? 0);
        $enganchados = (int) ($resultado['enganchados'] ?? 0);
        $erroresConfirm = is_array($resultado['errores'] ?? null) ? $resultado['errores'] : [];
        $asientos = (int) ($resultado['asientos'] ?? ($grabados + $enganchados));
        $totalGrabado = round((float) ($resultado['total_grabado'] ?? 0), 2);
        $totalEnganchado = round((float) ($resultado['total_enganchado'] ?? 0), 2);

        $exitosas = self::filasExitosas($filas, $erroresConfirm);
        [$puntosVenta, $centros, $cuentas] = self::agregarMaestros($exitosas, $empresaId);

        if ($desde === '' || $hasta === '') {
            [$desde, $hasta] = TransferenciaCrownControlSupport::rangoFechasDesdeFilas($filas);
        }

        return self::empaquetar(
            origen: (string) ($contexto['origen'] ?? ''),
            titulo: (string) ($contexto['titulo'] ?? 'Resultado de la importación'),
            sala: (string) ($contexto['sala'] ?? ''),
            desde: $desde,
            hasta: $hasta,
            empresaId: $empresaId,
            modo: 'post_import',
            contadores: [
                'leidos' => (int) ($resumenPreview['leidos'] ?? count($filas)),
                'nuevos_preview' => (int) ($resumenPreview['nuevos'] ?? 0),
                'enganchar_preview' => (int) ($resumenPreview['enganchar'] ?? 0),
                'existen' => (int) ($resumenPreview['existen'] ?? 0),
                'errores_preview' => (int) ($resumenPreview['errores'] ?? 0),
                'grabados' => $grabados,
                'enganchados' => $enganchados,
                'asientos' => $asientos,
                'errores_confirm' => count($erroresConfirm),
                'sin_asiento' => 0,
                'total_grabado' => $totalGrabado,
                'total_enganchado' => $totalEnganchado,
                'total_ok' => round($totalGrabado + $totalEnganchado, 2),
            ],
            errores: array_slice($erroresConfirm, 0, 30),
            puntosVenta: $puntosVenta,
            centros: $centros,
            cuentas: $cuentas,
        );
    }

    /**
     * Consulta posterior: arma el mismo informe desde lo ya grabado en el ERP.
     *
     * @return array<string, mixed>
     */
    public static function armarDesdePeriodo(
        int $empresaId,
        string $desde,
        string $hasta,
        string $origen = 'todos',
    ): array {
        $empresaId = max(1, $empresaId);
        $desde = trim($desde);
        $hasta = trim($hasta);
        if ($desde === '' || $hasta === '') {
            $hoy = now()->format('Y-m-d');
            $desde = $desde !== '' ? $desde : now()->startOfMonth()->format('Y-m-d');
            $hasta = $hasta !== '' ? $hasta : $hoy;
        }

        $ventas = DB::table('venta as v')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->leftJoin('asiento as a', 'a.venta_id', '=', 'v.id')
            ->where('pv.empresa_id', $empresaId)
            ->whereBetween('v.fecha', [$desde, $hasta])
            ->where(function ($q) use ($origen): void {
                if ($origen === 'arca') {
                    $q->where('v.leyenda', 'like', 'Importación ARCA%');
                } elseif ($origen === 'gastro') {
                    $q->where('v.leyenda', 'like', 'Transferencia gastronomía%');
                } else {
                    $q->where('v.leyenda', 'like', 'Transferencia gastronomía%')
                        ->orWhere('v.leyenda', 'like', 'Importación ARCA%');
                }
            })
            ->orderBy('v.fecha')
            ->orderBy('v.id')
            ->get([
                'v.id',
                'v.total',
                'v.leyenda',
                'v.codigo',
                'pv.codigo as pv_codigo',
                'a.id as asiento_id',
            ]);

        $asientoIds = [];
        $filasSynth = [];
        $conAsiento = 0;
        $sinAsiento = 0;
        $totalCon = 0.0;
        $totalSin = 0.0;
        foreach ($ventas as $venta) {
            $total = round((float) ($venta->total ?? 0), 2);
            $tieneAsiento = (int) ($venta->asiento_id ?? 0) > 0;
            if ($tieneAsiento) {
                $conAsiento++;
                $totalCon = round($totalCon + $total, 2);
                $asientoIds[(int) $venta->asiento_id] = (int) $venta->asiento_id;
            } else {
                $sinAsiento++;
                $totalSin = round($totalSin + $total, 2);
            }
            $filasSynth[] = [
                'estado' => $tieneAsiento ? 'nuevo' : 'error',
                'sucursal' => (int) ltrim((string) ($venta->pv_codigo ?? '0'), '0') ?: (int) ($venta->pv_codigo ?? 0),
                'total' => abs($total),
                'tipo' => $total < 0 ? 'NCD' : 'FAC',
                'lineas_asiento' => [],
                '_asiento_id' => (int) ($venta->asiento_id ?? 0),
            ];
        }

        $lineasPorAsiento = [];
        if ($asientoIds !== []) {
            foreach (array_chunk(array_values($asientoIds), 2000) as $lote) {
                $rows = DB::table('asiento_movimiento as m')
                    ->join('cuentacontable as c', 'c.id', '=', 'm.cuentacontable_id')
                    ->whereIn('m.asiento_id', $lote)
                    ->get([
                        'm.asiento_id',
                        'm.cuentacontable_id',
                        'm.monto',
                        'c.codigo',
                        'c.nombre',
                    ]);
                foreach ($rows as $row) {
                    $aid = (int) $row->asiento_id;
                    $monto = (float) ($row->monto ?? 0);
                    $lineasPorAsiento[$aid][] = [
                        'cuentacontable_id' => (int) $row->cuentacontable_id,
                        'codigo' => (string) $row->codigo,
                        'nombre' => (string) $row->nombre,
                        'dh' => $monto < 0 ? 'H' : 'D',
                        'importe' => abs($monto),
                    ];
                }
            }
        }
        foreach ($filasSynth as &$fila) {
            $aid = (int) ($fila['_asiento_id'] ?? 0);
            if ($aid > 0) {
                $fila['lineas_asiento'] = $lineasPorAsiento[$aid] ?? [];
            }
            unset($fila['_asiento_id']);
        }
        unset($fila);

        $exitosas = array_values(array_filter(
            $filasSynth,
            static fn (array $f): bool => ($f['estado'] ?? '') === 'nuevo',
        ));
        [$puntosVenta, $centros, $cuentas] = self::agregarMaestros($exitosas, $empresaId);

        $titulo = match ($origen) {
            'gastro' => 'Resumen transferido — Gastronomía',
            'arca' => 'Resumen transferido — ARCA / PXSOL',
            default => 'Resumen de lo transferido',
        };

        return self::empaquetar(
            origen: $origen,
            titulo: $titulo,
            sala: '',
            desde: $desde,
            hasta: $hasta,
            empresaId: $empresaId,
            modo: 'consulta',
            contadores: [
                'leidos' => count($ventas),
                'nuevos_preview' => 0,
                'enganchar_preview' => 0,
                'existen' => 0,
                'errores_preview' => 0,
                'grabados' => $conAsiento,
                'enganchados' => 0,
                'asientos' => count($asientoIds),
                'errores_confirm' => 0,
                'sin_asiento' => $sinAsiento,
                'total_grabado' => $totalCon,
                'total_enganchado' => 0.0,
                'total_ok' => round($totalCon + $totalSin, 2),
                'total_sin_asiento' => $totalSin,
            ],
            errores: [],
            puntosVenta: $puntosVenta,
            centros: $centros,
            cuentas: $cuentas,
        );
    }

    /**
     * @param  array<string, float|int>  $contadores
     * @param  list<string>  $errores
     * @param  list<array<string, mixed>>  $puntosVenta
     * @param  list<array<string, mixed>>  $centros
     * @param  list<array<string, mixed>>  $cuentas
     * @return array<string, mixed>
     */
    private static function empaquetar(
        string $origen,
        string $titulo,
        string $sala,
        string $desde,
        string $hasta,
        int $empresaId,
        string $modo,
        array $contadores,
        array $errores,
        array $puntosVenta,
        array $centros,
        array $cuentas,
    ): array {
        return [
            'modo' => $modo,
            'origen' => $origen,
            'titulo' => $titulo,
            'sala' => $sala,
            'desde' => $desde,
            'hasta' => $hasta,
            'empresa_id' => $empresaId,
            'contadores' => $contadores,
            'errores' => $errores,
            'puntos_venta' => $puntosVenta,
            'centros_costo' => $centros,
            'cuentas' => $cuentas,
            'url_mayor_cuentas' => self::urlMayor(
                $empresaId,
                $desde,
                $hasta,
                array_column($cuentas, 'codigo'),
                null,
            ),
            'url_mayor_centros' => self::urlMayor(
                $empresaId,
                $desde,
                $hasta,
                [],
                array_column($centros, 'codigo'),
            ),
            'puede_mayor' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<string>  $erroresConfirm
     * @return list<array<string, mixed>>
     */
    private static function filasExitosas(array $filas, array $erroresConfirm): array
    {
        $rechazadas = [];
        foreach ($erroresConfirm as $msg) {
            $etiqueta = trim(explode(':', (string) $msg, 2)[0] ?? '');
            if ($etiqueta !== '') {
                $rechazadas[$etiqueta] = true;
            }
        }

        $out = [];
        foreach ($filas as $fila) {
            $estado = (string) ($fila['estado'] ?? '');
            if ($estado !== 'nuevo' && $estado !== 'enganchar') {
                continue;
            }
            $etiqueta = TransferenciaCrownComprobante::codigoVisible($fila);
            if (isset($rechazadas[$etiqueta])) {
                continue;
            }
            $out[] = $fila;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}
     */
    private static function agregarMaestros(array $filas, int $empresaId): array
    {
        $porPv = [];
        $porCc = [];
        $porCuenta = [];

        foreach ($filas as $fila) {
            $sucursal = (int) ($fila['sucursal'] ?? 0);
            $signo = TransferenciaCrownComprobante::esCredito($fila) ? -1 : 1;
            $total = round($signo * abs((float) ($fila['total'] ?? 0)), 2);
            $pvKey = (string) $sucursal;
            if (! isset($porPv[$pvKey])) {
                $porPv[$pvKey] = [
                    'sucursal' => $sucursal,
                    'codigo' => str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT),
                    'nombre' => '',
                    'comprobantes' => 0,
                    'total' => 0.0,
                ];
            }
            $porPv[$pvKey]['comprobantes']++;
            $porPv[$pvKey]['total'] = round($porPv[$pvKey]['total'] + $total, 2);

            $ccId = TransferenciaCrownImputacionSupport::centrocostoIdParaSucursal($empresaId, $sucursal);
            if ($ccId !== null && $ccId > 0) {
                $ccKey = (string) $ccId;
                if (! isset($porCc[$ccKey])) {
                    $porCc[$ccKey] = [
                        'id' => $ccId,
                        'codigo' => '',
                        'nombre' => '',
                        'comprobantes' => 0,
                        'total' => 0.0,
                    ];
                }
                $porCc[$ccKey]['comprobantes']++;
                $porCc[$ccKey]['total'] = round($porCc[$ccKey]['total'] + $total, 2);
            }

            foreach (is_array($fila['lineas_asiento'] ?? null) ? $fila['lineas_asiento'] : [] as $linea) {
                $cuentaId = (int) ($linea['cuentacontable_id'] ?? 0);
                if ($cuentaId <= 0) {
                    continue;
                }
                $key = (string) $cuentaId;
                if (! isset($porCuenta[$key])) {
                    $porCuenta[$key] = [
                        'id' => $cuentaId,
                        'codigo' => (string) ($linea['codigo'] ?? ''),
                        'nombre' => (string) ($linea['nombre'] ?? ''),
                        'lineas' => 0,
                        'debe' => 0.0,
                        'haber' => 0.0,
                    ];
                }
                $importe = round(abs((float) ($linea['importe'] ?? 0)), 2);
                $porCuenta[$key]['lineas']++;
                if (($linea['dh'] ?? 'D') === 'H') {
                    $porCuenta[$key]['haber'] = round($porCuenta[$key]['haber'] + $importe, 2);
                } else {
                    $porCuenta[$key]['debe'] = round($porCuenta[$key]['debe'] + $importe, 2);
                }
            }
        }

        $pvIds = [];
        foreach ($porPv as $row) {
            $pvIds[] = $row['codigo'];
        }
        if ($pvIds !== []) {
            $nombres = Puntoventa::query()
                ->whereIn('codigo', $pvIds)
                ->get(['codigo', 'nombre'])
                ->keyBy(static fn ($p) => str_pad((string) $p->codigo, 5, '0', STR_PAD_LEFT));
            foreach ($porPv as $k => $row) {
                $porPv[$k]['nombre'] = (string) ($nombres->get($row['codigo'])->nombre ?? '');
            }
        }

        if ($porCc !== []) {
            $ccs = Centrocosto::query()
                ->whereIn('id', array_map('intval', array_keys($porCc)))
                ->get(['id', 'codigo', 'nombre'])
                ->keyBy('id');
            foreach ($porCc as $k => $row) {
                $cc = $ccs->get((int) $row['id']);
                $porCc[$k]['codigo'] = (string) ($cc->codigo ?? '');
                $porCc[$k]['nombre'] = (string) ($cc->nombre ?? '');
            }
        }

        if ($porCuenta !== []) {
            $faltanCodigo = [];
            foreach ($porCuenta as $row) {
                if (trim((string) $row['codigo']) === '') {
                    $faltanCodigo[] = (int) $row['id'];
                }
            }
            if ($faltanCodigo !== []) {
                $cuentas = Cuentacontable::query()
                    ->whereIn('id', $faltanCodigo)
                    ->get(['id', 'codigo', 'nombre'])
                    ->keyBy('id');
                foreach ($faltanCodigo as $id) {
                    $c = $cuentas->get($id);
                    if ($c === null) {
                        continue;
                    }
                    $porCuenta[(string) $id]['codigo'] = (string) $c->codigo;
                    $porCuenta[(string) $id]['nombre'] = (string) $c->nombre;
                }
            }
        }

        $puntos = array_values($porPv);
        usort($puntos, static fn ($a, $b) => $a['codigo'] <=> $b['codigo']);
        $centros = array_values($porCc);
        usort($centros, static fn ($a, $b) => $a['codigo'] <=> $b['codigo']);
        $cuentasOut = array_values($porCuenta);
        usort($cuentasOut, static fn ($a, $b) => $a['codigo'] <=> $b['codigo']);

        return [$puntos, $centros, $cuentasOut];
    }

    /**
     * Completa URLs de mayor en cada fila de cuenta/CC.
     *
     * @param  array<string, mixed>  $resumen
     * @return array<string, mixed>
     */
    public static function conUrlsMayor(array $resumen): array
    {
        $empresaId = (int) ($resumen['empresa_id'] ?? 1);
        $desde = (string) ($resumen['desde'] ?? '');
        $hasta = (string) ($resumen['hasta'] ?? '');

        foreach ($resumen['cuentas'] as $i => $cuenta) {
            $resumen['cuentas'][$i]['url_mayor'] = self::urlMayor(
                $empresaId,
                $desde,
                $hasta,
                [(string) ($cuenta['codigo'] ?? '')],
                null,
            );
        }
        foreach ($resumen['centros_costo'] as $i => $cc) {
            $resumen['centros_costo'][$i]['url_mayor'] = self::urlMayor(
                $empresaId,
                $desde,
                $hasta,
                [],
                [(string) ($cc['codigo'] ?? '')],
            );
        }

        return $resumen;
    }

    /**
     * @param  list<string|int>  $cuentasCodigo
     * @param  list<string|int>|null  $centrosCodigo
     */
    public static function urlMayor(
        int $empresaId,
        string $desde,
        string $hasta,
        array $cuentasCodigo,
        ?array $centrosCodigo,
    ): string {
        $params = [
            'consultar' => 1,
            'modo_periodo' => 'rango',
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'empresa_ids' => [$empresaId],
            'consolidar_empresas' => 1,
            'moneda_id' => 1,
            'incluye_subdiario' => 0,
            'modo_inclusion_asientos' => 'sin_cierre_ni_inflacion',
            'mostrar_columna_centrocosto' => 1,
        ];
        $cuentas = [];
        foreach ($cuentasCodigo as $codigo) {
            $n = (int) preg_replace('/\D+/', '', (string) $codigo);
            if ($n > 0) {
                $cuentas[$n] = $n;
            }
        }
        if ($cuentas !== []) {
            $params['cuentas'] = implode(',', array_values($cuentas));
        }
        $ccs = [];
        foreach ($centrosCodigo ?? [] as $codigo) {
            $c = trim((string) $codigo);
            if ($c !== '') {
                $ccs[$c] = $c;
            }
        }
        if ($ccs !== []) {
            $params['centrocostos_codigo'] = implode(',', array_values($ccs));
        }

        return route('mayor_plano_cuenta', $params);
    }
}
