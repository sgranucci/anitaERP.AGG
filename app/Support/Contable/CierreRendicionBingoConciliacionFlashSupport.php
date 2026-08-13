<?php

namespace App\Support\Contable;

use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Models\Caja\Flash\FlashCaja;
use App\Models\Configuracion\Empresa;
use App\Support\Contable\Efe\EfeAnitaBridgeReader;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Informe p-vtabingo.c (venta de sala) + columnas flash para conciliar recaudación/resultado.
 */
final class CierreRendicionBingoConciliacionFlashSupport
{
    private const TOLERANCIA_DEFAULT = 0.02;

    /**
     * @return array<string, mixed>
     */
    public function conciliar(int $empresaId, string $fechaDesde, string $fechaHasta, ?float $tolerancia = null): array
    {
        $empresa = Empresa::query()->findOrFail($empresaId);
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $tolerancia = $tolerancia ?? (float) config(
            'bingo.cierre_rendicion_contable.conciliacion_flash_tolerancia',
            self::TOLERANCIA_DEFAULT,
        );

        $concbIndex = CierreRendicionBingoConcbingoSupport::indicePorConcepto($empresaId);
        $gruposColumnas = $this->armarGruposColumnas($concbIndex);
        $columnas = $this->aplanarColumnas($gruposColumnas);

        $inicioMes = Carbon::parse($desde)->startOfMonth()->toDateString();
        $rendiciones = $this->cargarRendiciones($empresaId, $inicioMes, $hasta);
        $flashPorFecha = $this->cargarFlashPorFecha($empresaId, $desde, $hasta);

        $pozoAc = $this->pozoAcInicial($empresaId, $inicioMes, $desde, $rendiciones, $concbIndex);

        $dias = [];
        $diasOk = 0;
        $diasDif = 0;
        $totalPendienteCierre = 0;
        $totalGruposPendientes = 0;
        $jornadasConPendientes = 0;
        $totales = $this->filaNumericaVacia($columnas);
        $ultimaVentaAcumulada = 0.0;

        foreach (CarbonPeriod::create($inicioMes, $hasta) as $fecha) {
            $fechaStr = $fecha->toDateString();
            $rendicionesDia = $rendiciones->filter(
                fn (RendicionBingoCaja $r) => CierreRendicionBingoGrupoSupport::fechaDiaDesdeRendicion($r) === $fechaStr,
            );
            $totalesDia = CierreRendicionBingoTotalesSupport::calcular(
                new EloquentCollection($rendicionesDia->values()->all()),
                $empresaId,
                $fechaStr,
            );
            $acum = is_array($totalesDia['acum_concepto'] ?? null) ? $totalesDia['acum_concepto'] : [];
            $recaudacion = round((float) ($totalesDia['tot_recaudacion'] ?? 0), 2);
            $pozoAc = CierreRendicionBingoTotalesSupport::evolSiPozoAc(
                $pozoAc,
                $recaudacion,
                $concbIndex,
                $acum,
            );

            if ($fechaStr < $desde) {
                continue;
            }

            $dia = $this->armarDia(
                $fechaStr,
                $rendicionesDia,
                $totalesDia,
                $flashPorFecha,
                $pozoAc,
                $concbIndex,
                $columnas,
                $tolerancia,
            );

            if (
                $dia['cantidad_rendiciones'] <= 0
                && abs((float) ($dia['valores']['flash_venta'] ?? 0)) <= $tolerancia
                && abs((float) ($dia['valores']['tot_recaudacion'] ?? 0)) <= $tolerancia
            ) {
                continue;
            }

            $dias[] = $dia;
            if (($dia['estado'] ?? '') === 'OK') {
                $diasOk++;
            } elseif (($dia['estado'] ?? '') === 'DIF') {
                $diasDif++;
            }
            $pendDia = (int) ($dia['cantidad_pendiente'] ?? 0);
            $gruposDia = (int) ($dia['cantidad_grupos_pendientes'] ?? 0);
            $totalPendienteCierre += $pendDia;
            $totalGruposPendientes += $gruposDia;
            if ($pendDia > 0) {
                $jornadasConPendientes++;
            }

            $ultimaVentaAcumulada = round((float) ($dia['valores']['tot_vta_acumulada'] ?? 0), 2);
            foreach ($columnas as $col) {
                $tipo = (string) ($col['tipo'] ?? '');
                if (($tipo !== 'numero' && $tipo !== 'entero') || empty($col['suma_total'])) {
                    continue;
                }
                $key = (string) ($col['key'] ?? '');
                $origen = $tipo === 'entero' && isset($dia[$key])
                    ? $dia[$key]
                    : ($dia['valores'][$key] ?? 0);
                $totales[$key] = round(($totales[$key] ?? 0) + (float) $origen, $tipo === 'entero' ? 0 : 2);
            }
        }

        $totales['tot_vta_acumulada'] = $ultimaVentaAcumulada;
        $totales['tot_f_fijo'] = 0.0;

        return [
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'empresa_codigo_anita' => (int) ($empresa->codigo ?? 0),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tolerancia' => $tolerancia,
            'grupos_columnas' => $gruposColumnas,
            'columnas' => $columnas,
            'dias' => $dias,
            'totales' => $totales,
            'resumen' => [
                'total_dias' => count($dias),
                'dias_ok' => $diasOk,
                'dias_dif' => $diasDif,
                'total_pendiente_cierre' => $totalPendienteCierre,
                'total_grupos_pendientes' => $totalGruposPendientes,
                'jornadas_con_pendientes' => $jornadasConPendientes,
            ],
        ];
    }

