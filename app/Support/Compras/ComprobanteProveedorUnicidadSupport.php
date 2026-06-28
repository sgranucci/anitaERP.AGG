<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use RuntimeException;

/**
 * Unicidad de comprobante por empresa + tipo + letra + sucursal + número + CUIT (11 dígitos).
 * Cruza proveedor del maestro (proveedor_id) y eventual (proveedor_documento_eventual).
 */
final class ComprobanteProveedorUnicidadSupport
{
    public static function normalizarCuitDigitos(?string $cuit): string
    {
        $digits = preg_replace('/\D/', '', trim((string) $cuit)) ?? '';

        return strlen($digits) === 11 ? $digits : '';
    }

    public static function resolverCuitDigitos(?int $proveedorId, ?string $documentoEventual): string
    {
        if ($proveedorId !== null && $proveedorId > 0) {
            $nro = Proveedor::query()->whereKey($proveedorId)->value('nroinscripcion');

            return self::normalizarCuitDigitos(is_string($nro) ? $nro : null);
        }

        return self::normalizarCuitDigitos($documentoEventual);
    }

    public static function cuitDesdeComprobante(Comprobante_Proveedor $comprobante): string
    {
        $comprobante->loadMissing('proveedores');
        $doc = $comprobante->proveedores?->nroinscripcion ?? $comprobante->proveedor_documento_eventual;

        return self::normalizarCuitDigitos(is_string($doc) ? $doc : null);
    }

    /**
     * @throws RuntimeException
     */
    public static function assertUnico(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?int $proveedorId,
        ?string $documentoEventual,
        ?int $excluirComprobanteId = null,
        ?int $excluirPrecargaId = null,
    ): void {
        $cuit = self::resolverCuitDigitos($proveedorId, $documentoEventual);
        if ($cuit === '') {
            throw new RuntimeException(
                'Debe indicar un CUIT válido (11 dígitos) del proveedor para registrar el comprobante.'
            );
        }

        $duplicado = self::findDuplicado(
            $empresaId,
            $tipotransaccionCompraId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirComprobanteId,
        );

        if ($duplicado !== null) {
            throw new RuntimeException(self::mensajeDuplicado($duplicado));
        }

        $duplicadoPrecarga = self::findDuplicadoPrecarga(
            $empresaId,
            $tipotransaccionCompraId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirPrecargaId,
        );

        if ($duplicadoPrecarga !== null) {
            throw new RuntimeException(self::mensajeDuplicadoPrecarga($duplicadoPrecarga));
        }
    }

    /**
     * Valida unicidad al grabar precarga (API agente IA / pantalla precarga) contra precarga y comprobante definitivo.
     *
     * @throws RuntimeException
     */
    public static function assertUnicoPrecarga(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        int $proveedorId,
        ?int $excluirPrecargaId = null,
    ): void {
        $cuit = self::resolverCuitDigitos($proveedorId, null);
        if ($cuit === '') {
            throw new RuntimeException(
                'Debe indicar un proveedor con CUIT válido (11 dígitos) para registrar la precarga.'
            );
        }

        $duplicadoComprobante = self::findDuplicado(
            $empresaId,
            $tipotransaccionCompraId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
        );
        if ($duplicadoComprobante !== null) {
            throw new RuntimeException(self::mensajeDuplicado($duplicadoComprobante));
        }

        $duplicadoPrecarga = self::findDuplicadoPrecarga(
            $empresaId,
            $tipotransaccionCompraId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirPrecargaId,
        );
        if ($duplicadoPrecarga !== null) {
            throw new RuntimeException(self::mensajeDuplicadoPrecarga($duplicadoPrecarga));
        }
    }

