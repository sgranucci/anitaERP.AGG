<?php

namespace App\Support\Contable;

use App\Models\Caja\Flash\FlashCaja;
use App\Support\Caja\Flash\FlashCajaLFlashCalculoSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Flash unificado Contaduría + Impuestos: recorte diario del flash principal,
 * empresas en columnas (slots/ruletas, win, bingo, F&B, parking, vending y tilde de cerrado).
 */
final class FlashContableReporteSupport
{
    /** @var list<string> */
    public const METRICAS = [
        'q_pos_slots',
        'win_slots',
        'q_pos_ruletas',
        'win_ruletas',
        'q_posiciones',
        'win_electronico',
        'win_financiero',
        'cartones_bingo',
        'ventas_bingo',
        'net_win_bingo',
        'ventas_ayb',
        'ventas_parking',
        'ventas_vending',
        'flash_cerrado',
    ];

    /** @var list<string> */
    public const METRICAS_ENTERO = [
        'q_pos_slots',
        'q_pos_ruletas',
        'q_posiciones',
        'cartones_bingo',
    ];

    /** @var array<string, string> */
    public const ETIQUETAS = [
        'q_pos_slots' => 'Q Pos Slots',
        'win_slots' => 'Win Slots',
        'q_pos_ruletas' => 'Q Pos Ruletas',
        'win_ruletas' => 'Win Ruletas',
        'q_posiciones' => 'Q Posiciones',
        'win_electronico' => 'Win Electrónico',
        'win_financiero' => 'Win Financiero',
        'cartones_bingo' => 'N Cartones Bingo',
        'ventas_bingo' => 'Ventas Bingo',
        'net_win_bingo' => 'Net Win Bingo',
        'ventas_ayb' => 'Ventas F&B',
        'ventas_parking' => 'Ventas Parking',
        'ventas_vending' => 'Ventas Vending',
        'flash_cerrado' => 'Cerrado',
    ];

    /** @var array<int, string> */
    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * @param  list<int>  $empresaIds
     * @param  array<int, string>  $nombresPorId
     * @return array<string, mixed>
     */
    public static function armar(array $empresaIds, int $anio, int $mes, array $nombresPorId = []): array
    {
        $ids = self::normalizarEmpresaIds($empresaIds);
        $desde = Carbon::create($anio, $mes, 1)->startOfDay();
        $hasta = $desde->copy()->endOfMonth()->startOfDay();
        $flashes = self::cargarRango($ids, $desde->format('Y-m-d'), $hasta->format('Y-m-d'));

        return self::armarDesdeFlashes($flashes, $ids, $nombresPorId, $desde, $hasta);
    }

