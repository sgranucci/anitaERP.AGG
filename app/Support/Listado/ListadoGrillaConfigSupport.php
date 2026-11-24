<?php

declare(strict_types=1);

namespace App\Support\Listado;

/**
 * Configuración completa de grilla estilo SIFAB:
 * campo, título, visible, ancho, alineación, orden.
 */
final class ListadoGrillaConfigSupport
{
    public const ALINEA_IZQUIERDA = 'izquierda';

    public const ALINEA_CENTRO = 'centro';

    public const ALINEA_DERECHA = 'derecha';

    /** @var list<string> */
    public const ALINEACIONES = [
        self::ALINEA_IZQUIERDA,
        self::ALINEA_CENTRO,
        self::ALINEA_DERECHA,
    ];

    /**
     * Presupuesto de anchos (px) para columnas de datos en pantallas típicas
     * (área útil ~1100–1280 con sidebar AdminLTE). La columna Acciones queda afuera.
     * Si la suma preferida supera esto, se escala hacia abajo respetando el piso legible.
     */
    public const ANCHO_PRESUPUESTO_PANTALLA = 1040;

    /**
     * Ancho fijo de la columna Acciones (editar + CC + borrar).
     * Debe coincidir con `.lw-col-acciones` en listado-workbench.css (~7.25rem).
     */
    public const ANCHO_COL_ACCIONES = 144;

    /** Piso absoluto (px) al escalar: debajo no se lee (cubre Código/Estado con DnD). */
    public const ANCHO_MIN_LEGIBLE = 56;

    /**
     * @param  array<string, array{label: string, default?: bool, type?: string, ...}>  $catalogo
     * @param  array<string, string>  $etiquetasDefault key => etiqueta instalación
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function layoutDefault(array $catalogo, array $etiquetasDefault = []): array
    {
        $filas = [];
        $orden = 0;
        foreach ($catalogo as $key => $meta) {
            $filas[] = [
                'key' => $key,
                'titulo' => $etiquetasDefault[$key] ?? (string) ($meta['label'] ?? $key),
                'visible' => (bool) ($meta['default'] ?? false),
                'ancho' => self::anchoDefault($key, $meta),
                'alinea' => self::alineaDefault($key, $meta),
                'orden' => $orden++,
            ];
        }

        return $filas;
    }

    /**
     * Normaliza JSON de vista / request a layout completo.
     *
     * @param  mixed  $raw list de keys (legacy) o list de defs
     * @param  array<string, array<string, mixed>>  $catalogo
     * @param  array<string, string>  $etiquetasDefault
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function normalizar(mixed $raw, array $catalogo, array $etiquetasDefault = []): array
    {
        $base = self::layoutDefault($catalogo, $etiquetasDefault);
        $porKey = [];
        foreach ($base as $fila) {
            $porKey[$fila['key']] = $fila;
        }

        if (! is_array($raw) || $raw === []) {
            return array_values($porKey);
        }

        // Legacy: ["id","nombre",...]
        if (array_is_list($raw) && isset($raw[0]) && is_string($raw[0])) {
            $visibles = [];
            foreach ($raw as $i => $key) {
                $key = (string) $key;
                if (! isset($porKey[$key])) {
                    continue;
                }
                $porKey[$key]['visible'] = true;
                $porKey[$key]['orden'] = (int) $i;
                $visibles[$key] = true;
            }
            foreach ($porKey as $key => $fila) {
                if (! isset($visibles[$key])) {
                    $porKey[$key]['visible'] = false;
                    $porKey[$key]['orden'] = 1000 + $fila['orden'];
                }
            }

            return self::ordenar(array_values($porKey));
        }

        $orden = 0;
        $vistos = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $key = (string) ($item['key'] ?? $item['campo'] ?? '');
            if ($key === '' || ! isset($porKey[$key]) || isset($vistos[$key])) {
                continue;
            }
            $vistos[$key] = true;
            $titulo = trim((string) ($item['titulo'] ?? $item['label'] ?? ''));
            if ($titulo === '') {
                $titulo = $porKey[$key]['titulo'];
            }
            $alinea = strtolower(trim((string) ($item['alinea'] ?? $item['alineacion'] ?? $porKey[$key]['alinea'])));
            if (! in_array($alinea, self::ALINEACIONES, true)) {
                $alinea = $porKey[$key]['alinea'];
            }
            // Catálogo puede fijar alineación (ej. CUIT con guiones → izquierda)
            $alineaCatalogo = $catalogo[$key]['alinea'] ?? null;
            if (is_string($alineaCatalogo) && in_array($alineaCatalogo, self::ALINEACIONES, true)) {
                $alinea = $alineaCatalogo;
            }
            $ancho = (int) ($item['ancho'] ?? $porKey[$key]['ancho']);
            if ($ancho < 40) {
                $ancho = 40;
            }
            if ($ancho > 600) {
                $ancho = 600;
            }
            $visible = array_key_exists('visible', $item)
                ? filter_var($item['visible'], FILTER_VALIDATE_BOOLEAN)
                : filter_var($item['ver'] ?? true, FILTER_VALIDATE_BOOLEAN);

            $porKey[$key] = [
                'key' => $key,
                'titulo' => mb_substr($titulo, 0, 120),
                'visible' => $visible,
                'ancho' => $ancho,
                'alinea' => $alinea,
                'orden' => $orden++,
            ];
        }

        foreach ($porKey as $key => $fila) {
            if (! isset($vistos[$key])) {
                $porKey[$key]['orden'] = 1000 + $fila['orden'];
            }
        }

        $out = self::ordenar(array_values($porKey));

        // Al menos una visible
        $alguna = false;
        foreach ($out as $fila) {
            if ($fila['visible']) {
                $alguna = true;
                break;
            }
        }
        if (! $alguna && isset($out[0])) {
            $out[0]['visible'] = true;
        }

        return $out;
    }

    /**
     * @param  list<array{key: string, visible: bool, ...}>  $layout
     * @return list<string>
     */
    public static function keysVisibles(array $layout): array
    {
        $keys = [];
        foreach ($layout as $fila) {
            if (! empty($fila['visible'])) {
                $keys[] = (string) $fila['key'];
            }
        }

        return $keys;
    }

