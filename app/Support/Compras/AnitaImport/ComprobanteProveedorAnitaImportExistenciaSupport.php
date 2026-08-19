<?php

namespace App\Support\Compras\AnitaImport;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;

/**
 * Detecta facturas ya cargadas en ERP para no reimportarlas.
 *
 * @phpstan-type Existente array{id: int, motivo: string, etiqueta: string}
 */
final class ComprobanteProveedorAnitaImportExistenciaSupport
{
    /**
     * @return array{
     *   por_interno: array<int, int>,
     *   por_clave: array<string, int>,
     *   ids: list<int>
     * }
     */
    public static function indexarProveedor(int $proveedorId): array
    {
        $filas = Comprobante_Proveedor::query()
            ->with('tipotransaccion_compras:id,abreviatura')
            ->where('proveedor_id', $proveedorId)
            ->where(function ($q): void {
                $q->whereNull('estado')
                    ->orWhere('estado', '!=', ComprobanteProveedorEstados::ANULADO);
            })
            ->get([
                'id',
                'empresa_id',
                'proveedor_id',
                'tipotransaccion_compra_id',
                'letra',
                'sucursal',
                'numerocomprobante',
                'anita_nro_interno',
            ]);

        $porInterno = [];
        $porClave = [];
        $ids = [];
        foreach ($filas as $fila) {
            $id = (int) $fila->id;
            $ids[] = $id;
            $nroInterno = (int) ($fila->anita_nro_interno ?? 0);
            if ($nroInterno > 0) {
                $porInterno[$nroInterno] = $id;
            }
            $tipo = (string) ($fila->tipotransaccion_compras?->abreviatura ?? '');
            if ($tipo === '') {
                continue;
            }
            $clave = ComprobanteProveedorAnitaImportClaveSupport::claveDocumento(
                $tipo,
                (string) $fila->letra,
                (int) $fila->sucursal,
                (int) $fila->numerocomprobante,
            );
            $porClave[self::claveEmpresa((int) $fila->empresa_id, $clave)] = $id;
            $porClave[$clave] = $id;
        }

        return [
            'por_interno' => $porInterno,
            'por_clave' => $porClave,
            'ids' => $ids,
        ];
    }

    /**
     * @param  array{por_interno: array<int, int>, por_clave: array<string, int>, ids: list<int>}  $indice
     * @param  array<string, mixed>  $compra
     */
    public static function buscarEnIndice(
        array $indice,
        array $compra,
        int $empresaId,
        ?int $tipotransaccionCompraId,
        string $cuitDigitos,
    ): ?array {
        $hit = self::buscarSoloIndice($indice, $compra, $empresaId);
        if ($hit !== null) {
            return $hit;
        }

        if ($tipotransaccionCompraId && $tipotransaccionCompraId > 0 && $cuitDigitos !== '') {
            $dup = ComprobanteProveedorUnicidadSupport::findDuplicadoPorAfip(
                $empresaId,
                ComprobanteProveedorUnicidadSupport::codigoAfipDesdeTipoId($tipotransaccionCompraId),
                (string) ($compra['com_letra'] ?? ''),
                (int) ($compra['com_sucursal'] ?? 0),
                (int) ($compra['com_nro'] ?? 0),
                $cuitDigitos,
            );
            if ($dup !== null) {
                return [
                    'id' => (int) $dup->id,
                    'motivo' => 'unicidad_afip',
                    'etiqueta' => self::etiquetaCompra($compra),
                ];
            }
        }

        return null;
    }

    /**
     * Variante pura (tests): solo índice, sin consultar AFIP.
     *
     * @param  array{por_interno: array<int, int>, por_clave: array<string, int>}  $indice
     * @param  array<string, mixed>  $compra
     */
    public static function buscarSoloIndice(array $indice, array $compra, int $empresaId): ?array
    {
        $nroInterno = (int) ($compra['com_nro_interno'] ?? 0);
        if ($nroInterno > 0 && isset($indice['por_interno'][$nroInterno])) {
            return [
                'id' => $indice['por_interno'][$nroInterno],
                'motivo' => 'anita_nro_interno',
                'etiqueta' => self::etiquetaCompra($compra),
            ];
        }

        $clave = ComprobanteProveedorAnitaImportClaveSupport::claveDocumento(
            (string) ($compra['com_tipo'] ?? ''),
            (string) ($compra['com_letra'] ?? ''),
            (int) ($compra['com_sucursal'] ?? 0),
            (int) ($compra['com_nro'] ?? 0),
        );
        $claveEmpresa = self::claveEmpresa($empresaId, $clave);
        if (isset($indice['por_clave'][$claveEmpresa])) {
            return [
                'id' => $indice['por_clave'][$claveEmpresa],
                'motivo' => 'clave',
                'etiqueta' => self::etiquetaCompra($compra),
            ];
        }
        if (isset($indice['por_clave'][$clave])) {
            return [
                'id' => $indice['por_clave'][$clave],
                'motivo' => 'clave',
                'etiqueta' => self::etiquetaCompra($compra),
            ];
        }

        return null;
    }

    public static function claveEmpresa(int $empresaId, string $clave): string
    {
        return $empresaId.'#'.$clave;
    }

    /**
     * @param  array<string, mixed>  $compra
     */
    public static function etiquetaCompra(array $compra): string
    {
        return ComprobanteProveedorAnitaImportClaveSupport::etiqueta(
            (string) ($compra['com_tipo'] ?? ''),
            (string) ($compra['com_letra'] ?? ''),
            (int) ($compra['com_sucursal'] ?? 0),
            (int) ($compra['com_nro'] ?? 0),
        );
    }
}