    public static function findDuplicadoPrecarga(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirPrecargaId = null,
    ): ?Precarga_Comprobante_Proveedor {
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        if ($cuitDigitos === '') {
            return null;
        }

        $letra = strtoupper(substr(trim($letra), 0, 1));

        $query = Precarga_Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores'])
            ->where('empresa_id', $empresaId)
            ->where('tipotransaccion_compra_id', $tipotransaccionCompraId)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numerocomprobante', $numerocomprobante)
            ->where(function ($q) use ($cuitDigitos): void {
                $q->where('identificacion_proveedor_cuit', $cuitDigitos)
                    ->orWhereHas('proveedores', function ($p) use ($cuitDigitos): void {
                        $p->whereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), ' ', ''), '.', ''), '/', '') = ?",
                            [$cuitDigitos],
                        );
                    });
            });

        if ($excluirPrecargaId !== null && $excluirPrecargaId > 0) {
            $query->where('id', '!=', $excluirPrecargaId);
        }

        return $query->first();
    }

    public static function mensajeDuplicadoPrecarga(
        Precarga_Comprobante_Proveedor $existente,
        ?string $tipoAbreviatura = null,
    ): string {
        $existente->loadMissing('tipotransaccion_compras', 'proveedores');

        $tipo = strtoupper($tipoAbreviatura ?? (string) ($existente->tipotransaccion_compras?->abreviatura ?? 'FAC'));
        $comprobante = trim(sprintf(
            '%s %s %s-%s',
            $tipo,
            strtoupper((string) $existente->letra),
            $existente->sucursal,
            $existente->numerocomprobante,
        ));

        $cuit = self::normalizarCuitDigitos($existente->identificacion_proveedor_cuit ?? $existente->proveedores?->nroinscripcion);
        $cuitFmt = $cuit !== '' ? self::formatearCuit($cuit) : 'sin CUIT';
        $oc = trim((string) ($existente->numeroordencompra ?? ''));
        $detalleOc = $oc !== '' ? ', OC '.$oc : '';

        return sprintf(
            'Factura duplicada: ya existe una precarga %s para el CUIT %s (id %d%s).',
            $comprobante,
            $cuitFmt,
            $existente->id,
            $detalleOc,
        );
    }

    public static function findDuplicado(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirComprobanteId = null,
    ): ?Comprobante_Proveedor {
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        if ($cuitDigitos === '') {
            return null;
        }

        $letra = strtoupper(substr(trim($letra), 0, 1));

        $query = Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores'])
            ->whereNull('deleted_at')
            ->where('empresa_id', $empresaId)
            ->where('tipotransaccion_compra_id', $tipotransaccionCompraId)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numerocomprobante', $numerocomprobante)
            ->where(function ($q) use ($cuitDigitos): void {
                $q->where('identificacion_proveedor_cuit', $cuitDigitos)
                    ->orWhereHas('proveedores', function ($p) use ($cuitDigitos): void {
                        $p->whereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), ' ', ''), '.', ''), '/', '') = ?",
                            [$cuitDigitos],
                        );
                    })
                    ->orWhere('proveedor_documento_eventual', $cuitDigitos)
                    ->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(proveedor_documento_eventual, '-', ''), ' ', ''), '.', '') = ?",
                        [$cuitDigitos],
                    );
            });

        if ($excluirComprobanteId !== null && $excluirComprobanteId > 0) {
            $query->where('id', '!=', $excluirComprobanteId);
        }

        return $query->first();
    }

    public static function mensajeDuplicado(Comprobante_Proveedor $existente): string
    {
        $existente->loadMissing('tipotransaccion_compras');

        $tipo = strtoupper((string) ($existente->tipotransaccion_compras?->abreviatura ?? 'FAC'));
        $comprobante = trim(sprintf(
            '%s %s %s-%s',
            $tipo,
            strtoupper((string) $existente->letra),
            $existente->sucursal,
            $existente->numerocomprobante,
        ));

        $modulo = match ($existente->origen_entrada) {
            ComprobanteProveedorOrigenEntrada::INGRESO_EGRESO => 'ingresos y egresos',
            default => 'cuentas a pagar',
        };

        $cuit = self::cuitDesdeComprobante($existente);
        $cuitFmt = $cuit !== '' ? self::formatearCuit($cuit) : 'sin CUIT';

        return sprintf(
            'Comprobante duplicado: ya existe %s para el CUIT %s (registro #%d en %s).',
            $comprobante,
            $cuitFmt,
            $existente->id,
            $modulo,
        );
    }

    public static function formatearCuit(string $cuitDigitos): string
    {
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        if ($cuitDigitos === '') {
            return '';
        }

        return substr($cuitDigitos, 0, 2).'-'.substr($cuitDigitos, 2, 8).'-'.substr($cuitDigitos, 10, 1);
    }
}