    /**
     * @return Collection<int, RendicionBingoCaja>
     */
    private function cargarRendiciones(int $empresaId, string $desde, string $hasta): Collection
    {
        return RendicionBingoCaja::query()
            ->with([
                'empresa:id,nombre',
                'asiento:id,numeroasiento,fecha',
            ])
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', '>=', $desde)
            ->whereDate('fecha_jornada', '<=', $hasta)
            ->orderBy('fecha_jornada')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, array{venta: float, resultado: float, cartones: int}>
     */
    private function cargarFlashPorFecha(int $empresaId, string $desde, string $hasta): array
    {
        $out = [];
        FlashCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->get(['fecha', 'bingo_cant_carton', 'bingo_total_venta', 'bingo_resultado'])
            ->each(function (FlashCaja $flash) use (&$out) {
                $fecha = $flash->fecha?->format('Y-m-d');
                if ($fecha === null || $fecha === '') {
                    return;
                }
                $out[$fecha] = [
                    'venta' => round((float) ($flash->bingo_total_venta ?? 0), 2),
                    'resultado' => round((float) ($flash->bingo_resultado ?? 0), 2),
                    'cartones' => (int) ($flash->bingo_cant_carton ?? 0),
                ];
            });

        return $out;
    }

    /**
     * @param  Collection<int, RendicionBingoCaja>  $rendiciones
     * @param  array<int, array<string, mixed>>  $concbIndex
     */
    private function pozoAcInicial(
        int $empresaId,
        string $inicioMes,
        string $desde,
        Collection $rendiciones,
        array $concbIndex,
    ): float {
        $pozoAc = $this->leerPozoAcumAnita($empresaId, $inicioMes);

        if ($inicioMes >= $desde) {
            return $pozoAc;
        }

        $hastaPrevio = Carbon::parse($desde)->subDay()->toDateString();
        foreach (CarbonPeriod::create($inicioMes, $hastaPrevio) as $fecha) {
            $fechaStr = $fecha->toDateString();
            $rendicionesDia = $rendiciones->filter(
                fn (RendicionBingoCaja $r) => CierreRendicionBingoGrupoSupport::fechaDiaDesdeRendicion($r) === $fechaStr,
            );
            $totalesDia = CierreRendicionBingoTotalesSupport::calcular(
                new EloquentCollection($rendicionesDia->values()->all()),
                $empresaId,
                $fechaStr,
            );
            $acum = is_array($totalesDia['acum_concepto'] ?? null) ? $totalesDia['acum_concepto'] : [];
            $pozoAc = CierreRendicionBingoTotalesSupport::evolSiPozoAc(
                $pozoAc,
                round((float) ($totalesDia['tot_recaudacion'] ?? 0), 2),
                $concbIndex,
                $acum,
            );
        }

        return $pozoAc;
    }

    private function leerPozoAcumAnita(int $empresaId, string $fecha): float
    {
        try {
            $reader = new EfeAnitaBridgeReader;
            $filas = $reader->listarPozoacum($empresaId, (int) Carbon::parse($fecha)->format('Ymd'));
        } catch (\Throwable) {
            return 0.0;
        }

        $mejorFecha = 0;
        $importe = 0.0;
        foreach ($filas as $row) {
            $f = (int) ($row->pozoa_fecha ?? 0);
            if ($f <= 0) {
                continue;
            }
            if ($f >= $mejorFecha) {
                $mejorFecha = $f;
                $importe = round((float) ($row->pozoa_importe ?? 0), 2);
            }
        }

        return $importe;
    }

    /**
     * @param  Collection<int, RendicionBingoCaja>  $rendicionesDia
     * @param  array<string, mixed>  $totalesDia
     * @param  array<string, array{venta: float, resultado: float, cartones: int}>  $flashPorFecha
     * @param  array<int, array<string, mixed>>  $concbIndex
     * @param  list<array<string, mixed>>  $columnas
     * @return array<string, mixed>
     */
    private function armarDia(
        string $fechaDia,
        Collection $rendicionesDia,
        array $totalesDia,
        array $flashPorFecha,
        float $pozoAc,
        array $concbIndex,
        array $columnas,
        float $tolerancia,
    ): array {
        $recaudacion = round((float) ($totalesDia['tot_recaudacion'] ?? 0), 2);
        $resultado = round((float) ($totalesDia['tot_resultado_flash'] ?? 0), 2);
        $cartones = (int) ($totalesDia['tot_cartones'] ?? 0);
        $acum = is_array($totalesDia['acum_concepto'] ?? null) ? $totalesDia['acum_concepto'] : [];

        $flashVenta = round((float) ($flashPorFecha[$fechaDia]['venta'] ?? 0), 2);
        $flashResultado = round((float) ($flashPorFecha[$fechaDia]['resultado'] ?? 0), 2);
        $flashCartones = (int) ($flashPorFecha[$fechaDia]['cartones'] ?? 0);

        $difVenta = round($recaudacion - $flashVenta, 2);
        $difResultado = round($resultado - $flashResultado, 2);

        $cantidad = $rendicionesDia->count();
        $cantidadPendiente = $rendicionesDia
            ->filter(fn (RendicionBingoCaja $r) => $r->puedeCerrarContablemente())
            ->count();
        $cantidadCerrada = $rendicionesDia
            ->filter(fn (RendicionBingoCaja $r) => $r->tieneCierreContable())
            ->count();

        $gruposPendientes = CierreRendicionBingoGrupoSupport::agrupar(
            new EloquentCollection(
                $rendicionesDia
                    ->filter(fn (RendicionBingoCaja $r) => $r->puedeCerrarContablemente())
                    ->values()
                    ->all(),
            ),
        );

        $estadoCierre = $cantidadPendiente === 0 && $cantidadCerrada > 0
            ? CierreRendicionBingoGrupoSupport::ESTADO_CERRADA
            : ($cantidadCerrada > 0
                ? CierreRendicionBingoGrupoSupport::ESTADO_PARCIAL
                : CierreRendicionBingoGrupoSupport::ESTADO_PENDIENTE);

        $sinActividad = $cantidad === 0 && abs($flashVenta) <= $tolerancia && abs($recaudacion) <= $tolerancia;
        $estado = $sinActividad
            ? '—'
            : (abs($difVenta) <= $tolerancia && abs($difResultado) <= $tolerancia ? 'OK' : 'DIF');

        $valores = $this->filaNumericaVacia($columnas);
        $valores['tot_recaudacion'] = $recaudacion;
        $valores['flash_venta'] = $flashVenta;
        $valores['dif_venta'] = $difVenta;
        $valores['tot_cartones'] = $cartones;
        $valores['flash_cartones'] = $flashCartones;
        $valores['tot_resultado_flash'] = $resultado;
        $valores['flash_resultado'] = $flashResultado;
        $valores['dif_resultado'] = $difResultado;
        $valores['tot_pozo'] = round((float) ($totalesDia['tot_pozo'] ?? 0), 2);
        $valores['tot_pantalla'] = round((float) ($totalesDia['tot_pantalla'] ?? 0), 2);
        $valores['tot_si_pozo_ac'] = $pozoAc;
        $valores['tot_efectivo'] = round((float) ($totalesDia['tot_efectivo'] ?? 0), 2);
        $valores['tot_dif_caja'] = round((float) ($totalesDia['tot_dif_caja'] ?? 0), 2);
        $valores['tot_f_fijo'] = 0.0;
        $valores['tot_pago_hospital'] = round((float) ($totalesDia['tot_pago_hospital'] ?? 0), 2);
        $valores['tot_vta_acumulada'] = round((float) ($totalesDia['tot_vta_acumulada'] ?? 0), 2);

        foreach ($concbIndex as $concepto => $meta) {
            $tipo = (string) ($meta['tipo_conc'] ?? '');
            $pagado = round((float) ($acum[(int) $concepto]['pagado'] ?? 0), 2);
            $real = round((float) ($acum[(int) $concepto]['real'] ?? 0), 2);
            if (
                $tipo === CierreRendicionBingoConceptoTipos::PREMIO
                || $tipo === CierreRendicionBingoConceptoTipos::BINGO
                || $tipo === CierreRendicionBingoConceptoTipos::PORC_RECAUD
            ) {
                $valores['c'.$concepto.'_real'] = $real;
            } elseif (
                $tipo === CierreRendicionBingoConceptoTipos::PORC_POZO
                || $tipo === CierreRendicionBingoConceptoTipos::ULT_BOLA
            ) {
                $valores['c'.$concepto.'_pagado'] = $pagado;
                $valores['c'.$concepto.'_real'] = $real;
                $valores['c'.$concepto.'_asegurado'] = round($pagado - $real, 2);
            } elseif ($tipo === CierreRendicionBingoConceptoTipos::PAGO) {
                $valores['c'.$concepto.'_real'] = CierreRendicionBingoTotalesSupport::importePagoConcepto(
                    $meta,
                    $acum,
                    $recaudacion,
                );
            }
        }

        return [
            'fecha' => $fechaDia,
            'fecha_fmt' => Carbon::parse($fechaDia)->format('d/m/Y'),
            'cantidad_rendiciones' => $cantidad,
            'cantidad_pendiente' => $cantidadPendiente,
            'cantidad_cerrada' => $cantidadCerrada,
            'cantidad_grupos_pendientes' => count($gruposPendientes),
            'estado_cierre' => $estadoCierre,
            'estado' => $estado,
            'valores' => $valores,
            'rendicion_ids' => $rendicionesDia->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $concbIndex
     * @return list<array{titulo: string, grupo: string, columnas: list<array<string, mixed>>}>
     */
    private function armarGruposColumnas(array $concbIndex): array
    {
        $grupos = [
            $this->grupoSimple('Fecha', 'fecha_fmt', 'texto', 'base', false),
            $this->grupoSimple('Estado', 'estado', 'texto', 'base', false),
            $this->grupoSimple('Rend.', 'cantidad_rendiciones', 'entero', 'base', true),
            $this->grupoSimple('Recaudación', 'tot_recaudacion', 'numero', 'rendicion', true),
            $this->grupoSimple('Flash venta', 'flash_venta', 'numero', 'flash', true),
            $this->grupoSimple('Dif. recaudo', 'dif_venta', 'numero', 'flash', true),
            $this->grupoSimple('Cartones', 'tot_cartones', 'entero', 'rendicion', true),
            $this->grupoSimple('Flash cartones', 'flash_cartones', 'entero', 'flash', true),
            $this->grupoSimple('Resultado', 'tot_resultado_flash', 'numero', 'rendicion', true),
            $this->grupoSimple('Flash resultado', 'flash_resultado', 'numero', 'flash', true),
            $this->grupoSimple('Dif. resultado', 'dif_resultado', 'numero', 'flash', true),
        ];

        foreach ($concbIndex as $meta) {
            $tipo = (string) ($meta['tipo_conc'] ?? '');
            if (
                $tipo !== CierreRendicionBingoConceptoTipos::PREMIO
                && $tipo !== CierreRendicionBingoConceptoTipos::BINGO
                && $tipo !== CierreRendicionBingoConceptoTipos::PORC_RECAUD
            ) {
                continue;
            }
            $grupos[] = $this->grupoSimple(
                $this->tituloConcepto($meta),
                'c'.(int) ($meta['concepto'] ?? 0).'_real',
                'numero',
                'premio',
                true,
            );
        }

        $grupos[] = $this->grupoSimple('Fondos Pozo', 'tot_pozo', 'numero', 'pozo', true, 'Pagos');
        $grupos[] = $this->grupoSimple('Fondos Pantalla', 'tot_pantalla', 'numero', 'pozo', true, 'Pagos');
        $grupos[] = $this->grupoSimple('Evol. SI Pozo AC', 'tot_si_pozo_ac', 'numero', 'pozo', true);

        foreach ($concbIndex as $meta) {
            $tipo = (string) ($meta['tipo_conc'] ?? '');
            if (
                $tipo !== CierreRendicionBingoConceptoTipos::PORC_POZO
                && $tipo !== CierreRendicionBingoConceptoTipos::ULT_BOLA
            ) {
                continue;
            }
            $concepto = (int) ($meta['concepto'] ?? 0);
            $grupos[] = [
                'titulo' => $this->tituloConcepto($meta),
                'grupo' => 'pozo_pct',
                'columnas' => [
                    $this->col('c'.$concepto.'_pagado', 'Pagado', 'numero', 'pozo_pct', true),
                    $this->col('c'.$concepto.'_real', 'Real', 'numero', 'pozo_pct', true),
                    $this->col('c'.$concepto.'_asegurado', 'Asegurado', 'numero', 'pozo_pct', true),
                ],
            ];
        }

        $grupos[] = $this->grupoSimple('Efectivo', 'tot_efectivo', 'numero', 'caja', true);
        $grupos[] = $this->grupoSimple('Dif. Caja', 'tot_dif_caja', 'numero', 'caja', true);
        $grupos[] = $this->grupoSimple('Fondo Fijo', 'tot_f_fijo', 'numero', 'caja', true);

        foreach ($concbIndex as $meta) {
            if (($meta['tipo_conc'] ?? '') !== CierreRendicionBingoConceptoTipos::PAGO) {
                continue;
            }
            $grupos[] = $this->grupoSimple(
                $this->tituloConcepto($meta),
                'c'.(int) ($meta['concepto'] ?? 0).'_real',
                'numero',
                'pago',
                true,
                'Pago',
            );
        }

        $grupos[] = $this->grupoSimple('Hospital', 'tot_pago_hospital', 'numero', 'hospital', true, 'Pago');
        $grupos[] = $this->grupoSimple('Venta acumulada', 'tot_vta_acumulada', 'numero', 'hospital', false);
        $grupos[] = $this->grupoSimple('Cierre', 'estado_cierre', 'texto', 'base', false);

        return $grupos;
    }

    /**
     * @param  list<array{titulo: string, grupo: string, columnas: list<array<string, mixed>>}>  $grupos
     * @return list<array<string, mixed>>
     */
    private function aplanarColumnas(array $grupos): array
    {
        $out = [];
        foreach ($grupos as $grupo) {
            foreach ($grupo['columnas'] as $col) {
                $out[] = $col;
            }
        }

        return $out;
    }

    /**
     * @return array{titulo: string, grupo: string, columnas: list<array<string, mixed>>}
     */
    private function grupoSimple(
        string $titulo,
        string $key,
        string $tipo,
        string $grupo,
        bool $sumaTotal,
        string $subtitulo = '',
    ): array {
        return [
            'titulo' => $titulo,
            'grupo' => $grupo,
            'columnas' => [$this->col($key, $subtitulo, $tipo, $grupo, $sumaTotal)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function col(string $key, string $subtitulo, string $tipo, string $grupo, bool $sumaTotal): array
    {
        return [
            'key' => $key,
            'subtitulo' => $subtitulo,
            'tipo' => $tipo,
            'grupo' => $grupo,
            'suma_total' => $sumaTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function tituloConcepto(array $meta): string
    {
        $desc = trim((string) ($meta['desc'] ?? ''));

        return $desc !== '' ? $desc : ('Concepto '.(int) ($meta['concepto'] ?? 0));
    }

    /**
     * @param  list<array<string, mixed>>  $columnas
     * @return array<string, float|int>
     */
    private function filaNumericaVacia(array $columnas): array
    {
        $out = [];
        foreach ($columnas as $col) {
            $tipo = (string) ($col['tipo'] ?? '');
            if ($tipo === 'numero' || $tipo === 'entero') {
                $out[(string) $col['key']] = 0;
            }
        }

        return $out;
    }
}
