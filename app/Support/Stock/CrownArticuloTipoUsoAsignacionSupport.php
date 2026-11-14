<?php

namespace App\Support\Stock;

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alta de maestros tipo/uso y asignación en Crown.
 *
 * Uso INSUMO GASTRONOMIA: SKU que empieza con «I».
 * Resto: por categoría (+ fallbacks V* / M*).
 */
final class CrownArticuloTipoUsoAsignacionSupport
{
    /** @var list<array{nombre: string, abreviatura: string, cigarrillos: bool, letra: ?string, iva_indirecto: bool}> */
    public const TIPOS = [
        ['nombre' => 'ALIMENTO', 'abreviatura' => 'A', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'BEBIDA', 'abreviatura' => 'B', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'OTRO', 'abreviatura' => 'O', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'CIGARRILLO', 'abreviatura' => 'C', 'cigarrillos' => true, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'KIOSCO', 'abreviatura' => 'K', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'PREMIO', 'abreviatura' => 'P', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'MATERIA PRIMA', 'abreviatura' => 'M', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'BIEN ECONOMICO', 'abreviatura' => 'E', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'BIEN DE USO', 'abreviatura' => 'U', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'SERVICIO', 'abreviatura' => 'S', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'CONTABLE', 'abreviatura' => '1', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'IMPUESTO', 'abreviatura' => '2', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'VENTA', 'abreviatura' => 'V', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'REGALO', 'abreviatura' => 'G', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'CAPEX', 'abreviatura' => 'X', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'INDUMENTARIA', 'abreviatura' => 'IND', 'cigarrillos' => false, 'letra' => null, 'iva_indirecto' => false],
        ['nombre' => 'ALQUILERES', 'abreviatura' => 'ALQ', 'cigarrillos' => false, 'letra' => 'A', 'iva_indirecto' => true],
    ];

    /** @var list<array{nombre: string, aprobacion_modo: string}> */
    public const USOS = [
        ['nombre' => 'GASTRONOMIA', 'aprobacion_modo' => 'arbol'],
        ['nombre' => 'COMPRA', 'aprobacion_modo' => 'default'],
        ['nombre' => 'LABORATORIO', 'aprobacion_modo' => 'default'],
        ['nombre' => 'MANTENIMIENTO', 'aprobacion_modo' => 'default'],
        ['nombre' => 'MARKETING', 'aprobacion_modo' => 'default'],
        ['nombre' => 'INSUMO GASTRONOMIA', 'aprobacion_modo' => 'default'],
        ['nombre' => 'LIBRERIA', 'aprobacion_modo' => 'default'],
        ['nombre' => 'DESCARTABLES', 'aprobacion_modo' => 'default'],
        ['nombre' => 'LIMPIEZA', 'aprobacion_modo' => 'default'],
        ['nombre' => 'SISTEMAS', 'aprobacion_modo' => 'default'],
        ['nombre' => 'SEGURIDAD', 'aprobacion_modo' => 'default'],
        ['nombre' => 'SALA BINGO', 'aprobacion_modo' => 'default'],
        ['nombre' => 'RRHH', 'aprobacion_modo' => 'default'],
    ];

