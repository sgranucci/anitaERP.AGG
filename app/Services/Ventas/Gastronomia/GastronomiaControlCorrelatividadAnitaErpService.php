<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportEstacionamientoSupport;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportResvtaSupport;
use Illuminate\Support\Collection;

/**
 * Correlatividad ERP ↔ Anita por fecha de jornada + número (codigo).
 * ERP ordenado por venta.codigo; Anita por ven_tipo + ven_letra + ven_sucursal + ven_nro.
 */
final class GastronomiaControlCorrelatividadAnitaErpService
{
    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoService,
    ) {}

    /**
     * @param  list<int>  $empresaIds
     * @return array{
     *   jornada: array{fecha:string,corte_inicio:?string,corte_fin:?string},
     *   resumen: array<string, mixed>,
     *   por_puntoventa: list<array<string, mixed>>,
     *   huecos: list<array<string, mixed>>,
     *   filas: list<array<string, mixed>>
     * }
     */
    public function ejecutar(
        string $fechaJornada,
        array $empresaIds = [],
        ?string $corteInicio = null,
        ?string $corteFin = null,
    ): array {
        $puntosVenta = $this->listarPuntosVenta($empresaIds);

        $filas = [];
        $huecos = [];
        $porPuntoventa = [];
        $resumen = [
            'ventas_erp' => 0,
            'cabeceras_anita' => 0,
            'pares_ok' => 0,
            'solo_erp' => 0,
            'solo_anita' => 0,
            'excluido_estacionamiento' => 0,
            'excluido_resvta_legacy' => 0,
            'dif_monto' => 0,
            'huecos_corr_erp' => 0,
            'erp_corte_inicio' => 0,
            'erp_corte_fin' => 0,
        ];

        foreach ($puntosVenta as $puntoventa) {
            $pvId = (int) $puntoventa->id;
            $pvCodigo = (string) $puntoventa->codigo;
            $empresaId = (int) $puntoventa->empresa_id;

            $ventasErp = $this->listarVentasErpJornada($pvId, $fechaJornada);
            $anitaMap = $this->chequeoService->cabecerasAnitaMapPorPuntoventa($pvId, $fechaJornada);
            $anitaOrdenadas = $this->ordenarCabecerasAnita($anitaMap);

            if ($ventasErp->isEmpty() && $anitaOrdenadas === []) {
                continue;
            }

            $erpPorNumero = $this->indexarErpPorNumero($ventasErp);
            $anitaPorNumero = $this->indexarAnitaPorNumero($anitaOrdenadas);
            $numeros = $this->unionNumeros($erpPorNumero, $anitaPorNumero);
            $sucursal = (int) preg_replace('/\D+/', '', trim($pvCodigo));
            $empresaCodigo = $puntoventa->empresas?->codigo ?? $empresaId;
            $numerosEstacionamiento = GastronomiaAnitaImportEstacionamientoSupport::numerosEstacionamientoEnSucursal(
                $sucursal,
                $empresaCodigo,
                $numeros,
            );
            $numerosResvtaLegacy = GastronomiaAnitaImportResvtaSupport::numerosConResvtaEnSucursal(
                $sucursal,
                $empresaCodigo,
                $numeros,
            );

            $ordenadas = $ventasErp->sortBy(fn (Venta $v) => (string) $v->codigo)->values();
            $huecosPv = $this->detectarHuecosCorrelativos($ordenadas);
            foreach ($huecosPv as $row) {
                $row['pv_codigo'] = $pvCodigo;
                $row['empresa_id'] = $empresaId;
                $huecos[] = $row;
            }
            $resumen['huecos_corr_erp'] += count($huecosPv);

            $statsPv = [
                'pares_ok' => 0,
                'solo_erp' => 0,
                'solo_anita' => 0,
                'excluido_estacionamiento' => 0,
                'excluido_resvta_legacy' => 0,
                'dif_monto' => 0,
            ];

            foreach ($numeros as $numero) {
                $venta = $erpPorNumero[$numero] ?? null;
                $cab = $anitaPorNumero[$numero] ?? null;
                $fila = $this->armarFila(
                    $pvCodigo,
                    $empresaId,
                    $numero,
                    $venta,
                    $cab,
                    $corteInicio,
                    $corteFin,
                    $huecosPv,
                    $numerosResvtaLegacy,
                );
                $filas[] = $fila;

                $estado = (string) ($fila['estado'] ?? '');
                if ($estado === 'ok') {
                    $statsPv['pares_ok']++;
                } elseif ($estado === 'solo_erp') {
                    $statsPv['solo_erp']++;
                } elseif ($estado === 'solo_anita') {
                    $statsPv['solo_anita']++;
                } elseif ($estado === 'excluido_resvta_legacy') {
                    $statsPv['excluido_resvta_legacy']++;
                    if (isset($numerosEstacionamiento[$numero])) {
                        $statsPv['excluido_estacionamiento']++;
                    }
                } elseif ($estado === 'excluido_estacionamiento') {
                    $statsPv['excluido_estacionamiento']++;
                } elseif ($estado === 'dif_monto') {
                    $statsPv['dif_monto']++;
                }

                if ($fila['erp_en_corte_inicio'] ?? false) {
                    $resumen['erp_corte_inicio']++;
                }
                if ($fila['erp_en_corte_fin'] ?? false) {
                    $resumen['erp_corte_fin']++;
                }
            }

            $resumen['ventas_erp'] += $ventasErp->count();
            $resumen['cabeceras_anita'] += count($anitaOrdenadas);
            $resumen['pares_ok'] += $statsPv['pares_ok'];
            $resumen['solo_erp'] += $statsPv['solo_erp'];
            $resumen['solo_anita'] += $statsPv['solo_anita'];
            $resumen['excluido_estacionamiento'] += $statsPv['excluido_estacionamiento'];
            $resumen['excluido_resvta_legacy'] += $statsPv['excluido_resvta_legacy'];
            $resumen['dif_monto'] += $statsPv['dif_monto'];

            $porPuntoventa[] = [
                'pv_codigo' => $pvCodigo,
                'empresa_id' => $empresaId,
                'ventas_erp' => $ventasErp->count(),
                'cabeceras_anita' => count($anitaOrdenadas),
                'pares_ok' => $statsPv['pares_ok'],
                'solo_erp' => $statsPv['solo_erp'],
                'solo_anita' => $statsPv['solo_anita'],
                'excluido_estacionamiento' => $statsPv['excluido_estacionamiento'],
                'excluido_resvta_legacy' => $statsPv['excluido_resvta_legacy'],
                'dif_monto' => $statsPv['dif_monto'],
                'huecos_corr' => count($huecosPv),
                'min_numero' => $numeros === [] ? null : min($numeros),
                'max_numero' => $numeros === [] ? null : max($numeros),
            ];
        }

        usort($filas, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['pv_codigo'] ?? ''), (string) ($b['pv_codigo'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a['numero'] ?? 0)) <=> ((int) ($b['numero'] ?? 0));
        });

        usort($porPuntoventa, static fn (array $a, array $b) => strcmp((string) $a['pv_codigo'], (string) $b['pv_codigo']));

        return [
            'jornada' => [
                'fecha' => $fechaJornada,
                'corte_inicio' => $corteInicio,
                'corte_fin' => $corteFin,
            ],
            'resumen' => $resumen,
            'por_puntoventa' => $porPuntoventa,
            'huecos' => $huecos,
            'filas' => $filas,
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return Collection<int, Puntoventa>
     */
    private function listarPuntosVenta(array $empresaIds): Collection
    {
        $query = Puntoventa::query()
            ->with('empresas')
            ->where('modofacturacion', '!=', 'M')
            ->orderBy('codigo');

        if ($empresaIds !== []) {
            $query->whereIn('empresa_id', $empresaIds);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Venta>
     */
    private function listarVentasErpJornada(int $puntoventaId, string $fechaJornada): Collection
    {
        return Venta::query()
            ->select([
                'venta.id', 'venta.codigo', 'venta.numerocomprobante', 'venta.total',
                'venta.created_at', 'venta.fechajornada', 'venta.puntoventa_id', 'venta.tipotransaccion_id',
            ])
            ->where('puntoventa_id', $puntoventaId)
            ->whereDate('fechajornada', $fechaJornada)
            ->whereNull('deleted_at')
            ->whereHas('gastronomiaEmision')
            ->orderBy('venta.codigo')
            ->get();
    }

    /**
     * @param  array<string, object>  $anitaMap
     * @return list<object>
     */
    private function ordenarCabecerasAnita(array $anitaMap): array
    {
        $lista = array_values($anitaMap);
        usort($lista, static function (object $a, object $b): int {
            $cmp = strcmp(
                self::claveOrdenAnita($a),
                self::claveOrdenAnita($b),
            );

            return $cmp;
        });

        return $lista;
    }

    private static function claveOrdenAnita(object $cab): string
    {
        return strtoupper(trim((string) ($cab->ven_tipo ?? ''))).'|'
            .strtoupper(trim((string) ($cab->ven_letra ?? ''))).'|'
            .str_pad(trim((string) ($cab->ven_sucursal ?? '')), 5, '0', STR_PAD_LEFT).'|'
            .str_pad((string) (int) ($cab->ven_nro ?? 0), 8, '0', STR_PAD_LEFT);
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     * @return array<int, Venta>
     */
    private function indexarErpPorNumero(Collection $ventas): array
    {
        $map = [];
        foreach ($ventas as $venta) {
            $n = (int) ($venta->numerocomprobante ?? 0);
            if ($n > 0) {
                $map[$n] = $venta;
            }
        }

        return $map;
    }

    /**
     * @param  list<object>  $cabeceras
     * @return array<int, object>
     */
    private function indexarAnitaPorNumero(array $cabeceras): array
    {
        $map = [];
        foreach ($cabeceras as $cab) {
            $tipo = strtoupper(trim((string) ($cab->ven_tipo ?? '')));
            if (str_starts_with($tipo, 'NC')) {
                continue;
            }
            $n = (int) ($cab->ven_nro ?? 0);
            if ($n > 0) {
                $map[$n] = $cab;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, Venta>  $erpPorNumero
     * @param  array<int, object>  $anitaPorNumero
     * @return list<int>
     */
    private function unionNumeros(array $erpPorNumero, array $anitaPorNumero): array
    {
        $numeros = array_unique(array_merge(array_keys($erpPorNumero), array_keys($anitaPorNumero)));
        sort($numeros, SORT_NUMERIC);

        return array_values($numeros);
    }

    /**
     * @param  list<array{desde:int,hasta:int,faltantes:string,cantidad:int}>  $huecosPv
     * @return array<string, mixed>
     */
    private function armarFila(
        string $pvCodigo,
        int $empresaId,
        int $numero,
        ?Venta $venta,
        ?object $cab,
        ?string $corteInicio,
        ?string $corteFin,
        array $huecosPv,
        array $numerosResvtaLegacy = [],
    ): array {
        $estado = 'ok';
        $obs = [];
        $clave = $venta !== null ? $this->chequeoService->claveComprobanteDesdeVentaErp($venta) : null;

        if ($venta === null && $cab !== null) {
            if (isset($numerosResvtaLegacy[$numero])) {
                $estado = 'excluido_resvta_legacy';
                $obs[] = 'Cabecera Anita legacy (resvta); no emitida por AnitaERP';
            } else {
                $estado = 'solo_anita';
                $obs[] = 'Cabecera Anita sin venta ERP en jornada';
            }
        } elseif ($venta !== null && $cab === null) {
            $estado = 'solo_erp';
            $obs[] = 'Venta ERP sin cabecera Anita en jornada';
        } elseif ($venta !== null && $cab !== null) {
            $anitaMonto = round((float) ($cab->ven_monto ?? 0), 2);
            $erpTotal = round((float) $venta->total, 2);
            if (abs($erpTotal - $anitaMonto) > 0.02 && abs(abs($erpTotal) - abs($anitaMonto)) > 0.02) {
                $estado = 'dif_monto';
                $obs[] = 'ERP '.$erpTotal.' vs Anita '.$anitaMonto;
            }
        }

        if ($venta !== null) {
            $salto = $this->saltoAntes($huecosPv, (int) $venta->numerocomprobante);
            if ($salto !== '') {
                $obs[] = $salto;
            }
        }

        $createdAt = $venta !== null ? (string) $venta->created_at : null;

        return [
            'pv_codigo' => $pvCodigo,
            'empresa_id' => $empresaId,
            'numero' => $numero,
            'estado' => $estado,
            'codigo_erp' => $venta !== null ? (string) $venta->codigo : null,
            'clave' => $clave,
            'venta_id' => $venta !== null ? (int) $venta->id : null,
            'numero_erp' => $venta !== null ? (int) $venta->numerocomprobante : null,
            'total_erp' => $venta !== null ? round((float) $venta->total, 2) : null,
            'created_at' => $createdAt,
            'erp_en_corte_inicio' => $this->existiaEnCorte($createdAt, $corteInicio),
            'erp_en_corte_fin' => $this->existiaEnCorte($createdAt, $corteFin),
            'anita_orden' => $cab !== null ? self::claveOrdenAnita($cab) : null,
            'anita_tipo' => $cab !== null ? (string) ($cab->ven_tipo ?? '') : null,
            'anita_nro' => $cab !== null ? (int) ($cab->ven_nro ?? 0) : null,
            'anita_monto' => $cab !== null ? round((float) ($cab->ven_monto ?? 0), 2) : null,
            'anita_empresa' => $cab !== null ? (string) ($cab->ven_empresa ?? '') : null,
            'observaciones' => implode(' | ', $obs),
        ];
    }

    private function existiaEnCorte(?string $createdAt, ?string $corte): bool
    {
        if ($createdAt === null || $corte === null || trim($corte) === '') {
            return false;
        }

        return $createdAt < $corte;
    }

    /**
     * @param  Collection<int, Venta>  $ordenadas
     * @return list<array{desde:int,hasta:int,faltantes:string,cantidad:int}>
     */
    private function detectarHuecosCorrelativos(Collection $ordenadas): array
    {
        $huecos = [];
        $prev = null;

        foreach ($ordenadas as $venta) {
            $n = (int) ($venta->numerocomprobante ?? 0);
            if ($n <= 0) {
                continue;
            }
            if ($prev !== null && $n > $prev + 1) {
                $faltantes = range($prev + 1, $n - 1);
                $huecos[] = [
                    'desde' => $prev,
                    'hasta' => $n,
                    'faltantes' => implode(',', array_map('strval', $faltantes)),
                    'cantidad' => count($faltantes),
                ];
            }
            $prev = $n;
        }

        return $huecos;
    }

    /**
     * @param  list<array{desde:int,hasta:int,faltantes:string,cantidad:int}>  $huecosPv
     */
    private function saltoAntes(array $huecosPv, int $numero): string
    {
        foreach ($huecosPv as $h) {
            if ((int) ($h['hasta'] ?? 0) === $numero) {
                return 'Hueco corr.: faltan '.$h['faltantes'];
            }
        }

        return '';
    }
}
