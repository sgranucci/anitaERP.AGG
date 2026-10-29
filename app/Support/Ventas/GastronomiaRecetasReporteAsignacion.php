<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Decide si una receta entra al reporte según el artículo de venta asignado.
 *
 * Una receta está asignada cuando algún artículo apunta a ella (articulo.formula)
 * o la fórmula tiene articulo_id. Activo e inactivo se miran sobre esos artículos,
 * no sobre los insumos de las líneas. Si no hay ninguno, es una receta sin asignar.
 */
final class GastronomiaRecetasReporteAsignacion
{
    public const ESTADO_ACTIVO = 'Activo';

    public const ESTADO_INACTIVO = 'Inactivo';

    public const ESTADO_SIN_ASIGNAR = 'Sin asignar';

    /**
     * @param  list<array{id:int,estado:string,empresa_id:int,precio:float,descripcion:string,sku:string,categoria_id:int,subcategoria_id:int}>  $articulos
     * @param  array{activos:bool,inactivos:bool,sin_asignar:bool,empresa_id:int,categoria_id:int,subcategoria_id:int}  $filtros
     * @return array{incluye:bool,estado:string,articulo:array<string,mixed>|null}
     */
    public static function resolver(array $articulos, array $filtros): array
    {
        $vacio = ['incluye' => false, 'estado' => '', 'articulo' => null];
        $originales = self::unicos($articulos);
        $filtrados = self::aplicarEmpresaYRubro($originales, $filtros);

        if ($originales === []) {
            if (empty($filtros['sin_asignar'])) {
                return $vacio;
            }
            if ((int) ($filtros['categoria_id'] ?? 0) > 0 || (int) ($filtros['subcategoria_id'] ?? 0) > 0) {
                return $vacio;
            }

            return [
                'incluye' => true,
                'estado' => self::ESTADO_SIN_ASIGNAR,
                'articulo' => null,
            ];
        }

        if ($filtrados === []) {
            return $vacio;
        }

        $activos = array_values(array_filter(
            $filtrados,
            static fn (array $articulo): bool => self::esActivo($articulo['estado'] ?? ''),
        ));
        $inactivos = array_values(array_filter(
            $filtrados,
            static fn (array $articulo): bool => ! self::esActivo($articulo['estado'] ?? ''),
        ));

        if ($activos !== [] && ! empty($filtros['activos'])) {
            return [
                'incluye' => true,
                'estado' => self::ESTADO_ACTIVO,
                'articulo' => self::preferido($activos),
            ];
        }

        if ($activos === [] && $inactivos !== [] && ! empty($filtros['inactivos'])) {
            return [
                'incluye' => true,
                'estado' => self::ESTADO_INACTIVO,
                'articulo' => self::preferido($inactivos),
            ];
        }

        return $vacio;
    }

    public static function esActivo(string $estado): bool
    {
        return strtoupper(trim($estado)) !== 'INACTIVO';
    }

    /**
     * @param  list<array<string, mixed>>  $articulos
     * @return list<array<string, mixed>>
     */
    private static function unicos(array $articulos): array
    {
        $porId = [];
        foreach ($articulos as $articulo) {
            $id = (int) ($articulo['id'] ?? 0);
            if ($id <= 0 || isset($porId[$id])) {
                continue;
            }
            $porId[$id] = $articulo;
        }

        return array_values($porId);
    }

    /**
     * @param  list<array<string, mixed>>  $articulos
     * @param  array{empresa_id?:int,categoria_id?:int,subcategoria_id?:int}  $filtros
     * @return list<array<string, mixed>>
     */
    private static function aplicarEmpresaYRubro(array $articulos, array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $categoriaId = (int) ($filtros['categoria_id'] ?? 0);
        $subcategoriaId = (int) ($filtros['subcategoria_id'] ?? 0);

        return array_values(array_filter($articulos, static function (array $articulo) use ($empresaId, $categoriaId, $subcategoriaId): bool {
            if ($empresaId > 0 && (int) ($articulo['empresa_id'] ?? 0) !== $empresaId) {
                return false;
            }
            if ($categoriaId > 0 && (int) ($articulo['categoria_id'] ?? 0) !== $categoriaId) {
                return false;
            }
            if ($subcategoriaId > 0 && (int) ($articulo['subcategoria_id'] ?? 0) !== $subcategoriaId) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $articulos
     * @return array<string, mixed>
     */
    private static function preferido(array $articulos): array
    {
        usort($articulos, static function (array $a, array $b): int {
            $porPrecio = ((float) ($b['precio'] ?? 0)) <=> ((float) ($a['precio'] ?? 0));
            if ($porPrecio !== 0) {
                return $porPrecio;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $articulos[0];
    }
}
