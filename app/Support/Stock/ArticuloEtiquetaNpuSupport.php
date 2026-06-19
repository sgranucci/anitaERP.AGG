<?php

namespace App\Support\Stock;

use App\Models\Compras\Proveedor;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_ParteUnica;
use App\Models\Stock\Articulo_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_ParteUnica;
use RuntimeException;

/**
 * Datos de etiqueta por NPU: ERP local primero; si falta, recpunica / stk_parte_unica en Anita
 * (mismo criterio que recepción de proveedores e import recpunica).
 */
class ArticuloEtiquetaNpuSupport
{
    /**
     * @return array{
     *     numeroparte: int,
     *     sku: string,
     *     codigoproveedor: string,
     *     numerorecepcion: string,
     *     nombre_proveedor: string,
     *     origen: string,
     * }
     */
    public static function resolver(int $articuloId, int $numeroparte): array
    {
        if ($numeroparte <= 0) {
            throw new RuntimeException('Indique un NPU válido.');
        }

        $articulo = Articulo::query()->find($articuloId);
        if ($articulo === null) {
            throw new RuntimeException('Artículo no encontrado.');
        }

        if (! RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($articulo)) {
            throw new RuntimeException('El artículo no lleva número de parte única.');
        }

        $apu = Articulo_ParteUnica::query()->where('numeroparte', $numeroparte)->first();
        if ($apu !== null && (int) $apu->articulo_id !== $articuloId) {
            throw new RuntimeException("El NPU {$numeroparte} pertenece a otro artículo.");
        }

        $desdeErp = self::resolverDesdeErp($articulo, $numeroparte);
        if ($desdeErp !== null) {
            return $desdeErp;
        }

        $desdeAnita = self::resolverDesdeAnita($articulo, $numeroparte);
        if ($desdeAnita !== null) {
            return $desdeAnita;
        }

        throw new RuntimeException("No se encontró el NPU {$numeroparte} en anitaERP ni en Anita.");
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resolverDesdeErp(Articulo $articulo, int $numeroparte): ?array
    {
        $vinculo = Recepcion_Proveedor_ParteUnica::query()
            ->where('numeroparte', $numeroparte)
            ->with([
                'recepcion_proveedores.proveedores',
            ])
            ->first();

        if ($vinculo === null) {
            $apu = Articulo_ParteUnica::query()->where('numeroparte', $numeroparte)->first();
            if ($apu === null) {
                return null;
            }

            return self::armarResultado(
                $articulo,
                $numeroparte,
                null,
                null,
                'erp',
            );
        }

        $recepcion = $vinculo->recepcion_proveedores;
        $proveedorId = (int) ($recepcion?->proveedor_id ?? 0);
        $numerorecepcion = (int) ($recepcion?->numerorecepcion ?? 0);
        $nombreProveedor = trim((string) ($recepcion?->proveedores?->nombre ?? ''));

        return self::armarResultado(
            $articulo,
            $numeroparte,
            $proveedorId > 0 ? $proveedorId : null,
            $numerorecepcion > 0 ? (string) $numerorecepcion : '',
            'erp',
            $nombreProveedor,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resolverDesdeAnita(Articulo $articulo, int $numeroparte): ?array
    {
        $filasRecpunica = RecpunicaAnitaBridgeSupport::listarDesdeAnita(
            ' WHERE recpu_id = '.(int) $numeroparte,
            'FIRST 1',
        );

        if ($filasRecpunica !== []) {
            $fila = $filasRecpunica[0];
            $skuAnita = trim((string) ($fila->recpu_articulo ?? ''));
            if (! self::skuCoincide($articulo, $skuAnita)) {
                throw new RuntimeException("El NPU {$numeroparte} en Anita corresponde a otro artículo ({$skuAnita}).");
            }

            $tipo = trim((string) ($fila->recpu_tipo ?? 'COM'));
            $letra = trim((string) ($fila->recpu_letra ?? 'X'));
            $sucursal = (int) ($fila->recpu_sucursal ?? 0);
            $nroRec = (int) ($fila->recpu_nro ?? 0);

            $proveedorId = null;
            $nombreProveedor = '';
            if ($sucursal > 0 && $nroRec > 0) {
                $cabecera = RecepcionProveedorAnitaImportSupport::listarRecepmaePorClave($tipo, $letra, $sucursal, $nroRec);
                if ($cabecera !== null) {
                    $proveedorId = self::resolverProveedorIdDesdeAnita((string) ($cabecera->recm_proveedor ?? ''));
                    $nombreProveedor = trim((string) ($cabecera->recm_proveedor_nombre ?? ''));
                    if ($nombreProveedor === '' && $proveedorId !== null) {
                        $nombreProveedor = trim((string) (Proveedor::query()->whereKey($proveedorId)->value('nombre') ?? ''));
                    }
                }
            }

            return self::armarResultado(
                $articulo,
                $numeroparte,
                $proveedorId,
                $nroRec > 0 ? (string) $nroRec : '',
                'anita.recpunica',
                $nombreProveedor,
            );
        }

        $filasStk = StkParteUnicaAnitaBridgeSupport::listarDesdeAnita(
            ' WHERE stkpu_id = '.(int) $numeroparte,
            'FIRST 1',
        );

        if ($filasStk === []) {
            return null;
        }

        $filaStk = $filasStk[0];
        $skuAnita = trim((string) ($filaStk->stkpu_articulo ?? ''));
        if (! self::skuCoincide($articulo, $skuAnita)) {
            throw new RuntimeException("El NPU {$numeroparte} en Anita corresponde a otro artículo ({$skuAnita}).");
        }

        return self::armarResultado(
            $articulo,
            $numeroparte,
            null,
            '',
            'anita.stk_parte_unica',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function armarResultado(
        Articulo $articulo,
        int $numeroparte,
        ?int $proveedorId,
        string $numerorecepcion,
        string $origen,
        string $nombreProveedor = '',
    ): array {
        return [
            'numeroparte' => $numeroparte,
            'sku' => trim((string) ($articulo->sku ?? '')),
            'codigoproveedor' => self::codigoProveedorParaEtiqueta((int) $articulo->id, $proveedorId, $articulo),
            'numerorecepcion' => $numerorecepcion,
            'nombre_proveedor' => $nombreProveedor,
            'origen' => $origen,
        ];
    }

    private static function codigoProveedorParaEtiqueta(int $articuloId, ?int $proveedorId, Articulo $articulo): string
    {
        if ($proveedorId !== null && $proveedorId > 0) {
            $codigo = Articulo_Proveedor::query()
                ->where('articulo_id', $articuloId)
                ->where('proveedor_id', $proveedorId)
                ->where('activo', true)
                ->orderByDesc('preferido')
                ->value('codigo_articulo_proveedor');

            if ($codigo !== null && trim((string) $codigo) !== '') {
                return trim((string) $codigo);
            }
        }

        return trim((string) ($articulo->skuproveedor ?? ''));
    }

    private static function resolverProveedorIdDesdeAnita(string $codigoAnita): ?int
    {
        $codigoNorm = ltrim(trim($codigoAnita), '0');
        if ($codigoNorm === '') {
            return null;
        }

        $id = (int) (Proveedor::query()
            ->where('codigo', $codigoNorm)
            ->orWhere('codigo', str_pad($codigoNorm, 6, '0', STR_PAD_LEFT))
            ->value('id') ?? 0);

        return $id > 0 ? $id : null;
    }

    private static function skuCoincide(Articulo $articulo, string $skuAnita): bool
    {
        $skuErp = ltrim(trim((string) ($articulo->sku ?? '')), '0');
        $skuAnitaNorm = ltrim(trim($skuAnita), '0');

        if ($skuErp === '' || $skuAnitaNorm === '') {
            return false;
        }

        return $skuErp === $skuAnitaNorm;
    }
}