    /**
     * Categoría → [abreviatura tipo, nombre uso].
     * INSUMO GASTRONOMIA no va acá: solo SKU I*.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const MAPA_CATEGORIA = [
        'COCTELES' => ['B', 'GASTRONOMIA'],
        'JUGOS Y LICUADOS' => ['B', 'GASTRONOMIA'],
        'GASEOSAS Y AGUAS' => ['B', 'GASTRONOMIA'],
        'CERVEZAS' => ['B', 'GASTRONOMIA'],
        'VINOS BLANCOS' => ['B', 'GASTRONOMIA'],
        'VINOS TINTOS' => ['B', 'GASTRONOMIA'],
        'WHISKIES' => ['B', 'GASTRONOMIA'],
        'WHISKIES ENTEROS' => ['B', 'GASTRONOMIA'],
        'LICORES' => ['B', 'GASTRONOMIA'],
        'LICORES ENTEROS' => ['B', 'GASTRONOMIA'],
        'APERITIVOS' => ['B', 'GASTRONOMIA'],
        'APERITIVOS ENTEROS' => ['B', 'GASTRONOMIA'],
        'BEBIDAS BLANCAS' => ['B', 'GASTRONOMIA'],
        'BEBIDAS BLANCAS ENTERAS' => ['B', 'GASTRONOMIA'],
        'ESPUMANTES' => ['B', 'GASTRONOMIA'],
        'JUGOS ENTEROS' => ['B', 'GASTRONOMIA'],
        'BOTELLAS 50 ML' => ['B', 'GASTRONOMIA'],
        'PLATOS RESTAURANTE' => ['A', 'GASTRONOMIA'],
        'SANDWICH Y TABLAS' => ['A', 'GASTRONOMIA'],
        'POSTRES' => ['A', 'GASTRONOMIA'],
        'HELADOS' => ['A', 'GASTRONOMIA'],
        'ENTRADAS' => ['A', 'GASTRONOMIA'],
        'ADICIONALES' => ['A', 'GASTRONOMIA'],
        'ADICIONALES DE COCINA' => ['A', 'GASTRONOMIA'],
        'CAFETERIA' => ['A', 'GASTRONOMIA'],
        'PROMOCIONES DE VENTA' => ['A', 'GASTRONOMIA'],
        'ALMACEN' => ['M', 'COMPRA'],
        'CARNES / AVES / MARISCOS' => ['M', 'COMPRA'],
        'FRUTAS Y VERDURAS' => ['M', 'COMPRA'],
        'PANADERIA' => ['M', 'COMPRA'],
        'LACTEOS Y DERIVADOS' => ['M', 'COMPRA'],
        'EMBUTIDOS' => ['M', 'COMPRA'],
        'COMIDA PERSONAL' => ['A', 'RRHH'],
        'CIGARRILLOS' => ['C', 'COMPRA'],
        'GOLOSINAS' => ['K', 'COMPRA'],
        'UNIFORMES' => ['IND', 'RRHH'],
        'DESCARTABLES' => ['O', 'DESCARTABLES'],
        'LIMPIEZA' => ['O', 'LIMPIEZA'],
        'AMENITIES' => ['O', 'LIMPIEZA'],
        'VAJILLA Y ELEMENTOS DE BARRA' => ['E', 'COMPRA'],
        'INSUMO MANTENIMIENTO' => ['E', 'MANTENIMIENTO'],
        'ELECTRICIDAD' => ['E', 'MANTENIMIENTO'],
        'HERRAMIENTAS' => ['U', 'MANTENIMIENTO'],
        'PINTURA' => ['E', 'MANTENIMIENTO'],
        'OBRA CIVIL' => ['X', 'MANTENIMIENTO'],
        'TERMOMECANICA' => ['U', 'MANTENIMIENTO'],
        'GAS' => ['E', 'MANTENIMIENTO'],
        'JARDINERIA' => ['E', 'MANTENIMIENTO'],
        'SANITARIA' => ['E', 'MANTENIMIENTO'],
        'HOTEL' => ['E', 'COMPRA'],
        'INSUMOS DE COMPUTACION' => ['E', 'SISTEMAS'],
        'PROGRAMAS DE COMPUTACION' => ['S', 'SISTEMAS'],
        'COMPUTACION  - SLOTS' => ['E', 'SISTEMAS'],
        'ELECTRONICA - SLOTS' => ['U', 'SALA BINGO'],
        'REPUESTOS MAQUINAS - SLOTS' => ['E', 'SALA BINGO'],
        'INSUMOS - SLOTS' => ['E', 'SALA BINGO'],
        'INSUMOS ELECTRONICA - SLOTS' => ['E', 'SALA BINGO'],
        'INSUMOS RULETAS - SLOTS' => ['E', 'SALA BINGO'],
        'PLACAS - SLOTS' => ['E', 'SALA BINGO'],
        'INSTRUMENTAL - SLOTS' => ['U', 'SALA BINGO'],
        'ELECTRICIDAD - SLOTS' => ['E', 'SALA BINGO'],
        'HERRAMIENTAS - SLOTS' => ['U', 'SALA BINGO'],
        'JUEGO' => ['E', 'SALA BINGO'],
        'SEGURIDAD E HIGIENE' => ['E', 'SEGURIDAD'],
        'REDES CCTV' => ['U', 'SEGURIDAD'],
        'INCENDIO' => ['E', 'SEGURIDAD'],
        'MARKETING' => ['E', 'MARKETING'],
        'LIBRERIA' => ['E', 'LIBRERIA'],
        'MEDICAMENTOS' => ['O', 'RRHH'],
        'NO NEGOCIABLES' => ['O', 'COMPRA'],
        'ARTICULOS ESPECIALES' => ['O', 'COMPRA'],
    ];

    public static function esSkuInsumoGastronomia(?string $sku): bool
    {
        return str_starts_with(strtoupper(trim((string) $sku)), 'I');
    }

    /**
     * @return array{tipo_abrev: string, uso_nombre: string}
     */
    public static function resolverAbrevYUso(?string $sku, ?string $nombreCategoria): array
    {
        $mapa = self::MAPA_CATEGORIA;
        $tipoAbrev = 'O';
        $usoNombre = 'COMPRA';

        $cat = trim((string) $nombreCategoria);
        if ($cat !== '' && isset($mapa[$cat])) {
            [$tipoAbrev, $usoNombre] = $mapa[$cat];
        } else {
            $skuU = strtoupper(trim((string) $sku));
            if (str_starts_with($skuU, 'V')) {
                $usoNombre = 'GASTRONOMIA';
                $tipoAbrev = 'A';
            } elseif (str_starts_with($skuU, 'M')) {
                $usoNombre = 'MANTENIMIENTO';
                $tipoAbrev = 'E';
            }
        }

        if (self::esSkuInsumoGastronomia($sku)) {
            $usoNombre = ArticuloUsoInsumoSupport::NOMBRE_USO_INSUMO;
        }

        return ['tipo_abrev' => $tipoAbrev, 'uso_nombre' => $usoNombre];
    }

