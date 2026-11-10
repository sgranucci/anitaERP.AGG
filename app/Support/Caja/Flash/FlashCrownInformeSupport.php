<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashCrown;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownResultadoOnline;
use App\Models\Configuracion\Sala;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Informe de flash que dispara el usuario: detalle del día, o el cuadro
 * Gaming Revenues (día, mismo día de la semana del año anterior, MTD y MTD anterior).
 */
final class FlashCrownInformeSupport
{
    public const FORMATO_COMPLETO = 'completa';

    public const FORMATO_GAMING = 'gaming';

    /** Orden del mail de casinos (el resto de las salas va al final). */
    private const ORDEN_SALA = ['CIP', 'GRO', 'VIE', 'CAT', 'CHO', 'VRE', 'LGR', 'EFA', 'CSA'];

    /** @var array<string, FlashCrown|null> */
    private array $flashPorClave = [];

    /** @var array<string, true> */
    private array $conFuente = [];

    /**
     * @return array<string, string>
     */
    public static function formatos(): array
    {
        return [
            self::FORMATO_COMPLETO => 'Flash completo',
            self::FORMATO_GAMING => 'Gaming revenues',
        ];
    }

    public static function normalizarFormato(?string $formato): string
    {
        return trim((string) $formato) === self::FORMATO_GAMING
            ? self::FORMATO_GAMING
            : self::FORMATO_COMPLETO;
    }

    /**
     * @return array<string, mixed>
     */
    public function armar(string $formato, Carbon $fecha): array
    {
        $fecha = $fecha->copy()->startOfDay();
        $formato = self::normalizarFormato($formato);
        $salas = $this->salasEnOrden();

        if ($formato === self::FORMATO_GAMING) {
            return $this->armarGaming($fecha, $salas);
        }

        return $this->armarCompleto($fecha, $salas);
    }

    /**
     * @param  Collection<int, Sala>  $salas
     * @return array<string, mixed>
     */
    private function armarCompleto(Carbon $fecha, Collection $salas): array
    {
        $this->precargar($fecha, $fecha);
        $filas = [];
        foreach ($salas as $sala) {
            $base = $this->baseSalaFecha((int) $sala->id, $fecha->toDateString());
            $filas[] = [
                'codigo' => (string) $sala->codigo,
                'nombre' => (string) $sala->nombre,
                'att' => (int) ($base['att'] ?? 0),
                'cashdrop' => (float) ($base['cashdrop'] ?? 0),
                'coin_in' => (float) ($base['coin_in'] ?? 0),
                'slot_r' => (float) ($base['slot_r'] ?? 0),
                'ol_win' => (float) ($base['ol_win'] ?? 0),
                'bs_win' => (float) ($base['bs_win'] ?? 0),
                'ayb' => (float) ($base['ayb'] ?? 0),
                'hotel' => (float) ($base['hotel'] ?? 0),
                'metricas' => FlashCrownCalculoSupport::metricasDerivadas($base),
                'validado' => (bool) ($base['_validado'] ?? false),
            ];
        }

        return [
            'formato' => self::FORMATO_COMPLETO,
            'fecha' => $fecha,
            'filas' => $filas,
        ];
    }

    /**
     * @param  Collection<int, Sala>  $salas
     * @return array<string, mixed>
     */
    private function armarGaming(Carbon $fecha, Collection $salas): array
    {
        $comparable = FlashCrownFechaComparableSupport::mismoDiaSemanaAnioAnterior($fecha);
        $mtdDesde = $fecha->copy()->startOfMonth();
        $mtdAnteriorHasta = $this->mismoDiaDeMes($fecha, $fecha->year - 1);
        $mtdAnteriorDesde = $mtdAnteriorHasta->copy()->startOfMonth();

        $this->precargar($mtdDesde, $fecha);
        $this->precargar($mtdAnteriorDesde, $mtdAnteriorHasta);
        $this->precargar($comparable, $comparable);

        $casinos = [];
        foreach ($salas as $sala) {
            $salaId = (int) $sala->id;
            $dia = $this->juego($this->baseSalaFecha($salaId, $fecha->toDateString()));
            $anterior = $this->juego($this->baseSalaFecha($salaId, $comparable->toDateString()));
            $mtd = $this->sumarJuego($salaId, $mtdDesde, $fecha);
            $mtdAnterior = $this->sumarJuego($salaId, $mtdAnteriorDesde, $mtdAnteriorHasta);
            $casinos[] = [
                'titulo' => 'Casino: '.$this->rotuloSala($sala).' JUEGO',
                'filas' => $this->filasJuego($dia, $anterior, $mtd, $mtdAnterior),
            ];
        }

        return [
            'formato' => self::FORMATO_GAMING,
            'fecha' => $fecha,
            'fecha_comparable' => $comparable,
            'columnas' => [
                [
                    'clave' => 'dia',
                    'titulo' => $this->diaSemana($fecha),
                    'subtitulo' => $fecha->format('j/n/Y'),
                ],
                [
                    'clave' => 'anterior',
                    'titulo' => $this->diaSemana($comparable),
                    'subtitulo' => $comparable->format('j/n/Y'),
                ],
                [
                    'clave' => 'mtd',
                    'titulo' => 'Ac. '.$this->mesCorto($fecha).' '.$fecha->year,
                    'subtitulo' => '',
                ],
                [
                    'clave' => 'mtd_anterior',
                    'titulo' => 'Ac. '.$this->mesCorto($mtdAnteriorHasta).' '.$mtdAnteriorHasta->year,
                    'subtitulo' => '',
                ],
            ],
            'casinos' => $casinos,
        ];
    }

