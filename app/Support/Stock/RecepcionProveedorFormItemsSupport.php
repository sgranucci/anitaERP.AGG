<?php

namespace App\Support\Stock;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;

class RecepcionProveedorFormItemsSupport
{
    /**
     * Repuebla ítems enviados en old() con SKU, descripción, depósito y flags de conversión
     * para que la grilla JS se renderice igual que tras precargar la OC.
     *
     * @param  list<array<string, mixed>>|array<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function enriquecerItemsParaVista(
        array $items,
        ?int $depositoCabeceraId = null,
        ?int $proveedorId = null,
        ?int $empresaId = null
    ): array {
        if ($items === []) {
            return [];
        }

        RecepcionProveedorDepositoSupport::reiniciarCache();

        $articuloIds = array_values(array_unique(array_filter(array_map(
            static fn (array $item): int => (int) ($item['articulo_id'] ?? 0),
            $items
        ))));

        $articulos = $articuloIds !== []
            ? Articulo::query()->whereIn('id', $articuloIds)->get()->keyBy('id')
            : collect();

        $depositoIds = [];
        if ($depositoCabeceraId !== null && $depositoCabeceraId > 0) {
            $depositoIds[] = $depositoCabeceraId;
        }
        foreach ($articulos as $articulo) {
            $depArt = (int) ($articulo->depositoentrega_id ?? 0);
            if ($depArt > 0) {
                $depositoIds[] = $depArt;
            }
        }
        $depositos = $depositoIds !== []
            ? Depmae::query()->whereIn('id', array_unique($depositoIds))->get()->keyBy('id')
            : collect();

        $enriquecidos = [];
        foreach (array_values($items) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $articuloId = (int) ($item['articulo_id'] ?? 0);
            $articulo = $articulos->get($articuloId);
            $proveedorLinea = $proveedorId ?? (int) ($item['_proveedor_id'] ?? 0);

            $depositoLineaId = (int) ($item['deposito_id'] ?? 0);
            if ($depositoLineaId <= 0 && $articulo !== null) {
                $depositoLineaId = RecepcionProveedorDepositoSupport::resolverDepositoLinea($depositoCabeceraId, $articulo);
            }
            $deposito = $depositoLineaId > 0 ? $depositos->get($depositoLineaId) : null;
            if ($deposito === null && $depositoLineaId > 0) {
                $deposito = Depmae::query()->find($depositoLineaId);
            }

            $coefProveedor = (float) ($item['coeficiente_proveedor'] ?? 0);
            if ($coefProveedor <= 0 && $articulo !== null && $proveedorLinea > 0) {
                $coefProveedor = RecepcionProveedorDepositoSupport::coeficienteProveedor(
                    $articuloId,
                    $proveedorLinea
                );
            }
            if ($coefProveedor <= 0) {
                $coefProveedor = (float) ($item['coeficienteconversion'] ?? 1) ?: 1;
            }

            $coefArticulo = (float) ($item['coeficiente_articulo'] ?? 0);
            if ($coefArticulo <= 0 && $articulo !== null) {
                $coefArticulo = (float) ($articulo->coeficienteconversion ?? 1) ?: 1;
            }

            $esFormula = ! empty($item['es_deposito_formula'])
                || RecepcionProveedorDepositoSupport::esDepositoFormula($deposito);
            $empresaLinea = $empresaId ?? (int) ($item['_empresa_id'] ?? 0);
            $insumo = ($esFormula && $articulo !== null && $depositoCabeceraId === null)
                ? RecepcionProveedorDepositoSupport::resolverArticuloInsumo($articulo, $empresaLinea > 0 ? $empresaLinea : null)
                : null;

            $enriquecidos[] = array_merge($item, [
                'moneda_id' => (int) ($item['moneda_id'] ?? 1) ?: 1,
                'cotizacion' => (float) ($item['cotizacion'] ?? 1) ?: 1,
                'sku' => $item['sku'] ?? ($articulo->sku ?? ''),
                'descripcion' => $item['descripcion'] ?? ($articulo->nombre ?? ''),
                'deposito_id' => $depositoLineaId > 0 ? $depositoLineaId : ($item['deposito_id'] ?? null),
                'deposito_nombre' => $item['deposito_nombre'] ?? ($deposito->nombre ?? ''),
                'depositoentrega_id' => $item['depositoentrega_id'] ?? ($articulo->depositoentrega_id ?? null),
                'coeficiente_proveedor' => $coefProveedor,
                'coeficiente_articulo' => $coefArticulo,
                'coeficienteconversion' => (float) ($item['coeficienteconversion'] ?? $coefProveedor) ?: $coefProveedor,
                'es_deposito_formula' => $esFormula,
                'articulo_stock_id' => $item['articulo_stock_id'] ?? $insumo?->id,
                'articulo_stock_sku' => $item['articulo_stock_sku'] ?? $insumo?->sku,
                'skualternativo' => $item['skualternativo'] ?? ($articulo->skualternativo ?? ''),
                'maneja_parte_unica' => array_key_exists('maneja_parte_unica', $item)
                    ? (bool) $item['maneja_parte_unica']
                    : RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($articulo),
                'tipo_linea' => $item['tipo_linea'] ?? RecepcionProveedorDiferenciaSupport::TIPO_OC,
            ]);
        }

        return $enriquecidos;
    }

    /** @return array{numero_oc: ?int, proveedor_nombre: ?string, proveedor_id: ?int, empresa_id: ?int} */
    public static function datosCabeceraDesdeOrdencompra(
        ?int $ordencompraId,
        ?string $proveedorNombreOld = null,
        mixed $numeroOcOld = null
    ): array {
        $numeroOc = is_numeric($numeroOcOld) ? (int) $numeroOcOld : null;
        $proveedorNombre = $proveedorNombreOld !== null && trim($proveedorNombreOld) !== ''
            ? trim($proveedorNombreOld)
            : null;
        $proveedorId = null;
        $empresaId = null;

        if ($ordencompraId !== null && $ordencompraId > 0) {
            $oc = Ordencompra::query()->with('proveedores')->find($ordencompraId);
            if ($oc !== null) {
                $numeroOc = $numeroOc ?? (int) $oc->numeroordencompra;
                $proveedorNombre = $proveedorNombre ?? optional($oc->proveedores)->nombre;
                $proveedorId = (int) $oc->proveedor_id;
                $empresaId = (int) $oc->empresa_id;
            }
        }

        return [
            'numero_oc' => $numeroOc,
            'proveedor_nombre' => $proveedorNombre,
            'proveedor_id' => $proveedorId,
            'empresa_id' => $empresaId,
        ];
    }

    /** @return array{codigo: string, nombre: string}|null */
    public static function depositoCabeceraDesdeId(?int $depositoId): ?array
    {
        if ($depositoId === null || $depositoId <= 0) {
            return null;
        }

        $deposito = Depmae::query()->find($depositoId);
        if ($deposito === null) {
            return null;
        }

        return [
            'codigo' => (string) ($deposito->codigo ?? ''),
            'nombre' => (string) ($deposito->nombre ?? ''),
        ];
    }
}