    /**
     * Para import Anita Crown: tipo preferido por categoría; si no, abreviatura Anita.
     *
     * @return array{tipoarticulo_id: int, usoarticulo_id: int}
     */
    public static function resolverIdsParaImport(
        ?string $sku,
        ?string $nombreCategoria,
        ?string $abreviaturaAnita = null
    ): array {
        $ids = self::mapasIds();
        $res = self::resolverAbrevYUso($sku, $nombreCategoria);

        $tipoAbrev = $res['tipo_abrev'];
        $abrevAnita = strtoupper(trim((string) $abreviaturaAnita));
        if (trim((string) $nombreCategoria) === '' && $abrevAnita !== '' && isset($ids['tipos'][$abrevAnita])) {
            $tipoAbrev = $abrevAnita;
        }

        $tipoId = $ids['tipos'][$tipoAbrev]
            ?? ($abrevAnita !== '' ? ($ids['tipos'][$abrevAnita] ?? null) : null)
            ?? TipoarticuloDefaultSupport::idParaImportacionAnita();

        $usoId = $ids['usos'][$res['uso_nombre']]
            ?? $ids['usos']['COMPRA']
            ?? null;

        if ($usoId === null) {
            throw new \RuntimeException('Falta uso COMPRA en usoarticulo (Crown).');
        }

        return [
            'tipoarticulo_id' => (int) $tipoId,
            'usoarticulo_id' => (int) $usoId,
        ];
    }