    /**
     * Mismo número de día del mes en otro año. Si no existe (29/2), el último día del mes.
     */
    private function mismoDiaDeMes(Carbon $fecha, int $anio): Carbon
    {
        $mes = $fecha->month;
        $dia = min($fecha->day, Carbon::create($anio, $mes, 1)->daysInMonth);

        return Carbon::create($anio, $mes, $dia)->startOfDay();
    }

    /**
     * @return array{mesas: float, slots: float, ayb: float, att: int}
     */
    private function sumarJuego(int $salaId, Carbon $desde, Carbon $hasta): array
    {
        $total = ['mesas' => 0.0, 'slots' => 0.0, 'ayb' => 0.0, 'att' => 0];
        $cursor = $desde->copy()->startOfDay();
        $fin = $hasta->copy()->startOfDay();
        while ($cursor->lte($fin)) {
            $juego = $this->juego($this->baseSalaFecha($salaId, $cursor->toDateString()));
            $total['mesas'] += $juego['mesas'];
            $total['slots'] += $juego['slots'];
            $total['ayb'] += $juego['ayb'];
            $total['att'] += $juego['att'];
            $cursor->addDay();
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array{mesas: float, slots: float, ayb: float, att: int}
     */
    private function juego(array $base): array
    {
        $m = FlashCrownCalculoSupport::metricasDerivadas($base);
        $mesas = (float) $m['win_mesas'];
        $slots = round((float) $m['gaming'] - $mesas, 2);

        return [
            'mesas' => $mesas,
            'slots' => $slots,
            'ayb' => round((float) ($base['ayb'] ?? 0), 2),
            'att' => (int) ($base['att'] ?? 0),
        ];
    }

    /**
     * @param  array{mesas: float, slots: float, ayb: float, att: int}  $dia
     * @param  array{mesas: float, slots: float, ayb: float, att: int}  $anterior
     * @param  array{mesas: float, slots: float, ayb: float, att: int}  $mtd
     * @param  array{mesas: float, slots: float, ayb: float, att: int}  $mtdAnterior
     * @return list<array<string, mixed>>
     */
    private function filasJuego(array $dia, array $anterior, array $mtd, array $mtdAnterior): array
    {
        $linea = static function (string $etiqueta, string $campo, bool $entero) use ($dia, $anterior, $mtd, $mtdAnterior): array {
            return [
                'etiqueta' => $etiqueta,
                'dia' => $dia[$campo],
                'anterior' => $anterior[$campo],
                'mtd' => $mtd[$campo],
                'mtd_anterior' => $mtdAnterior[$campo],
                'entero' => $entero,
                'es_total' => false,
            ];
        };

        return [
            [
                'etiqueta' => 'TOTAL',
                'dia' => round($dia['mesas'] + $dia['slots'] + $dia['ayb'], 2),
                'anterior' => round($anterior['mesas'] + $anterior['slots'] + $anterior['ayb'], 2),
                'mtd' => round($mtd['mesas'] + $mtd['slots'] + $mtd['ayb'], 2),
                'mtd_anterior' => round($mtdAnterior['mesas'] + $mtdAnterior['slots'] + $mtdAnterior['ayb'], 2),
                'entero' => false,
                'es_total' => true,
            ],
            $linea('Mesas', 'mesas', false),
            $linea('Slots', 'slots', false),
            $linea('A&B', 'ayb', false),
            $linea('Att', 'att', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSalaFecha(int $salaId, string $fechaYmd): array
    {
        $clave = $salaId.'|'.$fechaYmd;
        $stored = $this->flashPorClave[$clave] ?? null;
        if ($stored instanceof FlashCrown && ($stored->estaValidado() || ! isset($this->conFuente[$clave]))) {
            $base = $stored->toArray();
            $base['_validado'] = $stored->estaValidado();

            return $base;
        }

        if (! isset($this->conFuente[$clave])) {
            return $this->vacio($salaId, $fechaYmd);
        }

        $calc = FlashCrownCalculoSupport::deSalaFecha($salaId, $fechaYmd);
        $overrides = $stored instanceof FlashCrown && is_array($stored->overrides_json)
            ? $stored->overrides_json
            : [];
        if ($overrides !== []) {
            $calc = FlashCrownCalculoSupport::aplicarOverrides($calc, $overrides);
        }
        $calc['_validado'] = false;

        return $calc;
    }

    /**
     * @return array<string, mixed>
     */
    private function vacio(int $salaId, string $fechaYmd): array
    {
        $vacio = FlashCrownCalculoSupport::estructuraVacia();
        $vacio['sala_id'] = $salaId;
        $vacio['fecha'] = $fechaYmd;
        $vacio['_validado'] = false;

        return $vacio;
    }

    private function precargar(Carbon $desde, Carbon $hasta): void
    {
        $desdeYmd = $desde->toDateString();
        $hastaYmd = $hasta->toDateString();

        $flashes = FlashCrown::query()
            ->whereDate('fecha', '>=', $desdeYmd)
            ->whereDate('fecha', '<=', $hastaYmd)
            ->get();
        foreach ($flashes as $flash) {
            $this->flashPorClave[(int) $flash->sala_id.'|'.$flash->fecha->format('Y-m-d')] = $flash;
        }

        $jornadas = TesoreriaCrownJornada::query()
            ->whereDate('fecha', '>=', $desdeYmd)
            ->whereDate('fecha', '<=', $hastaYmd)
            ->get(['sala_id', 'fecha']);
        foreach ($jornadas as $jornada) {
            $fecha = $jornada->fecha instanceof Carbon
                ? $jornada->fecha->format('Y-m-d')
                : Carbon::parse((string) $jornada->fecha)->format('Y-m-d');
            $this->conFuente[(int) $jornada->sala_id.'|'.$fecha] = true;
        }

        $online = TesoreriaCrownResultadoOnline::query()
            ->whereDate('fecha', '>=', $desdeYmd)
            ->whereDate('fecha', '<=', $hastaYmd)
            ->get(['sala_id', 'fecha']);
        foreach ($online as $fila) {
            $fecha = $fila->fecha instanceof Carbon
                ? $fila->fecha->format('Y-m-d')
                : Carbon::parse((string) $fila->fecha)->format('Y-m-d');
            $this->conFuente[(int) $fila->sala_id.'|'.$fecha] = true;
        }
    }

    /**
     * @return Collection<int, Sala>
     */
    private function salasEnOrden(): Collection
    {
        $orden = array_flip(self::ORDEN_SALA);

        return TesoreriaCrownSalaSupport::salas()
            ->sortBy(function (Sala $sala) use ($orden): array {
                $codigo = strtoupper((string) $sala->codigo);

                return [$orden[$codigo] ?? 1000, (string) $sala->nombre];
            })
            ->values();
    }

    private function rotuloSala(Sala $sala): string
    {
        $nombre = mb_strtoupper(str_replace(' ', '', (string) $sala->nombre), 'UTF-8');

        return $nombre !== '' ? $nombre : strtoupper((string) $sala->codigo);
    }

    private function diaSemana(Carbon $fecha): string
    {
        return [
            Carbon::SUNDAY => 'Domingo',
            Carbon::MONDAY => 'Lunes',
            Carbon::TUESDAY => 'Martes',
            Carbon::WEDNESDAY => 'Miércoles',
            Carbon::THURSDAY => 'Jueves',
            Carbon::FRIDAY => 'Viernes',
            Carbon::SATURDAY => 'Sábado',
        ][$fecha->dayOfWeek];
    }

    private function mesCorto(Carbon $fecha): string
    {
        return [
            1 => 'Ene.', 2 => 'Feb.', 3 => 'Mar.', 4 => 'Abr.',
            5 => 'May.', 6 => 'Jun.', 7 => 'Jul.', 8 => 'Ago.',
            9 => 'Sep.', 10 => 'Oct.', 11 => 'Nov.', 12 => 'Dic.',
        ][$fecha->month];
    }
}
