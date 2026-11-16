<?php

namespace App\Support\Configuracion;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calendario semana/mes de próximas corridas del Programador.
 */
final class ModuloAvisoProgramadorCalendarioSupport
{
    private const TZ = 'America/Argentina/Buenos_Aires';

    /**
     * @return array{
     *   modo: string,
     *   titulo: string,
     *   ref: string,
     *   prev: string,
     *   next: string,
     *   url_semana: string,
     *   url_mes: string,
     *   dias: list<array{fecha: string, label: string, es_hoy: bool, fuera: bool, items: list<array<string,mixed>>}>
     * }
     */
    public static function armar(
        string $modo = 'mes',
        ?Carbon $ref = null,
        ?Collection $tipos = null
    ): array {
        $modo = $modo === 'semana' ? 'semana' : 'mes';
        $ref = ($ref ?? now())->copy()->timezone(self::TZ)->startOfDay();
        $mapa = config('modulo_aviso.programados', []);
        $claves = is_array($mapa) ? array_keys($mapa) : [];
        $tipos ??= ModuloAvisoProgramadorCockpitSupport::tiposProgramables($claves);

        if ($modo === 'semana') {
            $inicio = $ref->copy()->startOfWeek(Carbon::MONDAY);
            $fin = $inicio->copy()->addDays(6);
            $titulo = 'Semana '.$inicio->format('d/m').' – '.$fin->format('d/m/Y');
            $prev = $inicio->copy()->subWeek()->toDateString();
            $next = $inicio->copy()->addWeek()->toDateString();
        } else {
            $inicio = $ref->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
            $finMes = $ref->copy()->endOfMonth();
            $fin = $finMes->copy()->endOfWeek(Carbon::SUNDAY);
            $meses = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
                7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];
            $titulo = ($meses[(int) $ref->month] ?? $ref->format('m')).' '.$ref->format('Y');
            $prev = $ref->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
            $next = $ref->copy()->addMonthNoOverflow()->startOfMonth()->toDateString();
        }

        $feriados = ModuloAvisoProgramacionSupport::feriadosYmdEntre(
            $inicio->toDateString(),
            $fin->toDateString()
        );

        /** @var array<string, list<array<string, mixed>>> $porDia */
        $porDia = [];
        $horizonte = max(1, $inicio->diffInDays($fin) + 1);
        foreach ($tipos as $tipo) {
            if (! $tipo->activo || ! $tipo->programacion_habilitada) {
                continue;
            }
            $fechas = ModuloAvisoProgramacionSupport::simularProximosDias(
                $tipo,
                $inicio->copy(),
                $horizonte,
                $feriados
            );
            $hora = ModuloAvisoProgramacionSupport::normalizarHora($tipo->programacion_hora) ?? '';
            foreach ($fechas as $ymd) {
                if ($ymd < $inicio->toDateString() || $ymd > $fin->toDateString()) {
                    continue;
                }
                $porDia[$ymd][] = [
                    'tipo_id' => $tipo->id,
                    'clave' => $tipo->claveCompleta(),
                    'nombre' => $tipo->nombre,
                    'hora' => $hora,
                    'pausada' => (bool) ($tipo->programacion_pausada ?? false),
                    'url' => url('configuracion/modulo-aviso/'.$tipo->id.'/editar').'?tab=ejecucion',
                ];
            }
        }

        $dias = [];
        $cursor = $inicio->copy();
        $hoy = now()->timezone(self::TZ)->toDateString();
        $mesRef = $ref->format('Y-m');
        while ($cursor->lte($fin)) {
            $ymd = $cursor->toDateString();
            $items = $porDia[$ymd] ?? [];
            usort($items, static fn ($a, $b) => strcmp($a['hora'], $b['hora']));
            $dias[] = [
                'fecha' => $ymd,
                'label' => $cursor->format('d'),
                'dow' => ModuloAvisoProgramacionSupport::DIAS[(int) $cursor->dayOfWeekIso] ?? '',
                'es_hoy' => $ymd === $hoy,
                'fuera' => $modo === 'mes' && $cursor->format('Y-m') !== $mesRef,
                'es_feriado' => in_array($ymd, $feriados, true),
                'items' => $items,
            ];
            $cursor->addDay();
        }

        $baseQuery = ['solo_programables' => 1, 'cal_modo' => $modo];

        return [
            'modo' => $modo,
            'titulo' => $titulo,
            'ref' => $ref->toDateString(),
            'prev' => $prev,
            'next' => $next,
            'url_semana' => url('configuracion/modulo-aviso').'?'.http_build_query($baseQuery + ['cal_modo' => 'semana', 'cal_ref' => $ref->toDateString()]),
            'url_mes' => url('configuracion/modulo-aviso').'?'.http_build_query($baseQuery + ['cal_modo' => 'mes', 'cal_ref' => $ref->toDateString()]),
            'url_prev' => url('configuracion/modulo-aviso').'?'.http_build_query($baseQuery + ['cal_ref' => $prev]),
            'url_next' => url('configuracion/modulo-aviso').'?'.http_build_query($baseQuery + ['cal_ref' => $next]),
            'dias' => $dias,
        ];
    }
}
