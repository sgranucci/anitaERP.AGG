<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\Venta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Control venta a venta por ventana horaria: total ERP, cobrado (cobranza) y cabecera Anita (bridge venta).
 */
final class GastronomiaControlVentasCobranzasAnitaService
{
    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoService,
    ) {}

    /**
     * @param  list<int>  $empresaIds
     * @return array{
     *   ventana: array{desde:string,hasta:string},
     *   resumen: array<string, mixed>,
     *   por_empresa: list<array<string, mixed>>,
     *   filas: list<array<string, mixed>>
     * }
     */
    public function ejecutar(
        string $desde,
        string $hasta,
        array $empresaIds = [],
        float $tolerancia = 0.02,
        bool $soloProblemas = true,
    ): array {
        $ventas = $this->listarVentasGastronomiaEnVentana($desde, $hasta, $empresaIds);
        $cobradoPorVenta = $this->sumarCobranzasPorVenta(
            $ventas->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $desde,
            $hasta,
        );
        $cacheAnita = $this->precargarCabecerasAnita($ventas);

        $filas = [];
        $conteo = [
            'ok' => 0,
            'solo_erp' => 0,
            'diferencia_anita' => 0,
            'desglose_anita' => 0,
            'cobranza_desfasada' => 0,
            'error' => 0,
        ];
        $totales = [
            'venta_erp' => 0.0,
            'cobrado_erp' => 0.0,
            'anita_ven_monto' => 0.0,
        ];
        $porEmpresa = [];

        foreach ($ventas as $venta) {
            $ventaId = (int) $venta->id;
            $empresaId = (int) ($venta->empresa_id ?? 0);
            $pvId = (int) ($venta->puntoventa_id ?? 0);
            $pvCodigo = (string) ($venta->codigo_pv ?? '');
            $fechaJornada = $this->fechaJornadaDesdeVenta($venta);
            $totalVenta = round((float) $venta->total, 2);
            $cobrado = round((float) ($cobradoPorVenta[$ventaId] ?? 0), 2);
            $exentoCobranza = $this->exentoControlCobranza($totalVenta, $cobrado);

            $totales['venta_erp'] += $totalVenta;
            $totales['cobrado_erp'] += $cobrado;

            $clave = $this->chequeoService->claveComprobanteDesdeVentaErp($venta);
            $obs = [];
            $estadoAnita = 'ok';
            $anitaTotal = null;
            $anitaMontos = null;

            if ($clave === null) {
                $conteo['error']++;
                $estadoAnita = 'error';
                $obs[] = 'Código de comprobante ERP no reconocido';
            } else {
                $cabecera = $cacheAnita[$this->cacheKey($pvId, $fechaJornada)][$clave] ?? null;
                $conciliacion = $this->chequeoService->conciliarVentaConCabeceraAnita($venta, $cabecera, $tolerancia);
                $estadoAnita = (string) ($conciliacion['estado'] ?? 'error');
                $anitaMontos = $conciliacion['anita'] ?? null;
                if ($anitaMontos !== null) {
                    $anitaTotal = round((float) ($anitaMontos['total'] ?? 0), 2);
                    $totales['anita_ven_monto'] += $anitaTotal;
                }
                if ($estadoAnita === 'solo_erp') {
                    $conteo['solo_erp']++;
                } elseif ($estadoAnita === 'diferencia') {
                    $erpTotal = round((float) ($conciliacion['erp']['total'] ?? 0), 2);
                    $anitaTotalCmp = round((float) ($anitaMontos['total'] ?? 0), 2);
                    if ($this->diffMonetario($erpTotal, $anitaTotalCmp, $tolerancia) === null) {
                        $estadoAnita = 'ok';
                        $conteo['desglose_anita']++;
                        $obs[] = 'Desglose grav/IVA: '.implode('; ', array_values($conciliacion['diferencias'] ?? []));
                    } else {
                        $conteo['diferencia_anita']++;
                        $obs[] = implode('; ', array_values($conciliacion['diferencias'] ?? []));
                    }
                }
            }

            $estadoCobranza = 'ok';
            if (! $exentoCobranza) {
                $diffCob = $this->diffMonetario($totalVenta, $cobrado, $tolerancia);
                if ($diffCob !== null) {
                    $estadoCobranza = 'cobranza_desfasada';
                    $conteo['cobranza_desfasada']++;
                    $obs[] = 'Cobranza: '.$diffCob;
                }
            }

            $estado = $this->estadoFinal($estadoAnita, $estadoCobranza);
            if ($estado === 'ok') {
                $conteo['ok']++;
            }

            if (! isset($porEmpresa[$empresaId])) {
                $porEmpresa[$empresaId] = [
                    'empresa_id' => $empresaId,
                    'ventas' => 0,
                    'venta_erp' => 0.0,
                    'cobrado_erp' => 0.0,
                    'anita_ven_monto' => 0.0,
                    'problemas' => 0,
                ];
            }
            $porEmpresa[$empresaId]['ventas']++;
            $porEmpresa[$empresaId]['venta_erp'] += $totalVenta;
            $porEmpresa[$empresaId]['cobrado_erp'] += $cobrado;
            if ($anitaTotal !== null) {
                $porEmpresa[$empresaId]['anita_ven_monto'] += $anitaTotal;
            }
            if ($estado !== 'ok') {
                $porEmpresa[$empresaId]['problemas']++;
            }

            if (! $soloProblemas || $estado !== 'ok') {
                $filas[] = [
                    'estado' => $estado,
                    'venta_id' => $ventaId,
                    'codigo' => (string) $venta->codigo,
                    'clave' => $clave,
                    'empresa_id' => $empresaId,
                    'pv_codigo' => $pvCodigo,
                    'fecha_jornada' => $fechaJornada,
                    'created_at' => (string) $venta->created_at,
                    'total_erp' => $totalVenta,
                    'cobrado_erp' => $cobrado,
                    'anita_ven_monto' => $anitaTotal,
                    'anita_gravado' => $anitaMontos['gravado'] ?? null,
                    'anita_iva' => $anitaMontos['iva'] ?? null,
                    'delta_anita' => $anitaTotal !== null ? round($totalVenta - $anitaTotal, 2) : null,
                    'delta_cobranza' => round($totalVenta - $cobrado, 2),
                    'exento_cobranza' => $exentoCobranza,
                    'solo_desglose' => $estadoAnita === 'ok' && str_contains(implode(' ', $obs), 'Desglose'),
                    'observaciones' => implode(' | ', $obs),
                ];
            }
        }

        foreach ($totales as $k => $v) {
            $totales[$k] = round($v, 2);
        }

        usort($filas, static function (array $a, array $b): int {
            $order = ['cobranza_desfasada' => 0, 'solo_erp' => 1, 'diferencia_anita' => 2, 'error' => 3, 'ok' => 4];
            $ea = $order[$a['estado']] ?? 9;
            $eb = $order[$b['estado']] ?? 9;
            if ($ea !== $eb) {
                return $ea <=> $eb;
            }

            return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
        });

        return [
            'ventana' => ['desde' => $desde, 'hasta' => $hasta],
            'resumen' => [
                'ventas' => $ventas->count(),
                'tolerancia' => $tolerancia,
                'conteo' => $conteo,
                'totales' => $totales,
                'delta_venta_anita' => round($totales['venta_erp'] - $totales['anita_ven_monto'], 2),
                'delta_venta_cobrado' => round($totales['venta_erp'] - $totales['cobrado_erp'], 2),
            ],
            'por_empresa' => array_values($porEmpresa),
            'filas' => $filas,
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return Collection<int, Venta>
     */
    private function listarVentasGastronomiaEnVentana(string $desde, string $hasta, array $empresaIds): Collection
    {
        $query = Venta::query()
            ->select([
                'venta.id',
                'venta.codigo',
                'venta.numerocomprobante',
                'venta.total',
                'venta.created_at',
                'venta.fecha',
                'venta.fechajornada',
                'venta.puntoventa_id',
                'venta.tipotransaccion_id',
                'puntoventa.codigo as codigo_pv',
                'puntoventa.empresa_id',
            ])
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->whereNull('venta.deleted_at')
            ->where('venta.created_at', '>=', $desde)
            ->where('venta.created_at', '<', $hasta)
            ->whereHas('gastronomiaEmision')
            ->orderBy('venta.created_at')
            ->orderBy('venta.id');

        if ($empresaIds !== []) {
            $query->whereIn('puntoventa.empresa_id', $empresaIds);
        }

        return $query->get();
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, float>
     */
    private function sumarCobranzasPorVenta(array $ventaIds, string $desde, string $hasta): array
    {
        if ($ventaIds === []) {
            return [];
        }

        $rows = DB::table('cobranza')
            ->selectRaw('venta_id, SUM(monto) as cobrado')
            ->whereIn('venta_id', $ventaIds)
            ->where('estado', 'CONFIRMADA')
            ->where('created_at', '>=', $desde)
            ->where('created_at', '<', $hasta)
            ->groupBy('venta_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->venta_id] = (float) $row->cobrado;
        }

        return $map;
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     * @return array<string, array<string, object>>
     */
    private function precargarCabecerasAnita(Collection $ventas): array
    {
        $cache = [];
        $combinaciones = [];

        foreach ($ventas as $venta) {
            $pvId = (int) ($venta->puntoventa_id ?? 0);
            $fechaJornada = $this->fechaJornadaDesdeVenta($venta);
            if ($pvId <= 0 || $fechaJornada === '') {
                continue;
            }
            $combinaciones[$this->cacheKey($pvId, $fechaJornada)] = [
                'puntoventa_id' => $pvId,
                'fecha_jornada' => $fechaJornada,
            ];
        }

        foreach ($combinaciones as $key => $combo) {
            try {
                $cache[$key] = $this->chequeoService->cabecerasAnitaMapPorPuntoventa(
                    (int) $combo['puntoventa_id'],
                    (string) $combo['fecha_jornada'],
                );
            } catch (\Throwable $e) {
                $cache[$key] = [];
            }
        }

        return $cache;
    }

    private function fechaJornadaDesdeVenta(Venta $venta): string
    {
        $fj = $venta->fechajornada ?? null;
        if ($fj !== null && (string) $fj !== '') {
            return substr((string) $fj, 0, 10);
        }

        return substr((string) ($venta->fecha ?? ''), 0, 10);
    }

    private function cacheKey(int $puntoventaId, string $fechaJornada): string
    {
        return $puntoventaId.'|'.$fechaJornada;
    }

    private function exentoControlCobranza(float $totalVenta, float $cobrado): bool
    {
        if (abs($totalVenta) <= 0.02) {
            return true;
        }

        if (abs($cobrado) <= 0.02 && $totalVenta > 0.02) {
            return false;
        }

        return false;
    }

    private function diffMonetario(float $a, float $b, float $tolerancia): ?string
    {
        if (abs($a - $b) <= $tolerancia) {
            return null;
        }
        if (abs(abs($a) - abs($b)) <= $tolerancia) {
            return null;
        }

        return number_format($a, 2, '.', '').' vs '.number_format($b, 2, '.', '');
    }

    private function estadoFinal(string $estadoAnita, string $estadoCobranza): string
    {
        if ($estadoAnita === 'error') {
            return 'error';
        }
        if ($estadoAnita === 'solo_erp') {
            return 'solo_erp';
        }
        if ($estadoAnita === 'diferencia') {
            return 'diferencia_anita';
        }
        if ($estadoCobranza === 'cobranza_desfasada') {
            return 'cobranza_desfasada';
        }

        return 'ok';
    }
}