    /**
     * @param  Collection<int, FlashCaja>  $flashes
     * @param  list<int>  $empresaIds
     * @param  array<int, string>  $nombresPorId
     * @return array<string, mixed>
     */
    public static function armarDesdeFlashes(
        Collection $flashes,
        array $empresaIds,
        array $nombresPorId,
        Carbon $desde,
        Carbon $hasta,
    ): array {
        $ids = self::normalizarEmpresaIds($empresaIds);
        $porFechaEmpresa = $flashes->keyBy(
            fn (FlashCaja $f) => ($f->fecha?->format('Y-m-d') ?? '').'|'.(int) $f->empresa_id
        );

        $empresas = [];
        foreach ($ids as $empresaId) {
            $nombre = trim((string) ($nombresPorId[$empresaId] ?? ''));
            if ($nombre === '') {
                $nombre = (string) ($porFechaEmpresa->first(
                    fn (FlashCaja $f) => (int) $f->empresa_id === $empresaId
                )?->empresa?->nombre ?? ('#'.$empresaId));
            }
            $empresas[] = [
                'id' => $empresaId,
                'nombre' => $nombre,
            ];
        }

        $filas = [];
        $cursor = $desde->copy()->startOfDay();
        $fin = $hasta->copy()->startOfDay();
        while ($cursor->lte($fin)) {
            $ymd = $cursor->format('Y-m-d');
            $porEmpresa = [];
            $tieneDato = false;
            foreach ($ids as $empresaId) {
                /** @var FlashCaja|null $flash */
                $flash = $porFechaEmpresa->get($ymd.'|'.$empresaId);
                if ($flash instanceof FlashCaja) {
                    $porEmpresa[$empresaId] = self::metricasDesdeFlash($flash);
                    $tieneDato = true;
                } else {
                    $porEmpresa[$empresaId] = self::metricasVacias();
                }
            }
            $filas[] = [
                'fecha' => $cursor->format('d/m/Y'),
                'fecha_iso' => $ymd,
                'empresas' => $porEmpresa,
                'tiene_dato' => $tieneDato,
            ];
            $cursor->addDay();
        }

        $diasConDato = collect($filas)->where('tiene_dato', true)->count();

        return [
            'titulo' => 'Flash — Contabilidad e Impuestos',
            'empresas' => $empresas,
            'empresa_ids' => $ids,
            'filas' => $filas,
            'totales' => self::totales($filas, $ids),
            'cantidad_dias' => $diasConDato,
            'fecha_desde' => $desde->format('Y-m-d'),
            'fecha_hasta' => $hasta->format('Y-m-d'),
            'periodo' => self::etiquetaMes((int) $desde->format('Y'), (int) $desde->format('n')),
            'metricas' => self::METRICAS,
            'etiquetas' => self::ETIQUETAS,
            'cantidad_columnas' => 1 + (count($ids) * count(self::METRICAS)),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    public static function metricasDesdeFlash(FlashCaja $flash): array
    {
        $m = FlashCajaLFlashCalculoSupport::metricasDesdeFlash($flash);

        return [
            'q_pos_slots' => (int) ($m['slot_units'] ?? 0),
            'win_slots' => (float) ($m['slot_ol_win'] ?? 0),
            'q_pos_ruletas' => (int) ($m['rul_units'] ?? 0),
            'win_ruletas' => (float) ($m['rul_ol_win'] ?? 0),
            'q_posiciones' => (int) ($m['el_positions'] ?? 0),
            'win_electronico' => (float) ($m['win_online'] ?? 0),
            'win_financiero' => (float) ($m['win_financial'] ?? 0),
            'cartones_bingo' => (int) ($m['bingo_carton'] ?? 0),
            'ventas_bingo' => (float) ($m['bingo_venta'] ?? 0),
            'net_win_bingo' => (float) ($m['bingo_win'] ?? 0),
            'ventas_ayb' => (float) ($m['ayb'] ?? 0),
            'ventas_parking' => (float) ($m['estac'] ?? 0),
            'ventas_vending' => (float) ($m['vending'] ?? 0),
            'flash_cerrado' => (bool) ($flash->validado ?? false),
            'tiene_flash' => true,
        ];
    }

    /**
     * @return array<string, float|int>
     */
    public static function metricasVacias(): array
    {
        return [
            'q_pos_slots' => 0,
            'win_slots' => 0.0,
            'q_pos_ruletas' => 0,
            'win_ruletas' => 0.0,
            'q_posiciones' => 0,
            'win_electronico' => 0.0,
            'win_financiero' => 0.0,
            'cartones_bingo' => 0,
            'ventas_bingo' => 0.0,
            'net_win_bingo' => 0.0,
            'ventas_ayb' => 0.0,
            'ventas_parking' => 0.0,
            'ventas_vending' => 0.0,
            'flash_cerrado' => false,
            'tiene_flash' => false,
        ];
    }

    public static function esEntero(string $clave): bool
    {
        return in_array($clave, self::METRICAS_ENTERO, true);
    }

    public static function esTilde(string $clave): bool
    {
        return $clave === 'flash_cerrado';
    }

    public static function etiquetaMes(int $anio, int $mes): string
    {
        $nombre = self::MESES[$mes] ?? str_pad((string) $mes, 2, '0', STR_PAD_LEFT);

        return $nombre.' '.$anio;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return Collection<int, FlashCaja>
     */
    public static function cargarRango(array $empresaIds, string $desde, string $hasta): Collection
    {
        $ids = self::normalizarEmpresaIds($empresaIds);
        if ($ids === []) {
            return collect();
        }

        return FlashCaja::query()
            ->whereIn('empresa_id', $ids)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->with('empresa')
            ->orderBy('fecha')
            ->orderBy('empresa_id')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<int>  $empresaIds
     * @return array<int, array<string, float|int>>
     */
    private static function totales(array $filas, array $empresaIds): array
    {
        $out = [];
        foreach ($empresaIds as $empresaId) {
            $acum = self::metricasVacias();
            $sumaEntero = array_fill_keys(self::METRICAS_ENTERO, 0);
            $diasEntero = array_fill_keys(self::METRICAS_ENTERO, 0);
            $diasConDato = 0;
            $diasCerrado = 0;
            foreach ($filas as $fila) {
                $m = $fila['empresas'][$empresaId] ?? self::metricasVacias();
                foreach (self::METRICAS as $clave) {
                    if (self::esTilde($clave)) {
                        continue;
                    }
                    if (self::esEntero($clave)) {
                        $valor = (int) ($m[$clave] ?? 0);
                        if ($valor > 0) {
                            $sumaEntero[$clave] += $valor;
                            $diasEntero[$clave]++;
                        }
                        continue;
                    }
                    $acum[$clave] = round((float) $acum[$clave] + (float) ($m[$clave] ?? 0), 2);
                }
                if (! empty($m['tiene_flash'])) {
                    $diasConDato++;
                }
                if (! empty($m['flash_cerrado'])) {
                    $diasCerrado++;
                }
            }
            foreach (self::METRICAS_ENTERO as $clave) {
                $acum[$clave] = $diasEntero[$clave] > 0
                    ? (int) round($sumaEntero[$clave] / $diasEntero[$clave])
                    : 0;
            }
            $acum['flash_cerrado'] = $diasCerrado;
            $acum['flash_cerrado_texto'] = $diasCerrado.'/'.$diasConDato;
            $out[$empresaId] = $acum;
        }

        return $out;
    }

    /**
     * @param  list<int|string>  $empresaIds
     * @return list<int>
     */
    private static function normalizarEmpresaIds(array $empresaIds): array
    {
        return collect($empresaIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
