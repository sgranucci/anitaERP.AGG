<?php

namespace App\Support\Compras;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Proveedor;
use App\Support\Stock\ArticuloSeleccionOperativaSupport;
use Illuminate\Support\Facades\DB;

class ArticuloProveedorMatchSupport
{
    public const MATCH_CATALOGO_CODIGO = 'catalogo_codigo_articulo_proveedor';

    public const MATCH_CATALOGO_CODIGOBARRA = 'catalogo_codigobarra';

    public const MATCH_ARTICULO_CODIGOBARRA = 'articulo_codigobarra';

    public const MATCH_LISTA_VIGENTE_CODIGO = 'lista_vigente_codigo_articulo_proveedor';

    /**
     * @return array<string, mixed>|null
     */
    public static function resolver(
        int $proveedorId,
        ?string $codigoArticuloProveedor = null,
        ?string $codigobarra = null,
        ?string $fechaRef = null
    ): ?array {
        if ($proveedorId <= 0) {
            return null;
        }

        $codigo = self::normalizar($codigoArticuloProveedor);
        $barra = self::normalizar($codigobarra);

        if ($codigo === null && $barra === null) {
            return null;
        }

        if ($codigo !== null) {
            $porCatalogo = self::matchPorCatalogoCodigo($proveedorId, $codigo);
            if ($porCatalogo !== null) {
                return $porCatalogo;
            }

            $porLista = self::matchPorListaVigenteCodigo($proveedorId, $codigo, $fechaRef);
            if ($porLista !== null) {
                return $porLista;
            }
        }

        if ($barra !== null) {
            $porCatalogoBarra = self::matchPorCatalogoCodigobarra($proveedorId, $barra);
            if ($porCatalogoBarra !== null) {
                return $porCatalogoBarra;
            }

            $porArticuloBarra = self::matchPorArticuloCodigobarra($barra);
            if ($porArticuloBarra !== null) {
                return $porArticuloBarra;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function matchPorCatalogoCodigo(int $proveedorId, string $codigo): ?array
    {
        $fila = Articulo_Proveedor::query()
            ->with(['articulos', 'proveedores'])
            ->where('proveedor_id', $proveedorId)
            ->where('codigo_articulo_proveedor', $codigo)
            ->where('activo', true)
            ->orderByDesc('preferido')
            ->orderBy('id')
            ->first();

        if (! $fila) {
            return null;
        }

        return self::armarResultado(
            self::MATCH_CATALOGO_CODIGO,
            (int) $fila->articulo_id,
            $fila
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function matchPorCatalogoCodigobarra(int $proveedorId, string $barra): ?array
    {
        $fila = Articulo_Proveedor::query()
            ->with(['articulos', 'proveedores'])
            ->where('proveedor_id', $proveedorId)
            ->where('codigobarra', $barra)
            ->where('activo', true)
            ->orderByDesc('preferido')
            ->orderBy('id')
            ->first();

        if (! $fila) {
            return null;
        }

        return self::armarResultado(
            self::MATCH_CATALOGO_CODIGOBARRA,
            (int) $fila->articulo_id,
            $fila
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function matchPorArticuloCodigobarra(string $barra): ?array
    {
        $articulo = ArticuloSeleccionOperativaSupport::aplicarSoloActivosTablaArticulo(
            Articulo::query()->where('codigobarra', $barra)
        )
            ->orderBy('id')
            ->first();

        if (! $articulo) {
            return null;
        }

        return [
            'articulo_id' => (int) $articulo->id,
            'articulo_sku' => (string) ($articulo->sku ?? ''),
            'articulo_descripcion' => (string) ($articulo->descripcion ?? ''),
            'articulo_proveedor_id' => null,
            'proveedor_id' => null,
            'codigo_articulo_proveedor' => null,
            'codigobarra' => $barra,
            'metodo_match' => self::MATCH_ARTICULO_CODIGOBARRA,
            'coeficiente_conversion' => null,
            'unidadmedida_compra_id' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function matchPorListaVigenteCodigo(int $proveedorId, string $codigo, ?string $fechaRef): ?array
    {
        $candidatos = DB::table('listaprecio_proveedor_articulo as lpa')
            ->join('listaprecio_proveedor as lp', 'lp.id', '=', 'lpa.listaprecio_proveedor_id')
            ->where('lp.proveedor_id', $proveedorId)
            ->where('lp.estado', 'ACTIVA')
            ->where('lpa.codigo_articulo_proveedor', $codigo)
            ->distinct()
            ->orderBy('lpa.articulo_id')
            ->pluck('lpa.articulo_id');

        $fechaRef = $fechaRef ?? date('Y-m-d');
        $mejor = null;

        foreach ($candidatos as $articuloId) {
            $articuloId = (int) $articuloId;
            if ($articuloId <= 0) {
                continue;
            }

            $vigente = ArticuloProveedorPrecioListaSupport::precioVigente(
                $articuloId,
                $proveedorId,
                null,
                $fechaRef
            );

            if (! $vigente) {
                continue;
            }

            if (self::normalizar($vigente['codigo_articulo_proveedor'] ?? null) !== $codigo) {
                continue;
            }

            $fila = Articulo_Proveedor::query()
                ->where('articulo_id', $articuloId)
                ->where('proveedor_id', $proveedorId)
                ->first();

            $prioridad = ($fila && $fila->preferido ? 2 : 0) + ($fila && $fila->activo ? 1 : 0);

            if ($mejor === null || $prioridad > $mejor['prioridad']) {
                $mejor = [
                    'prioridad' => $prioridad,
                    'articulo_id' => $articuloId,
                    'fila' => $fila,
                    'vigente' => $vigente,
                ];
            }
        }

        if ($mejor === null) {
            return null;
        }

        if ($mejor['fila']) {
            return self::armarResultado(
                self::MATCH_LISTA_VIGENTE_CODIGO,
                $mejor['articulo_id'],
                $mejor['fila'],
                $mejor['vigente']
            );
        }

        $articulo = Articulo::query()->find($mejor['articulo_id']);
        if (! ArticuloSeleccionOperativaSupport::esSeleccionable($articulo)) {
            return null;
        }

        return [
            'articulo_id' => $mejor['articulo_id'],
            'articulo_sku' => (string) ($articulo->sku ?? ''),
            'articulo_descripcion' => (string) ($articulo->descripcion ?? ''),
            'articulo_proveedor_id' => null,
            'proveedor_id' => $proveedorId,
            'codigo_articulo_proveedor' => $codigo,
            'codigobarra' => null,
            'metodo_match' => self::MATCH_LISTA_VIGENTE_CODIGO,
            'coeficiente_conversion' => null,
            'unidadmedida_compra_id' => null,
            'precio_vigente' => $mejor['vigente']['precio'] ?? null,
            'moneda_abreviatura' => $mejor['vigente']['moneda_abreviatura'] ?? '',
            'fechavigencia_lista' => $mejor['vigente']['fechavigencia'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $vigente
     * @return array<string, mixed>|null
     */
    private static function armarResultado(
        string $metodo,
        int $articuloId,
        Articulo_Proveedor $fila,
        ?array $vigente = null
    ): ?array {
        $articulo = $fila->articulos;
        if (! ArticuloSeleccionOperativaSupport::esSeleccionable($articulo)) {
            return null;
        }

        $resultado = [
            'articulo_id' => $articuloId,
            'articulo_sku' => (string) ($articulo->sku ?? ''),
            'articulo_descripcion' => (string) ($articulo->descripcion ?? ''),
            'articulo_proveedor_id' => (int) $fila->id,
            'proveedor_id' => (int) $fila->proveedor_id,
            'codigo_articulo_proveedor' => self::normalizar($fila->codigo_articulo_proveedor),
            'codigobarra' => self::normalizar($fila->codigobarra),
            'metodo_match' => $metodo,
            'coeficiente_conversion' => (float) ($fila->coeficiente_conversion ?? 1),
            'unidadmedida_compra_id' => $fila->unidadmedida_compra_id ? (int) $fila->unidadmedida_compra_id : null,
            'activo' => (bool) $fila->activo,
            'preferido' => (bool) $fila->preferido,
        ];

        if ($vigente === null && $articuloId > 0 && $fila->proveedor_id) {
            $vigente = ArticuloProveedorPrecioListaSupport::precioVigente($articuloId, (int) $fila->proveedor_id);
        }

        if ($vigente) {
            $resultado['precio_vigente'] = $vigente['precio'] ?? null;
            $resultado['moneda_abreviatura'] = $vigente['moneda_abreviatura'] ?? '';
            $resultado['fechavigencia_lista'] = $vigente['fechavigencia'] ?? '';
        }

        return $resultado;
    }

    private static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }
}
