<?php

declare(strict_types=1);

namespace App\Services\Caja\Estacionamiento;

use App\ApiAnita;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Services\Ventas\Gastronomia\GastronomiaChequeoVentasAnitaErpService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Concilia ventas estacionamiento ERP ↔ cabecera Anita (Informix).
 */
final class EstacionamientoChequeoVentasAnitaErpService
{
    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $gastronomiaChequeo,
    ) {
    }

    /**
     * @return Collection<int, Venta>
     */
    public function listarVentasErpPorJornada(int $puntoventaId, string $fechaJornada): Collection
    {
        return Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->whereDate('fechajornada', $fechaJornada)
            ->whereHas('estacionamientoEmision')
            ->orderBy('numerocomprobante')
            ->get(['id', 'puntoventa_id', 'codigo', 'numerocomprobante', 'total', 'fechajornada', 'fecha', 'tipotransaccion_id']);
    }

    /**
     * Ventas estacionamiento del ERP sin cabecera en Informix para un PV y fecha de jornada.
     *
     * @return Collection<int, Venta>
     */
    public function listarVentasErpSinCabeceraAnita(int $puntoventaId, string $fechaJornada): Collection
    {
        $puntoventa = Puntoventa::query()->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        $fechaEntera = (int) str_replace('-', '', $fechaJornada);

        $anitaPorClave = $this->listarCabecerasAnitaPorJornada($sucursal, $fechaEntera);
        $ventasErp = $this->listarVentasErpPorJornada($puntoventaId, $fechaJornada);

        return $ventasErp->filter(function (Venta $venta) use ($anitaPorClave): bool {
            $clave = $this->claveComprobanteDesdeVenta($venta);

            return $clave !== null && ! isset($anitaPorClave[$clave]);
        })->values();
    }

    /**
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
            ->whereHas('estacionamientoEmision')
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
    ): array {
        $puntoventa = Puntoventa::query()->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        if ($sucursal <= 0) {
            throw new \InvalidArgumentException('Código de punto de venta inválido: '.$puntoventa->codigo);
        }

        $ventasErp = $this->listarVentasErpPorJornada($puntoventaId, $fechaJornada);
        $filas = [];
        $conteo = [
            'ok' => 0,
            'diferencia' => 0,
            'solo_erp' => 0,
            'solo_anita' => 0,
            'error' => 0,
        ];
        $anitaPorClave = [];
        $totalesErp = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];
        $totalesAnita = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];

        foreach ($ventasErp as $venta) {
            $consulta = $this->gastronomiaChequeo->consultarCabeceraAnitaDesdeVenta($venta);
            if ($consulta['error_lectura'] !== null) {
                $conteo['error']++;
                $erpMontos = $this->gastronomiaChequeo->montosDesdeVentaErp($venta);
                foreach (['total', 'gravado', 'iva', 'exento'] as $c) {
                    $totalesErp[$c] += (float) ($erpMontos[$c] ?? 0);
                }
                $filas[] = [
                    'estado' => 'error',
                    'codigo_erp' => (string) $venta->codigo,
                    'venta_id' => (int) $venta->id,
                    'numero' => (int) ($venta->numerocomprobante ?? 0),
                    'erp' => $erpMontos,
                    'diferencias' => [
                        'anita' => 'Error de lectura Anita: '.$consulta['error_lectura'],
                    ],
                ];

                continue;
            }

            $conc = $this->gastronomiaChequeo->conciliarVentaConCabeceraAnita(
                $venta,
                $consulta['cabecera'],
                $tolerancia,
            );
            $estado = (string) ($conc['estado'] ?? 'error');
            if (isset($conteo[$estado])) {
                $conteo[$estado]++;
            } else {
                $conteo['error']++;
                $estado = 'error';
            }

            $erpMontos = $conc['erp'] ?? [];
            foreach (['total', 'gravado', 'iva', 'exento'] as $c) {
                $totalesErp[$c] += (float) ($erpMontos[$c] ?? 0);
            }

            if ($consulta['cabecera'] !== null) {
                $clave = $this->claveComprobanteDesdeVenta($venta);
                if ($clave !== null) {
                    $anitaPorClave[$clave] = $consulta['cabecera'];
                }
                $anitaMontos = $conc['anita'] ?? [];
                foreach (['total', 'gravado', 'iva', 'exento'] as $c) {
                    $totalesAnita[$c] += (float) ($anitaMontos[$c] ?? 0);
                }
            }

            if (! $soloDiferencias || $estado !== 'ok') {
                $filas[] = [
                    'estado' => $estado,
                    'codigo_erp' => (string) $venta->codigo,
                    'venta_id' => (int) $venta->id,
                    'numero' => (int) ($venta->numerocomprobante ?? 0),
                    'erp' => $erpMontos,
                    'anita' => $conc['anita'] ?? null,
                    'diferencias' => $conc['diferencias'] ?? [],
                ];
            }
        }

        foreach ($totalesErp as $k => $v) {
            $totalesErp[$k] = round($v, 2);
        }
        foreach ($totalesAnita as $k => $v) {
            $totalesAnita[$k] = round($v, 2);
        }

        $delta = [];
        foreach (['total', 'gravado', 'iva', 'exento'] as $c) {
            $delta[$c] = round($totalesErp[$c] - $totalesAnita[$c], 2);
        }

        return [
            'puntoventa' => (string) $puntoventa->codigo,
            'sucursal' => $sucursal,
            'fecha_jornada' => $fechaJornada,
            'resumen' => [
                'ventas_erp' => $ventasErp->count(),
                'cabeceras_anita' => count($anitaPorClave),
                'tolerancia' => $tolerancia,
                'conteo' => $conteo,
                'totales_erp' => $totalesErp,
                'totales_anita_signo_erp' => $totalesAnita,
                'delta_totales' => $delta,
                'filtro_anita' => 'ven_sucursal + ven_fecha_vto (jornada) + ven_tipo + ven_nro + ven_letra=B',
            ],
            'filas' => $filas,
        ];
    }

    /**
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
                $conteoGlobal[$k] += (int) ($res['conteo'][$k] ?? 0);
            }
            foreach (['total', 'gravado', 'iva', 'exento'] as $c) {
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
        foreach (['total', 'gravado', 'iva', 'exento'] as $c) {
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
                'filtro_erp' => 'venta.fechajornada + estacionamiento_emision',
                'filtro_anita' => 'ven_sucursal + ven_fecha_vto (jornada) + ven_tipo + ven_nro + ven_letra=B',
            ],
        ];
    }

    /**
     * @return array{cabecera: ?object, error_lectura: ?string}
     */
    public function consultarCabeceraAnitaDesdeVenta(Venta $venta, string $letra = 'B'): array
    {
        return $this->gastronomiaChequeo->consultarCabeceraAnitaDesdeVenta($venta, $letra);
    }

    /**
     * @return array<string, object>
     */
    private function listarCabecerasAnitaPorJornada(int $sucursal, int $fechaEntera): array
    {
        $api = new ApiAnita;
        $where = " WHERE ven_sucursal = '".$sucursal."'"
            ." AND ven_fecha_vto = '".$fechaEntera."'"
            ." AND ven_letra = 'B' ";

        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => implode(',', [
                'ven_tipo', 'ven_letra', 'ven_sucursal', 'ven_nro',
                'ven_fecha', 'ven_fecha_vto',
                'ven_monto', 'ven_gravado', 'ven_exento', 'ven_impuesto1', 'ven_monto_desc',
            ]),
            'whereArmado' => $where,
            'orderBy' => 'ven_tipo, ven_nro',
        ]));

        if ($parsed['error_lectura'] !== null) {
            Log::warning('estacionamiento.chequeo_anita.lista_jornada_fallo', [
                'sucursal' => $sucursal,
                'fecha_jornada' => $fechaEntera,
                'msg' => $parsed['error_lectura'],
            ]);

            throw new \RuntimeException(
                'No se pudo listar cabeceras Anita para la jornada: '.$parsed['error_lectura']
            );
        }

        $map = [];
        foreach ($parsed['filas'] as $fila) {
            $tipo = trim((string) ($fila->ven_tipo ?? ''));
            $nro = (int) ($fila->ven_nro ?? 0);
            if ($tipo === '' || $nro <= 0) {
                continue;
            }
            $map[$tipo.'-'.$nro] = $fila;
        }

        return $map;
    }

    private function claveComprobanteDesdeVenta(Venta $venta): ?string
    {
        $codigo = trim((string) ($venta->codigo ?? ''));
        if (preg_match('/^(\S+)\s+[A-Z]-\d+-(\d+)$/', $codigo, $m)) {
            return $m[1].'-'.(int) $m[2];
        }

        if ((int) ($venta->numerocomprobante ?? 0) <= 0) {
            return null;
        }

        return 'FAC-'.(int) $venta->numerocomprobante;
    }

    private function sucursalDesdeCodigoPuntoventa(string $codigo): int
    {
        return (int) preg_replace('/\D+/', '', trim($codigo));
    }
}