    /**
     * @param  list<array{key: string, titulo: string, ...}>  $layout
     * @return array<string, string>
     */
    public static function etiquetasDesdeLayout(array $layout): array
    {
        $out = [];
        foreach ($layout as $fila) {
            $out[(string) $fila['key']] = (string) $fila['titulo'];
        }

        return $out;
    }

    /**
     * @param  list<array{orden: int, ...}>  $filas
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    private static function ordenar(array $filas): array
    {
        usort($filas, static fn ($a, $b) => ((int) $a['orden']) <=> ((int) $b['orden']));
        $i = 0;
        foreach ($filas as &$fila) {
            $fila['orden'] = $i++;
        }
        unset($fila);

        return $filas;
    }

    /**
     * Anchos preferidos compactos: la vista estándar (~10 cols) entra en pantalla
     * sin scroll horizontal; textos largos se truncan con ellipsis + title.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function anchoDefault(string $key, array $meta): int
    {
        if (isset($meta['ancho']) && is_numeric($meta['ancho'])) {
            $anchoMeta = (int) $meta['ancho'];
            if ($anchoMeta >= 40 && $anchoMeta <= 600) {
                return $anchoMeta;
            }
        }

        return match ($key) {
            'id' => 56,
            'fuera_pago' => 56,
            'mail' => 40,
            'codigo', 'estado', 'tipoalta', 'semaforo', 'apoc' => 72,
            'numerodocumento', 'codigopostal', 'telefono' => 96,
            'fecha' => 70,
            'op' => 88,
            'monto' => 100,
            'sku' => 88,
            'descripcion' => 170,
            'unidadmedida' => 72,
            'categoria' => 110,
            'tipoarticulo' => 90,
            'usoarticulo' => 90,
            'numeroparte' => 64,
            'ubicacionparte' => 72,
            'saldo' => 80,
            'nofactura' => 80,
            'codigobarra' => 100,
            'cbu' => 160,
            'alias_cbu' => 110,
            'cuentaventa', 'cuentacompra', 'cuentagasto' => 220,
            'nombre' => 150,
            'fantasia' => 160,
            'proveedor' => 180,
            'detalle' => 130,
            'tipo' => 130,
            'concepto' => 90,
            'movimientos' => 150,
            'numero' => 72,
            'moneda' => 52,
            'fob_estimado' => 88,
            'gastos_estimados' => 88,
            'gastos_reales' => 88,
            'landed_cost' => 92,
            'cuentas' => 150,
            'domicilio' => 120,
            'leyenda', 'email', 'emailoc' => 140,
            'localidad', 'provincia', 'pais', 'empresa' => 90,
            default => (($meta['type'] ?? '') === 'entero' ? 64 : 110),
        };
    }

    /**
     * Piso de legibilidad por columna al ajustar a pantalla.
     * Incluye el título (no truncar encabezados con ellipsis).
     *
     * @param  array<string, mixed>  $meta
     */
    public static function anchoMinimoLegible(string $key, array $meta = [], ?string $titulo = null): int
    {
        $base = match ($key) {
            'id', 'codigo', 'tipoalta', 'semaforo', 'fuera_pago', 'mail', 'numeroparte', 'ubicacionparte' => self::ANCHO_MIN_LEGIBLE,
            'estado', 'apoc', 'fecha', 'unidadmedida', 'nofactura', 'saldo' => 72,
            'op', 'monto', 'sku', 'tipoarticulo', 'usoarticulo', 'numero' => 84,
            'cbu' => 100,
            'numerodocumento', 'alias_cbu' => 72,
            'nombre', 'fantasia', 'domicilio', 'email', 'emailoc', 'leyenda', 'proveedor', 'detalle', 'cuentas', 'descripcion', 'categoria', 'tipo', 'concepto', 'movimientos' => 96,
            default => (($meta['type'] ?? '') === 'entero' ? 52 : 64),
        };

        $label = trim($titulo ?? (string) ($meta['label'] ?? $key));
        if ($label === '') {
            return $base;
        }

        // ~7.2px/carácter (thead 0.78rem) + asa DnD / × / ícono de orden.
        $porTitulo = (int) min(220, max(self::ANCHO_MIN_LEGIBLE, (int) round(mb_strlen($label) * 7.2 + 52)));

        return max($base, $porTitulo);
    }

