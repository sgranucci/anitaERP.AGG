<?php

namespace App\Support\Sueldos\Lsd;

/**
 * Flags de subsistemas del TXT de conceptos (posiciones 168-186).
 * '0' no aplica / '1' aplica. Libre = espacio.
 */
class LsdSubsistemaSupport
{
    /** @var list<array{clave: string, etiqueta: string, pos: int, tipo: 'flag'|'libre'}> */
    public const CAMPOS = [
        ['clave' => 'sipa_ap', 'etiqueta' => 'Aportes SIPA', 'pos' => 168, 'tipo' => 'flag'],
        ['clave' => 'sipa_co', 'etiqueta' => 'Contrib. SIPA', 'pos' => 169, 'tipo' => 'flag'],
        ['clave' => 'inssjp_ap', 'etiqueta' => 'Aportes INSSJyP', 'pos' => 170, 'tipo' => 'flag'],
        ['clave' => 'inssjp_co', 'etiqueta' => 'Contrib. INSSJyP', 'pos' => 171, 'tipo' => 'flag'],
        ['clave' => 'os_ap', 'etiqueta' => 'Aportes obra social', 'pos' => 172, 'tipo' => 'flag'],
        ['clave' => 'os_co', 'etiqueta' => 'Contrib. obra social', 'pos' => 173, 'tipo' => 'flag'],
        ['clave' => 'fsr_ap', 'etiqueta' => 'Aportes FSR', 'pos' => 174, 'tipo' => 'flag'],
        ['clave' => 'fsr_co', 'etiqueta' => 'Contrib. FSR', 'pos' => 175, 'tipo' => 'flag'],
        ['clave' => 'renatea_ap', 'etiqueta' => 'Aportes RENATEA', 'pos' => 176, 'tipo' => 'flag'],
        ['clave' => 'renatea_co', 'etiqueta' => 'Contrib. RENATEA', 'pos' => 177, 'tipo' => 'flag'],
        ['clave' => 'libre_178', 'etiqueta' => 'Libre', 'pos' => 178, 'tipo' => 'libre'],
        ['clave' => 'af_co', 'etiqueta' => 'Contrib. asignaciones familiares', 'pos' => 179, 'tipo' => 'flag'],
        ['clave' => 'libre_180', 'etiqueta' => 'Libre', 'pos' => 180, 'tipo' => 'libre'],
        ['clave' => 'fne_co', 'etiqueta' => 'Contrib. FNE', 'pos' => 181, 'tipo' => 'flag'],
        ['clave' => 'libre_182', 'etiqueta' => 'Libre', 'pos' => 182, 'tipo' => 'libre'],
        ['clave' => 'lrt_co', 'etiqueta' => 'Contrib. LRT', 'pos' => 183, 'tipo' => 'flag'],
        ['clave' => 'dif_ap', 'etiqueta' => 'Aportes regímenes diferenciales', 'pos' => 184, 'tipo' => 'flag'],
        ['clave' => 'libre_185', 'etiqueta' => 'Libre', 'pos' => 185, 'tipo' => 'libre'],
        ['clave' => 'esp_ap', 'etiqueta' => 'Aportes regímenes especiales', 'pos' => 186, 'tipo' => 'flag'],
    ];

    /** @return array<string, int> */
    public static function defaultsParaTipo(string $tipo): array
    {
        $todos = $tipo === 'remunerativo' ? 1 : 0;
        $out = [];
        foreach (self::CAMPOS as $c) {
            if ($c['tipo'] === 'flag') {
                $out[$c['clave']] = $todos;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $flags
     * @return array<string, int>
     */
    public static function normalizar(?array $flags, string $tipo): array
    {
        $base = self::defaultsParaTipo($tipo);
        if (! is_array($flags)) {
            return $base;
        }
        foreach ($base as $k => $v) {
            if (array_key_exists($k, $flags)) {
                $base[$k] = ((int) $flags[$k]) === 1 ? 1 : 0;
            }
        }

        return $base;
    }

    /** @param  array<string, int>  $flags */
    public static function bloquetxt(array $flags): string
    {
        $out = '';
        foreach (self::CAMPOS as $c) {
            if ($c['tipo'] === 'libre') {
                $out .= ' ';

                continue;
            }
            $out .= ((int) ($flags[$c['clave']] ?? 0)) === 1 ? '1' : '0';
        }

        return $out.str_repeat(' ', 9);
    }

    /** @return list<array{clave: string, etiqueta: string}> */
    public static function flagsEditables(): array
    {
        $out = [];
        foreach (self::CAMPOS as $c) {
            if ($c['tipo'] === 'flag') {
                $out[] = ['clave' => $c['clave'], 'etiqueta' => $c['etiqueta']];
            }
        }

        return $out;
    }
}
