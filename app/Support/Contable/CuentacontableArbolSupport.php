<?php

namespace App\Support\Contable;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use Illuminate\Support\Collection;

/**
 * Árbol del plan a partir del código de 9 dígitos (Anita).
 * No persiste parent_id: el padre se infiere. Las totalizadoras cuelgan del grupo que totalizan.
 */
class CuentacontableArbolSupport
{
    public const TIPO_IMPUTABLE = '1';

    public const TIPO_TITULO = '2';

    public const TIPO_TOTALIZADORA = '3';

    /**
     * @return array<string, string>
     */
    public static function etiquetasTipo(): array
    {
        return [
            self::TIPO_IMPUTABLE => 'Imputable',
            self::TIPO_TITULO => 'Título',
            self::TIPO_TOTALIZADORA => 'Totalizadora',
        ];
    }

    public static function etiquetaTipo(?string $tipo): string
    {
        $tipo = (string) $tipo;

        return self::etiquetasTipo()[$tipo] ?? '—';
    }

    public static function esTotalizadora(?string $tipo): bool
    {
        return (string) $tipo === self::TIPO_TOTALIZADORA;
    }

    public static function esGrupo(?string $tipo): bool
    {
        return (string) $tipo === self::TIPO_TITULO;
    }

    public static function normalizarCodigo(string $codigo): string
    {
        $digits = preg_replace('/\D/', '', trim($codigo)) ?? '';

        return str_pad($digits, 9, '0', STR_PAD_LEFT);
    }

    public static function formatearCodigo(string $codigo): string
    {
        $n = MayorPlanoCuentaSupport::parsearCodigoCuenta($codigo);
        if ($n <= 0) {
            $norm = self::normalizarCodigo($codigo);

            return $norm === '000000000' ? trim($codigo) : MayorPlanoCuentaSupport::formatearCodigoCuenta((int) $norm);
        }

        return MayorPlanoCuentaSupport::formatearCodigoCuenta($n);
    }