    /**
     * Escala anchos visibles para caber en el presupuesto de pantalla.
     * No muta preferencias guardadas: solo layout de render.
     * Si aun con pisos de legibilidad no entra, deja los mínimos (habrá scroll).
     *
     * @param  list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>  $layout
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function escalarAnchosParaPantalla(array $layout, ?int $presupuesto = null): array
    {
        $presupuesto = $presupuesto ?? self::ANCHO_PRESUPUESTO_PANTALLA;
        if ($presupuesto < 320) {
            $presupuesto = 320;
        }

        $suma = 0;
        $idxs = [];
        foreach ($layout as $i => $fila) {
            if (empty($fila['visible'])) {
                continue;
            }
            $suma += max(1, (int) $fila['ancho']);
            $idxs[] = $i;
        }
        if ($suma <= 0 || $suma <= $presupuesto) {
            return $layout;
        }

        $factor = $presupuesto / $suma;
        foreach ($idxs as $i) {
            $key = (string) $layout[$i]['key'];
            $preferido = max(1, (int) $layout[$i]['ancho']);
            $titulo = (string) ($layout[$i]['titulo'] ?? '');
            $min = self::anchoMinimoLegible($key, [], $titulo !== '' ? $titulo : null);
            $escalado = (int) max($min, (int) round($preferido * $factor));
            $layout[$i]['ancho'] = $escalado;
        }

        return $layout;
    }

    /**
     * @param  list<array{key: string, visible: bool, ancho: int, ...}>  $layout
     */
    public static function sumaAnchosVisibles(array $layout): int
    {
        $suma = 0;
        foreach ($layout as $fila) {
            if (! empty($fila['visible'])) {
                $suma += max(0, (int) $fila['ancho']);
            }
        }

        return $suma;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function alineaDefault(string $key, array $meta): string
    {
        if (isset($meta['alinea']) && in_array($meta['alinea'], self::ALINEACIONES, true)) {
            return $meta['alinea'];
        }

        $type = $meta['type'] ?? 'texto';
        // numerodocumento (CUIT) lleva guiones → izquierda (fijar vía meta['alinea'] en el catálogo)
        if ($type === 'entero' || in_array($key, ['cbu', 'codigo', 'codigopostal'], true)) {
            return self::ALINEA_DERECHA;
        }
        if (in_array($key, ['estado', 'apoc', 'semaforo', 'tipoalta', 'fuera_pago'], true)) {
            return self::ALINEA_CENTRO;
        }

        return self::ALINEA_IZQUIERDA;
    }

    public static function claseAlineacion(string $alinea): string
    {
        return match ($alinea) {
            self::ALINEA_CENTRO => 'text-center',
            self::ALINEA_DERECHA => 'text-right',
            default => 'text-left',
        };
    }
}