    /**
     * @return array{
     *   tipos_creados: int,
     *   tipos_actualizados: int,
     *   usos_creados: int,
     *   usos_actualizados: int,
     *   articulos_tipo: int,
     *   articulos_uso: int,
     *   categorias_tipo: int,
     *   por_tipo: array<string, int>,
     *   por_uso: array<string, int>,
     *   dry_run: bool
     * }
     */
    public static function asegurarMaestrosYAsignar(bool $ejecutar = false): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new \RuntimeException('Solo aplica en entorno CROWN.');
        }

        $maestros = self::asegurarMaestros($ejecutar);
        $ids = self::mapasIds();
        $asignacion = self::asignarArticulosYCategorias($ids, $ejecutar);

        if ($ejecutar) {
            ArticuloUsoInsumoSupport::olvidarCache();
        }

        return array_merge($maestros, $asignacion, ['dry_run' => ! $ejecutar]);
    }

    /**
     * @return array{tipos_creados: int, tipos_actualizados: int, usos_creados: int, usos_actualizados: int}
     */
    public static function asegurarMaestros(bool $ejecutar = false): array
    {
        $tiposCreados = 0;
        $tiposActualizados = 0;
        $usosCreados = 0;
        $usosActualizados = 0;
        $now = now();

        foreach (self::TIPOS as $tipo) {
            $existente = DB::table('tipoarticulo')
                ->where('abreviatura', $tipo['abreviatura'])
                ->first();

            $payload = [
                'nombre' => $tipo['nombre'],
                'abreviatura' => $tipo['abreviatura'],
                'usa_control_contable_cigarrillos' => $tipo['cigarrillos'],
                'letra_comprobante_compra' => $tipo['letra'],
                'comprobante_compra_iva_indirecto' => $tipo['iva_indirecto'],
                'updated_at' => $now,
            ];

            if (! $existente) {
                $tiposCreados++;
                if ($ejecutar) {
                    $payload['created_at'] = $now;
                    DB::table('tipoarticulo')->insert($payload);
                }
                continue;
            }

            $cambia = trim((string) $existente->nombre) !== $tipo['nombre']
                || (bool) $existente->usa_control_contable_cigarrillos !== $tipo['cigarrillos']
                || (string) ($existente->letra_comprobante_compra ?? '') !== (string) ($tipo['letra'] ?? '')
                || (bool) ($existente->comprobante_compra_iva_indirecto ?? false) !== $tipo['iva_indirecto'];

            if ($cambia) {
                $tiposActualizados++;
                if ($ejecutar) {
                    DB::table('tipoarticulo')->where('id', $existente->id)->update($payload);
                }
            }
        }

        foreach (self::USOS as $uso) {
            $existente = DB::table('usoarticulo')
                ->whereRaw('UPPER(TRIM(nombre)) = ?', [$uso['nombre']])
                ->first();

            if (! $existente) {
                $usosCreados++;
                if ($ejecutar) {
                    DB::table('usoarticulo')->insert([
                        'nombre' => $uso['nombre'],
                        'aprobacion_modo' => $uso['aprobacion_modo'],
                        'arbolaprobacion_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                continue;
            }

            if ((string) ($existente->aprobacion_modo ?? 'default') !== $uso['aprobacion_modo']) {
                $usosActualizados++;
                if ($ejecutar) {
                    DB::table('usoarticulo')->where('id', $existente->id)->update([
                        'aprobacion_modo' => $uso['aprobacion_modo'],
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        return [
            'tipos_creados' => $tiposCreados,
            'tipos_actualizados' => $tiposActualizados,
            'usos_creados' => $usosCreados,
            'usos_actualizados' => $usosActualizados,
        ];
    }

    /**
     * @return array{tipos: array<string, int>, usos: array<string, int>}
     */
    public static function mapasIds(): array
    {
        $tipos = [];
        foreach (DB::table('tipoarticulo')->get(['id', 'abreviatura']) as $row) {
            $tipos[strtoupper(trim((string) $row->abreviatura))] = (int) $row->id;
        }

        $usos = [];
        foreach (DB::table('usoarticulo')->get(['id', 'nombre']) as $row) {
            $usos[strtoupper(trim((string) $row->nombre))] = (int) $row->id;
        }

        return ['tipos' => $tipos, 'usos' => $usos];
    }

    /**
     * @param  array{tipos: array<string, int>, usos: array<string, int>}  $ids
     * @return array{
     *   articulos_tipo: int,
     *   articulos_uso: int,
     *   categorias_tipo: int,
     *   por_tipo: array<string, int>,
     *   por_uso: array<string, int>
     * }
     */
    public static function asignarArticulosYCategorias(array $ids, bool $ejecutar = false): array
    {
        $cats = DB::table('categoria')->get(['id', 'nombre', 'tipoarticulo_id'])->keyBy('id');
        $porTipo = [];
        $porUso = [];
        $articulosTipo = 0;
        $articulosUso = 0;
        $now = now();

        // Agrupar updates: clave "tipoId|usoId" → list ids
        $lotes = [];

        foreach (DB::table('articulo')->select(['id', 'sku', 'categoria_id', 'tipoarticulo_id', 'usoarticulo_id'])->cursor() as $art) {
            $nombreCat = $art->categoria_id ? (string) ($cats[$art->categoria_id]->nombre ?? '') : '';
            $res = self::resolverAbrevYUso($art->sku, $nombreCat !== '' ? $nombreCat : null);

            $porTipo[$res['tipo_abrev']] = ($porTipo[$res['tipo_abrev']] ?? 0) + 1;
            $porUso[$res['uso_nombre']] = ($porUso[$res['uso_nombre']] ?? 0) + 1;

            $tipoId = $ids['tipos'][$res['tipo_abrev']] ?? $ids['tipos']['O'] ?? null;
            $usoId = $ids['usos'][$res['uso_nombre']] ?? $ids['usos']['COMPRA'] ?? null;

            $tipoActualAbrev = null;
            foreach ($ids['tipos'] as $abrev => $idTipo) {
                if ((int) $idTipo === (int) $art->tipoarticulo_id) {
                    $tipoActualAbrev = $abrev;
                    break;
                }
            }
            $usoActualNombre = null;
            foreach ($ids['usos'] as $nombreUso => $idUso) {
                if ((int) $idUso === (int) ($art->usoarticulo_id ?? 0)) {
                    $usoActualNombre = $nombreUso;
                    break;
                }
            }

            $cambiaTipo = $tipoActualAbrev !== $res['tipo_abrev'];
            $cambiaUso = $usoActualNombre !== $res['uso_nombre'];
            if ($cambiaTipo) {
                $articulosTipo++;
            }
            if ($cambiaUso) {
                $articulosUso++;
            }
            if (! $ejecutar || $tipoId === null || $usoId === null || (! $cambiaTipo && ! $cambiaUso)) {
                continue;
            }

            $clave = $tipoId.'|'.$usoId;
            $lotes[$clave][] = (int) $art->id;
        }

        if ($ejecutar) {
            foreach ($lotes as $clave => $listaIds) {
                [$tipoId, $usoId] = array_map('intval', explode('|', $clave));
                foreach (array_chunk($listaIds, 500) as $chunk) {
                    DB::table('articulo')->whereIn('id', $chunk)->update([
                        'tipoarticulo_id' => $tipoId,
                        'usoarticulo_id' => $usoId,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        $categoriasTipo = 0;
        if (Schema::hasColumn('categoria', 'tipoarticulo_id')) {
            foreach ($cats as $cat) {
                $nombre = (string) $cat->nombre;
                if (! isset(self::MAPA_CATEGORIA[$nombre])) {
                    continue;
                }
                $abrev = self::MAPA_CATEGORIA[$nombre][0];
                $tipoId = $ids['tipos'][$abrev] ?? null;
                if ($tipoId === null || (int) ($cat->tipoarticulo_id ?? 0) === (int) $tipoId) {
                    continue;
                }
                $categoriasTipo++;
                if ($ejecutar) {
                    DB::table('categoria')->where('id', $cat->id)->update([
                        'tipoarticulo_id' => $tipoId,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        arsort($porTipo);
        arsort($porUso);

        return [
            'articulos_tipo' => $articulosTipo,
            'articulos_uso' => $articulosUso,
            'categorias_tipo' => $categoriasTipo,
            'por_tipo' => $porTipo,
            'por_uso' => $porUso,
        ];
    }
}
