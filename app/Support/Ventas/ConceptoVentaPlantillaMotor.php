<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Motor de plantillas de concepto/abono:
 * - tags @clave@
 * - tipos texto|fecha|periodo|lista
 * - tags de sistema
 * - condicionales {{#si clave}}…{{/si}} y {{#si clave=valor}}…{{/si}}
 */
final class ConceptoVentaPlantillaMotor
{
    public const TIPO_TEXTO = 'texto';

    public const TIPO_FECHA = 'fecha';

    public const TIPO_PERIODO = 'periodo';

    public const TIPO_LISTA = 'lista';

    /** @var list<string> */
    public const TIPOS = [
        self::TIPO_TEXTO,
        self::TIPO_FECHA,
        self::TIPO_PERIODO,
        self::TIPO_LISTA,
    ];

    public const ORIGEN_PEDIBLE = 'pedible';

    public const ORIGEN_SISTEMA = 'sistema';

    public const REGEX_TAG = '/@([a-z][a-z0-9_]{0,39})@/';

    /** Condicional: {{#si clave}}…{{/si}} o {{#si clave=valor}}…{{/si}} */
    public const REGEX_CONDICIONAL = '/\{\{#si\s+([a-z][a-z0-9_]{0,39})(?:\s*=\s*([^}]+))?\}\}(.*?)\{\{\/si\}\}/is';

    public const LARGO_DETALLE_ARCA = 250;

    /** @var list<string> */
    public const TAGS_SISTEMA = [
        'cliente',
        'cuit',
        'fecha_factura',
        'empresa',
        'codigo_concepto',
        'nombre_concepto',
    ];

    public static function normalizarClave(string $clave): string
    {
        $clave = strtolower(trim($clave));
        $clave = preg_replace('/[^a-z0-9_]/', '', $clave) ?? '';

        return substr($clave, 0, 40);
    }

    public static function esClaveValida(string $clave): bool
    {
        return preg_match('/^[a-z][a-z0-9_]{0,39}$/', $clave) === 1;
    }

    public static function normalizarTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));

        return in_array($tipo, self::TIPOS, true) ? $tipo : self::TIPO_TEXTO;
    }

    public static function normalizarOrigen(string $origen): string
    {
        $origen = strtolower(trim($origen));

        return $origen === self::ORIGEN_SISTEMA ? self::ORIGEN_SISTEMA : self::ORIGEN_PEDIBLE;
    }

    public static function esTagSistema(string $clave): bool
    {
        return in_array(self::normalizarClave($clave), self::TAGS_SISTEMA, true);
    }

    /**
     * @return list<string>
     */
    public static function extraerClaves(string $plantilla): array
    {
        $claves = [];
        if ($plantilla !== '' && preg_match_all(self::REGEX_TAG, $plantilla, $m)) {
            foreach ($m[1] as $clave) {
                $clave = self::normalizarClave($clave);
                if ($clave !== '' && ! in_array($clave, $claves, true)) {
                    $claves[] = $clave;
                }
            }
        }
        if ($plantilla !== '' && preg_match_all(self::REGEX_CONDICIONAL, $plantilla, $mc)) {
            foreach ($mc[1] as $clave) {
                $clave = self::normalizarClave($clave);
                if ($clave !== '' && ! in_array($clave, $claves, true)) {
                    $claves[] = $clave;
                }
            }
            foreach ($mc[3] as $bloque) {
                foreach (self::extraerClaves((string) $bloque) as $clave) {
                    if (! in_array($clave, $claves, true)) {
                        $claves[] = $clave;
                    }
                }
            }
        }

        return $claves;
    }

    /**
     * @param  array<string, string>  $valores
     */
    public static function render(string $plantilla, array $valores): string
    {
        $texto = self::aplicarCondicionales($plantilla, $valores);
        $texto = self::sustituirTags($texto, $valores);

        return trim(preg_replace('/[ \t]{2,}/', ' ', $texto) ?? $texto);
    }

    /**
     * @param  array<string, string>  $valores
     */
    public static function sustituirTags(string $plantilla, array $valores): string
    {
        return (string) preg_replace_callback(self::REGEX_TAG, static function (array $m) use ($valores): string {
            $clave = strtolower($m[1]);
            if (! array_key_exists($clave, $valores)) {
                return $m[0];
            }

            return trim((string) $valores[$clave]);
        }, $plantilla);
    }

    /**
     * @param  array<string, string>  $valores
     */
    public static function aplicarCondicionales(string $plantilla, array $valores): string
    {
        $prev = null;
        $texto = $plantilla;
        // Repetir por si hay anidados simples (profundidad baja).
        for ($i = 0; $i < 5 && $texto !== $prev; $i++) {
            $prev = $texto;
            $texto = (string) preg_replace_callback(
                self::REGEX_CONDICIONAL,
                static function (array $m) use ($valores): string {
                    $clave = strtolower(trim($m[1]));
                    $esperado = isset($m[2]) && $m[2] !== '' ? trim((string) $m[2]) : null;
                    $bloque = (string) ($m[3] ?? '');
                    $actual = trim((string) ($valores[$clave] ?? ''));
                    if ($esperado === null) {
                        return $actual !== '' ? $bloque : '';
                    }

                    return strcasecmp($actual, $esperado) === 0 ? $bloque : '';
                },
                $texto
            );
        }

        return $texto;
    }

    public static function tieneTagsSinResolver(string $texto): bool
    {
        // Tras render no deberían quedar tags ni condicionales abiertos.
        if (preg_match(self::REGEX_TAG, $texto) === 1) {
            return true;
        }

        return preg_match('/\{\{#si\b/', $texto) === 1;
    }

    public static function mensajeTagsPendientes(string $texto): string
    {
        $claves = self::extraerClaves($texto);
        if ($claves === []) {
            if (preg_match('/\{\{#si\b/', $texto) === 1) {
                return 'El detalle del concepto tiene condicionales {{#si}} sin resolver.';
            }

            return '';
        }
        $listado = implode(', ', array_map(static fn (string $c): string => '@'.$c.'@', $claves));

        return 'El detalle del concepto tiene tags sin completar: '.$listado
            .'. Completelos con el modal, el abono o edite el texto.';
    }

    /**
     * Formatea un valor según tipo de tag.
     *
     * @param  array{tipo?: string, largo_max?: int|null}  $meta
     */
    public static function formatearValor(string $valor, array $meta = []): string
    {
        $tipo = self::normalizarTipo((string) ($meta['tipo'] ?? self::TIPO_TEXTO));
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }

        $out = match ($tipo) {
            self::TIPO_FECHA => self::formatearFecha($valor),
            self::TIPO_PERIODO => self::formatearPeriodo($valor),
            default => $valor,
        };

        $largo = isset($meta['largo_max']) ? (int) $meta['largo_max'] : 0;
        if ($largo > 0 && mb_strlen($out) > $largo) {
            $out = mb_substr($out, 0, $largo);
        }

        return $out;
    }

    /**
     * Acepta Y-m-d, d/m/Y o texto libre ya formateado.
     */
    public static function formatearFecha(string $valor): string
    {
        $valor = trim($valor);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1) {
            [$y, $m, $d] = explode('-', $valor);

            return $d.'/'.$m.'/'.$y;
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor) === 1) {
            return $valor;
        }

        return $valor;
    }

    /**
     * Acepta:
     * - "2026-08-01|2026-08-31"
     * - "2026-08" / "08/2026"
     * - texto libre
     */
    public static function formatearPeriodo(string $valor): string
    {
        $valor = trim($valor);
        if (str_contains($valor, '|')) {
            [$desde, $hasta] = array_pad(explode('|', $valor, 2), 2, '');
            $d = self::formatearFecha(trim($desde));
            $h = self::formatearFecha(trim($hasta));
            if ($d !== '' && $h !== '') {
                return $d.' al '.$h;
            }
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $valor, $m) === 1) {
            $desde = $m[1].'-'.$m[2].'-01';
            $hasta = date('Y-m-t', strtotime($desde));

            return self::formatearFecha($desde).' al '.self::formatearFecha($hasta);
        }
        if (preg_match('/^(\d{2})\/(\d{4})$/', $valor, $m) === 1) {
            $desde = $m[2].'-'.$m[1].'-01';
            $hasta = date('Y-m-t', strtotime($desde));

            return self::formatearFecha($desde).' al '.self::formatearFecha($hasta);
        }

        return $valor;
    }

    /**
     * @param  array{
     *   cliente_nombre?: string|null,
     *   cliente_documento?: string|null,
     *   fecha_factura?: string|null,
     *   empresa_nombre?: string|null,
     *   codigo_concepto?: string|null,
     *   nombre_concepto?: string|null
     * }  $ctx
     * @return array<string, string>
     */
    public static function valoresSistema(array $ctx): array
    {
        $fecha = trim((string) ($ctx['fecha_factura'] ?? ''));
        if ($fecha !== '') {
            $fecha = self::formatearFecha(substr($fecha, 0, 10));
        }

        return [
            'cliente' => trim((string) ($ctx['cliente_nombre'] ?? '')),
            'cuit' => trim((string) ($ctx['cliente_documento'] ?? '')),
            'fecha_factura' => $fecha,
            'empresa' => trim((string) ($ctx['empresa_nombre'] ?? '')),
            'codigo_concepto' => trim((string) ($ctx['codigo_concepto'] ?? '')),
            'nombre_concepto' => trim((string) ($ctx['nombre_concepto'] ?? '')),
        ];
    }

    /**
     * Une valores (pedibles/contrato/sistema), formatea y renderiza plantilla.
     *
     * @param  array<string, string>  $valores
     * @param  array<string, array{tipo?: string, largo_max?: int|null}>  $metas
     * @return array{texto: string, valores: array<string, string>}
     */
    public static function resolver(string $plantilla, array $valores, array $metas = []): array
    {
        $formateados = [];
        foreach ($valores as $clave => $valor) {
            $claveN = self::normalizarClave((string) $clave);
            if ($claveN === '') {
                continue;
            }
            $formateados[$claveN] = self::formatearValor((string) $valor, $metas[$claveN] ?? []);
        }
        $texto = self::render($plantilla, $formateados);

        return ['texto' => $texto, 'valores' => $formateados];
    }
}
