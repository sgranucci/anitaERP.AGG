<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Feriado;
use App\Models\Configuracion\ModuloAvisoTipo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Decide si un aviso del módulo general corresponde enviarse ahora.
 * Día ISO: 1 = lunes … 7 = domingo. La hora es HH:MM.
 * Opcional: cada N semanas (ancla), fecha fin, día del mes, skip feriados, pausa.
 */
class ModuloAvisoProgramacionSupport
{
    /** @var array<int, string> */
    public const DIAS = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mié',
        4 => 'Jue',
        5 => 'Vie',
        6 => 'Sáb',
        7 => 'Dom',
    ];

    public static function corresponde(
        bool $habilitada,
        ?string $hora,
        ?string $dias,
        ?string $ultima,
        Carbon $ahora,
        int $cadaN = 1,
        ?string $hasta = null,
        ?string $ancla = null,
        bool $pausada = false,
        ?int $diaMes = null,
        bool $skipFeriados = false,
        ?array $feriadosYmd = null,
        ?string $horaHasta = null
    ): bool {
        if ($pausada || ! $habilitada) {
            return false;
        }

        $hora = self::normalizarHora($hora);
        if ($hora === null) {
            return false;
        }
        if ($hasta !== null && $hasta !== '' && $ahora->toDateString() > substr($hasta, 0, 10)) {
            return false;
        }
        if ($ultima !== null && substr($ultima, 0, 10) === $ahora->toDateString()) {
            return false;
        }
        if ($skipFeriados && self::esFeriado($ahora->toDateString(), $feriadosYmd)) {
            return false;
        }

        $diaMes = $diaMes !== null && $diaMes >= 1 && $diaMes <= 31 ? $diaMes : null;
        if ($diaMes !== null) {
            $diaObjetivo = min($diaMes, (int) $ahora->daysInMonth);
            if ((int) $ahora->day !== $diaObjetivo) {
                return false;
            }
        } else {
            $lista = self::diasLista($dias);
            if ($lista === []) {
                return false;
            }
            if (! in_array((int) $ahora->dayOfWeekIso, $lista, true)) {
                return false;
            }
            $cadaN = max(1, $cadaN);
            if ($cadaN > 1 && ! self::caeEnCicloSemanal($ahora, $cadaN, $ancla, $ultima)) {
                return false;
            }
        }

        $ahoraHi = $ahora->format('H:i');
        if ($ahoraHi < $hora) {
            return false;
        }
        $horaHasta = self::normalizarHora($horaHasta);
        if ($horaHasta !== null && $ahoraHi > $horaHasta) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<string>|null  $feriadosYmd
     */
    public static function correspondeTipo(
        ModuloAvisoTipo $tipo,
        Carbon $ahora,
        ?array $feriadosYmd = null,
        bool $ignorarUltima = false
    ): bool {
        $ultima = null;
        if (! $ignorarUltima && $tipo->programacion_ultima_en) {
            $ultima = $tipo->programacion_ultima_en instanceof Carbon
                ? $tipo->programacion_ultima_en->toDateString()
                : substr((string) $tipo->programacion_ultima_en, 0, 10);
        }

        $hasta = $tipo->programacion_hasta;
        $ancla = $tipo->programacion_ancla;

        return self::corresponde(
            (bool) $tipo->programacion_habilitada,
            $tipo->programacion_hora,
            $tipo->programacion_dias,
            $ultima,
            $ahora,
            (int) ($tipo->programacion_cada_n ?? 1),
            $hasta ? (string) ($hasta instanceof Carbon ? $hasta->toDateString() : substr((string) $hasta, 0, 10)) : null,
            $ancla ? (string) ($ancla instanceof Carbon ? $ancla->toDateString() : substr((string) $ancla, 0, 10)) : null,
            (bool) ($tipo->programacion_pausada ?? false),
            isset($tipo->programacion_dia_mes) ? (int) $tipo->programacion_dia_mes : null,
            (bool) ($tipo->programacion_skip_feriados ?? false),
            $feriadosYmd,
            isset($tipo->programacion_hora_hasta) ? (string) $tipo->programacion_hora_hasta : null
        );
    }

    /**
     * Próxima fecha/hora (TZ del $desde) en la que correspondería, o null.
     *
     * @param  list<string>|null  $feriadosYmd
     */
    public static function proximaCorrida(
        ModuloAvisoTipo $tipo,
        Carbon $desde,
        int $diasHorizonte = 45,
        ?array $feriadosYmd = null
    ): ?Carbon {
        if (! $tipo->programacion_habilitada || ($tipo->programacion_pausada ?? false)) {
            return null;
        }
        $hora = self::normalizarHora($tipo->programacion_hora);
        if ($hora === null) {
            return null;
        }

        $cursor = $desde->copy();
        for ($i = 0; $i < max(1, $diasHorizonte); $i++) {
            $dia = $cursor->copy()->addDays($i)->setTime(
                (int) substr($hora, 0, 2),
                (int) substr($hora, 3, 2),
                0
            );
            // Sin “última” para proyectar futuro (la del tipo bloquearía hoy si ya corrió).
            $ok = self::corresponde(
                true,
                $hora,
                $tipo->programacion_dias,
                $i === 0 && $tipo->programacion_ultima_en
                    ? $tipo->programacion_ultima_en->toDateString()
                    : null,
                $dia,
                (int) ($tipo->programacion_cada_n ?? 1),
                $tipo->programacion_hasta ? $tipo->programacion_hasta->toDateString() : null,
                $tipo->programacion_ancla ? $tipo->programacion_ancla->toDateString() : null,
                false,
                isset($tipo->programacion_dia_mes) ? (int) $tipo->programacion_dia_mes : null,
                (bool) ($tipo->programacion_skip_feriados ?? false),
                $feriadosYmd,
                isset($tipo->programacion_hora_hasta) ? (string) $tipo->programacion_hora_hasta : null
            );
            if ($ok && $dia->gte($desde)) {
                return $dia;
            }
        }

        return null;
    }

    /**
     * Simula qué días del horizonte dispararían (sin marcar última).
     *
     * @param  list<string>|null  $feriadosYmd
     * @return list<string> Y-m-d
     */
    public static function simularProximosDias(
        ModuloAvisoTipo $tipo,
        Carbon $desde,
        int $diasHorizonte = 14,
        ?array $feriadosYmd = null
    ): array {
        $hora = self::normalizarHora($tipo->programacion_hora) ?? '08:00';
        $fechas = [];
        for ($i = 0; $i < max(1, $diasHorizonte); $i++) {
            $dia = $desde->copy()->addDays($i)->setTime(
                (int) substr($hora, 0, 2),
                (int) substr($hora, 3, 2),
                0
            );
            if (self::corresponde(
                (bool) $tipo->programacion_habilitada,
                $hora,
                $tipo->programacion_dias,
                null,
                $dia,
                (int) ($tipo->programacion_cada_n ?? 1),
                $tipo->programacion_hasta ? $tipo->programacion_hasta->toDateString() : null,
                $tipo->programacion_ancla ? $tipo->programacion_ancla->toDateString() : null,
                (bool) ($tipo->programacion_pausada ?? false),
                isset($tipo->programacion_dia_mes) ? (int) $tipo->programacion_dia_mes : null,
                (bool) ($tipo->programacion_skip_feriados ?? false),
                $feriadosYmd,
                isset($tipo->programacion_hora_hasta) ? (string) $tipo->programacion_hora_hasta : null
            )) {
                $fechas[] = $dia->toDateString();
            }
        }

        return $fechas;
    }

    /**
     * @return list<string>
     */
    public static function feriadosYmdEntre(string $desdeYmd, string $hastaYmd): array
    {
        if (! Schema::hasTable('feriado')) {
            return [];
        }

        return Feriado::query()
            ->whereBetween('fecha', [$desdeYmd, $hastaYmd])
            ->orderBy('fecha')
            ->pluck('fecha')
            ->map(static fn ($f) => substr((string) $f, 0, 10))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>|null  $feriadosYmd
     */
    public static function esFeriado(string $ymd, ?array $feriadosYmd = null): bool
    {
        $ymd = substr($ymd, 0, 10);
        if ($feriadosYmd !== null) {
            return in_array($ymd, $feriadosYmd, true);
        }
        if (! Schema::hasTable('feriado')) {
            return false;
        }

        return Feriado::query()->where('fecha', $ymd)->exists();
    }

    /**
     * Ciclo de N semanas desde ancla (o desde última corrida / semana actual).
     */
    public static function caeEnCicloSemanal(
        Carbon $ahora,
        int $cadaN,
        ?string $ancla,
        ?string $ultima
    ): bool {
        $cadaN = max(1, $cadaN);
        if ($cadaN === 1) {
            return true;
        }

        $ref = null;
        if ($ancla !== null && $ancla !== '') {
            $ref = Carbon::parse(substr($ancla, 0, 10))->startOfWeek(Carbon::MONDAY);
        } elseif ($ultima !== null && $ultima !== '') {
            $ref = Carbon::parse(substr($ultima, 0, 10))->startOfWeek(Carbon::MONDAY);
        } else {
            return true;
        }

        $semanaActual = $ahora->copy()->startOfWeek(Carbon::MONDAY);
        $semanas = (int) $ref->diffInWeeks($semanaActual, false);
        if ($semanas < 0) {
            return false;
        }

        return ($semanas % $cadaN) === 0;
    }

    public static function normalizarHora(?string $hora): ?string
    {
        $hora = trim((string) $hora);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m) !== 1) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $min);
    }

    /**
     * @return list<int>
     */
    public static function diasLista(?string $dias): array
    {
        if ($dias === null || trim($dias) === '') {
            return [];
        }
        $lista = [];
        foreach (explode(',', $dias) as $dia) {
            $n = (int) trim($dia);
            if ($n >= 1 && $n <= 7) {
                $lista[$n] = $n;
            }
        }
        $lista = array_values($lista);
        sort($lista);

        return $lista;
    }

    /**
     * @param  list<int>|null  $dias
     */
    public static function diasTexto(?array $dias): ?string
    {
        $lista = [];
        foreach ($dias ?? [] as $dia) {
            $n = (int) $dia;
            if ($n >= 1 && $n <= 7) {
                $lista[$n] = $n;
            }
        }
        if ($lista === []) {
            return null;
        }
        $lista = array_values($lista);
        sort($lista);

        return implode(',', $lista);
    }

    public static function etiqueta(
        ?string $hora,
        ?string $dias,
        bool $habilitada,
        int $cadaN = 1,
        ?string $hasta = null,
        ?int $diaMes = null,
        bool $pausada = false,
        bool $skipFeriados = false,
        ?string $horaHasta = null
    ): string {
        if ($pausada) {
            return 'Pausada';
        }
        if (! $habilitada) {
            return 'Sin programar';
        }
        $hora = self::normalizarHora($hora);
        if ($hora === null) {
            return 'Sin programar';
        }
        $horaHasta = self::normalizarHora($horaHasta);
        $ventana = $horaHasta !== null ? ($hora.'–'.$horaHasta) : $hora;

        $extra = '';
        if ($diaMes !== null && $diaMes >= 1 && $diaMes <= 31) {
            $base = 'Día '.$diaMes.' '.$ventana;
        } else {
            $lista = self::diasLista($dias);
            if ($lista === []) {
                return 'Sin programar';
            }
            $nombres = array_map(static fn (int $dia) => self::DIAS[$dia], $lista);
            $diasTxt = $lista === [1, 2, 3, 4, 5]
                ? 'Lun a vie'
                : ($lista === [1, 2, 3, 4, 5, 6, 7] ? 'Todos los días' : implode(', ', $nombres));
            $cadaN = max(1, $cadaN);
            $extra = $cadaN > 1 ? (' cada '.$cadaN.' sem.') : '';
            $base = $diasTxt.' '.$ventana;
        }

        if ($hasta !== null && $hasta !== '') {
            try {
                $extra .= ' hasta '.Carbon::parse(substr($hasta, 0, 10))->format('d/m/Y');
            } catch (\Throwable) {
                // ignore
            }
        }
        if ($skipFeriados) {
            $extra .= ' (sin feriados)';
        }

        return $base.$extra;
    }

    public static function etiquetaDesdeTipo(ModuloAvisoTipo $tipo): string
    {
        return self::etiqueta(
            $tipo->programacion_hora,
            $tipo->programacion_dias,
            (bool) $tipo->programacion_habilitada,
            (int) ($tipo->programacion_cada_n ?? 1),
            $tipo->programacion_hasta ? $tipo->programacion_hasta->toDateString() : null,
            isset($tipo->programacion_dia_mes) ? (int) $tipo->programacion_dia_mes : null,
            (bool) ($tipo->programacion_pausada ?? false),
            (bool) ($tipo->programacion_skip_feriados ?? false),
            isset($tipo->programacion_hora_hasta) ? (string) $tipo->programacion_hora_hasta : null
        );
    }

    /** @return array<string, string> */
    public static function modosDependencia(): array
    {
        return [
            'ok_hoy' => 'OK hoy (default)',
            'ok_reciente' => 'Última corrida OK (cualquier día)',
            'sin_fallo_hoy' => 'Sin fallo hoy (permite si aún no corrió)',
        ];
    }
}