    /**
     * Candidatos de padre, del más específico al más amplio.
     *
     * @return list<string>
     */
    public static function candidatosPadre(string $codigo9): array
    {
        $codigo9 = self::normalizarCodigo($codigo9);
        $out = [];
        for ($i = 8; $i >= 1; $i--) {
            $cand = substr($codigo9, 0, $i).str_repeat('0', 9 - $i);
            if ($cand !== $codigo9 && $cand !== '000000000') {
                $out[] = $cand;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Código de la totalizadora gemela de un título (111010000 → 111019999).
     */
    public static function codigoTotalizadoraDeGrupo(string $codigoGrupo): ?string
    {
        $codigo9 = self::normalizarCodigo($codigoGrupo);
        $base = rtrim($codigo9, '0');
        if ($base === '' || $base === $codigo9) {
            return null;
        }

        return $base.str_repeat('9', 9 - strlen($base));
    }

    /**
     * @param  Collection<int, object>  $cuentas
     * @return list<array<string, mixed>>
     */
    public static function armar(Collection $cuentas, bool $incluirTotalizadoras = false): array
    {
        $nodos = [];
        foreach ($cuentas as $cuenta) {
            $tipo = (string) ($cuenta->tipocuenta ?? '');
            if (! $incluirTotalizadoras && self::esTotalizadora($tipo)) {
                continue;
            }
            $codigo9 = self::normalizarCodigo((string) ($cuenta->codigo ?? ''));
            $nodos[$codigo9] = self::nodoDesdeCuenta($cuenta, $codigo9);
        }

        ksort($nodos, SORT_STRING);

        foreach ($nodos as $codigo9 => &$nodo) {
            $padre = self::resolverPadreCodigo($codigo9, $nodos);
            $nodo['padre_codigo'] = $padre;
        }
        unset($nodo);

        $hijos = [];
        $raices = [];
        foreach ($nodos as $codigo9 => $nodo) {
            $padre = $nodo['padre_codigo'];
            if ($padre !== null && isset($nodos[$padre])) {
                $hijos[$padre][] = $codigo9;
            } else {
                $raices[] = $codigo9;
            }
        }

        $walk = function (string $codigo9) use (&$walk, $nodos, $hijos): array {
            $nodo = $nodos[$codigo9];
            $nodo['hijos'] = [];
            foreach ($hijos[$codigo9] ?? [] as $hijoCodigo) {
                $nodo['hijos'][] = $walk($hijoCodigo);
            }

            return $nodo;
        };

        $arbol = [];
        foreach ($raices as $codigo9) {
            $arbol[] = $walk($codigo9);
        }

        return $arbol;
    }

    /**
     * Marca coincidencias y deja solo ramas que contienen alguna.
     *
     * @param  list<array<string, mixed>>  $arbol
     * @return list<array<string, mixed>>
     */
    public static function podarPorBusqueda(array $arbol, string $busqueda): array
    {
        $q = mb_strtolower(trim($busqueda));
        if ($q === '') {
            return $arbol;
        }

        return self::podar($arbol, fn (array $nodo): bool => self::nodoCoincide($nodo, $q));
    }

    /**
     * @param  list<array<string, mixed>>  $arbol
     * @return list<array<string, mixed>>
     */
    public static function podarPorTipoNivel(array $arbol, string $tipo, int $nivel): array
    {
        if ($tipo === '' && $nivel <= 0) {
            return $arbol;
        }

        return self::podar($arbol, static function (array $nodo) use ($tipo, $nivel): bool {
            if ($tipo !== '' && (string) ($nodo['tipocuenta'] ?? '') !== $tipo) {
                return false;
            }
            if ($nivel > 0 && (int) ($nodo['nivel'] ?? 0) !== $nivel) {
                return false;
            }

            return true;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $arbol
     * @param  callable(array<string, mixed>): bool  $match
     * @return list<array<string, mixed>>
     */
    public static function podar(array $arbol, callable $match): array
    {
        $walk = function (array $nodo) use (&$walk, $match): ?array {
            $hijos = [];
            foreach ($nodo['hijos'] ?? [] as $hijo) {
                $vivo = $walk($hijo);
                if ($vivo !== null) {
                    $hijos[] = $vivo;
                }
            }
            $nodo['hijos'] = $hijos;
            $hay = $match($nodo);
            $nodo['coincide'] = $hay || (bool) ($nodo['coincide'] ?? false);
            $nodo['expandido'] = $hay || $hijos !== [];

            if ($hay || $hijos !== []) {
                return $nodo;
            }

            return null;
        };

        $out = [];
        foreach ($arbol as $raiz) {
            $vivo = $walk($raiz);
            if ($vivo !== null) {
                $out[] = $vivo;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $arbol
     */
    public static function contarNodos(array $arbol): int
    {
        $n = 0;
        $walk = function (array $nodo) use (&$walk, &$n): void {
            $n++;
            foreach ($nodo['hijos'] ?? [] as $hijo) {
                $walk($hijo);
            }
        };
        foreach ($arbol as $raiz) {
            $walk($raiz);
        }

        return $n;
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodos
     */
    private static function resolverPadreCodigo(string $codigo9, array $nodos): ?string
    {
        foreach (self::candidatosPadre($codigo9) as $cand) {
            if (isset($nodos[$cand])) {
                return $cand;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function nodoDesdeCuenta(object $cuenta, string $codigo9): array
    {
        $tipo = (string) ($cuenta->tipocuenta ?? '');
        $nivel = (int) ($cuenta->nivel ?? 0);

        return [
            'id' => (int) ($cuenta->id ?? 0),
            'empresa_id' => (int) ($cuenta->empresa_id ?? 0),
            'codigo' => (string) ($cuenta->codigo ?? ''),
            'codigo9' => $codigo9,
            'codigo_fmt' => self::formatearCodigo((string) ($cuenta->codigo ?? '')),
            'nombre' => (string) ($cuenta->nombre ?? ''),
            'nivel' => $nivel,
            'tipocuenta' => $tipo,
            'tipo_label' => self::etiquetaTipo($tipo),
            'rubro' => (string) ($cuenta->rubrocontables->nombre ?? ''),
            'manejaccosto' => (string) ($cuenta->manejaccosto ?? 'N'),
            'concepto' => (string) ($cuenta->conceptogastos->nombre ?? ''),
            'padre_codigo' => null,
            'hijos' => [],
            'coincide' => false,
            'expandido' => $nivel > 0 && $nivel <= 2,
        ];
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private static function nodoCoincide(array $nodo, string $q): bool
    {
        $hay = mb_strtolower((string) ($nodo['nombre'] ?? ''));
        $cod = mb_strtolower((string) ($nodo['codigo'] ?? '').' '.(string) ($nodo['codigo_fmt'] ?? ''));
        $tipo = mb_strtolower((string) ($nodo['tipo_label'] ?? ''));

        return str_contains($hay, $q) || str_contains($cod, $q) || str_contains($tipo, $q);
    }
}
