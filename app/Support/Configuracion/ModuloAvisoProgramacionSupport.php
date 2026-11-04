<?php

namespace App\Support\Configuracion;

use Illuminate\Support\Carbon;

/**
 * Decide si un aviso del módulo general corresponde enviarse ahora.
 * Día ISO: 1 = lunes … 7 = domingo. La hora es HH:MM.
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
        Carbon $ahora
    ): bool {
        $hora = self::normalizarHora($hora);
        $lista = self::diasLista($dias);
        if (! $habilitada || $hora === null || $lista === []) {
            return false;
        }
        if (! in_array((int) $ahora->dayOfWeekIso, $lista, true)) {
            return false;
        }
        if ($ultima !== null && substr($ultima, 0, 10) === $ahora->toDateString()) {
            return false;
        }

        return $ahora->format('H:i') >= $hora;
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

    public static function etiqueta(?string $hora, ?string $dias, bool $habilitada): string
    {
        if (! $habilitada) {
            return 'Sin programar';
        }
        $hora = self::normalizarHora($hora);
        $lista = self::diasLista($dias);
        if ($hora === null || $lista === []) {
            return 'Sin programar';
        }
        $nombres = array_map(static fn (int $dia) => self::DIAS[$dia], $lista);
        $diasTxt = $lista === [1, 2, 3, 4, 5]
            ? 'Lun a vie'
            : ($lista === [1, 2, 3, 4, 5, 6, 7] ? 'Todos los días' : implode(', ', $nombres));

        return $diasTxt.' '.$hora;
    }
}
