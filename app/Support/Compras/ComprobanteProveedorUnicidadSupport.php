<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Support\Database\DbContencionSupport;
use RuntimeException;

/**
 * Unicidad de factura de proveedor (cualquier origen):
 * empresa + CUIT proveedor + código AFIP (01/02/…) + letra + sucursal + número.
 * Si el código de autorización es CAE/CAI (no CAEA), también se controla CUIT + número CAE.
 */
final class ComprobanteProveedorUnicidadSupport
{
    public static function normalizarCuitDigitos(?string $cuit): string
    {
        $digits = preg_replace('/\D/', '', trim((string) $cuit)) ?? '';

        return strlen($digits) === 11 ? $digits : '';
    }

    public static function normalizarCodigoAfip(?string $codigo): string
    {
        $digits = preg_replace('/\D/', '', trim((string) $codigo)) ?? '';
        if ($digits === '') {
            return '';
        }

        return (string) (int) $digits;
    }

    public static function normalizarNumeroCae(?string $numerocae): string
    {
        return preg_replace('/\D/', '', trim((string) $numerocae)) ?? '';
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

    public static function codigoAfipDesdeTipoId(int $tipotransaccionCompraId): string
    {
        if ($tipotransaccionCompraId <= 0) {
            return '';
        }

        $codigo = Tipotransaccion_Compra::query()->whereKey($tipotransaccionCompraId)->value('codigoafip');

        return self::normalizarCodigoAfip(is_string($codigo) ? $codigo : null);
    }

    /** @var array<string, list<int>> */
    private static array $cacheTiposPorAfip = [];

    /** @return list<int> */
    public static function tipotransaccionIdsPorCodigoAfip(string $codigoAfipNorm): array
    {
        if ($codigoAfipNorm === '') {
            return [];
        }

        if (isset(self::$cacheTiposPorAfip[$codigoAfipNorm])) {
            return self::$cacheTiposPorAfip[$codigoAfipNorm];
        }

        self::$cacheTiposPorAfip[$codigoAfipNorm] = Tipotransaccion_Compra::query()
            ->get(['id', 'codigoafip'])
            ->filter(fn ($t) => self::normalizarCodigoAfip((string) $t->codigoafip) === $codigoAfipNorm)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return self::$cacheTiposPorAfip[$codigoAfipNorm];
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
        ?string $numerocae = null,
        ?string $tipoAutorizacion = null,
    ): void {
        $cuit = self::resolverCuitDigitos($proveedorId, $documentoEventual);
        if ($cuit === '') {
            throw new RuntimeException(
                'Debe indicar un CUIT válido (11 dígitos) del proveedor para registrar el comprobante.'
            );
        }

        $codigoAfip = self::codigoAfipDesdeTipoId($tipotransaccionCompraId);
        if ($codigoAfip === '') {
            throw new RuntimeException(
                'El tipo de comprobante no tiene código AFIP configurado; no se puede controlar la unicidad fiscal.'
            );
        }

        $duplicado = self::findDuplicadoPorAfip(
            $empresaId,
            $codigoAfip,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirComprobanteId,
        );

        if ($duplicado !== null) {
            throw ComprobanteProveedorDuplicadoException::desdeExistente($duplicado, $codigoAfip);
        }

        $duplicadoClave = self::findDuplicadoPorClaveUnica(
            $empresaId,
            $tipotransaccionCompraId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirComprobanteId,
        );
        if ($duplicadoClave !== null) {
            throw ComprobanteProveedorDuplicadoException::desdeExistente($duplicadoClave, $codigoAfip);
        }

        $duplicadoPrecarga = self::findDuplicadoPrecargaPorAfip(
            $empresaId,
            $codigoAfip,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirPrecargaId,
        );

        if ($duplicadoPrecarga !== null) {
            throw new RuntimeException(self::mensajeDuplicadoPrecarga($duplicadoPrecarga, $codigoAfip));
        }

        self::assertUnicoPorCae(
            $empresaId,
            $cuit,
            $numerocae,
            $tipoAutorizacion,
            $excluirComprobanteId,
            $excluirPrecargaId,
        );
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
        ?string $numerocae = null,
        ?string $tipoAutorizacion = null,
    ): void {
        self::assertUnico(
            $empresaId,
            $tipotransaccionCompraId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $proveedorId,
            null,
            null,
            $excluirPrecargaId,
            $numerocae,
            $tipoAutorizacion,
        );
    }

    /**
     * @throws RuntimeException
     */
    public static function assertUnicoPorCae(
        int $empresaId,
        string $cuitDigitos,
        ?string $numerocae,
        ?string $tipoAutorizacion,
        ?int $excluirComprobanteId = null,
        ?int $excluirPrecargaId = null,
    ): void {
        if (! ComprobanteProveedorTipoAutorizacion::controlaUnicidadCodigo($tipoAutorizacion, $numerocae)) {
            return;
        }

        $cae = self::normalizarNumeroCae($numerocae);
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        if ($cae === '' || $cuitDigitos === '') {
            return;
        }

        $dupCp = self::findDuplicadoPorCae($empresaId, $cuitDigitos, $cae, $excluirComprobanteId);
        if ($dupCp !== null) {
            throw new RuntimeException(self::mensajeDuplicadoCaeComprobante($dupCp, $cae));
        }

        $dupPre = self::findDuplicadoPrecargaPorCae($empresaId, $cuitDigitos, $cae, $excluirPrecargaId);
        if ($dupPre !== null) {
            throw new RuntimeException(self::mensajeDuplicadoCaePrecarga($dupPre, $cae));
        }
    }

    public static function findDuplicadoPorAfip(
        int $empresaId,
        string $codigoAfipNorm,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirComprobanteId = null,
    ): ?Comprobante_Proveedor {
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        $codigoAfipNorm = self::normalizarCodigoAfip($codigoAfipNorm);
        $tipoIds = self::tipotransaccionIdsPorCodigoAfip($codigoAfipNorm);
        if ($cuitDigitos === '' || $codigoAfipNorm === '' || $tipoIds === []) {
            return null;
        }

        $letra = strtoupper(substr(trim($letra), 0, 1));

        $query = Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores'])
            ->where('empresa_id', $empresaId)
            ->whereIn('tipotransaccion_compra_id', $tipoIds)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numerocomprobante', $numerocomprobante)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhere('estado', '!=', ComprobanteProveedorEstados::ANULADO);
            })
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

    /** Compat: búsqueda por tipo interno (delega a AFIP). */
    public static function findDuplicado(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirComprobanteId = null,
    ): ?Comprobante_Proveedor {
        return self::findDuplicadoPorAfip(
            $empresaId,
            self::codigoAfipDesdeTipoId($tipotransaccionCompraId),
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuitDigitos,
            $excluirComprobanteId,
        );
    }

    /**
     * Misma clave que el índice único uq_comprobante_proveedor_por_cuit
     * (empresa + tipo interno + letra + sucursal + número + CUIT).
     */
    public static function findDuplicadoPorClaveUnica(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirComprobanteId = null,
    ): ?Comprobante_Proveedor {
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        if ($cuitDigitos === '' || $tipotransaccionCompraId <= 0) {
            return null;
        }

        $letra = strtoupper(substr(trim($letra), 0, 1));

        $query = Comprobante_Proveedor::query();

        $query->with(['tipotransaccion_compras', 'proveedores'])
            ->where('empresa_id', $empresaId)
            ->where('tipotransaccion_compra_id', $tipotransaccionCompraId)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numerocomprobante', $numerocomprobante)
            ->where('identificacion_proveedor_cuit', $cuitDigitos);

        if ($excluirComprobanteId !== null && $excluirComprobanteId > 0) {
            $query->where('id', '!=', $excluirComprobanteId);
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * Convierte violación de índice único (carrera) en mensaje de negocio.
     *
     * @throws ComprobanteProveedorDuplicadoException
     */
    public static function relevarViolacionUnicidad(
        \Throwable $e,
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?int $proveedorId,
        ?string $documentoEventual = null,
        ?int $excluirComprobanteId = null,
    ): void {
        if (! self::esViolacionUnicidadFiscal($e)) {
            return;
        }

        $cuit = self::resolverCuitDigitos($proveedorId, $documentoEventual);
        $dup = self::findDuplicadoPorClaveUnica(
            $empresaId,
            $tipotransaccionCompraId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirComprobanteId,
        );

        if ($dup !== null) {
            throw ComprobanteProveedorDuplicadoException::desdeExistente(
                $dup,
                self::codigoAfipDesdeTipoId($tipotransaccionCompraId),
            );
        }

        throw new RuntimeException(
            'Ya existe un comprobante con la misma empresa, tipo, letra, sucursal, número y CUIT. '
            .'Buscalo en Cuentas a pagar.'
        );
    }

    public static function esViolacionUnicidadFiscal(\Throwable $e): bool
    {
        return DbContencionSupport::esViolacionUnicidad(
            $e,
            'uq_comprobante_proveedor_por_cuit',
            'comprobante_proveedor',
        );
    }

    public static function findDuplicadoPrecargaPorAfip(
        int $empresaId,
        string $codigoAfipNorm,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirPrecargaId = null,
    ): ?Precarga_Comprobante_Proveedor {
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        $codigoAfipNorm = self::normalizarCodigoAfip($codigoAfipNorm);
        $tipoIds = self::tipotransaccionIdsPorCodigoAfip($codigoAfipNorm);
        if ($cuitDigitos === '' || $codigoAfipNorm === '' || $tipoIds === []) {
            return null;
        }

        $letra = strtoupper(substr(trim($letra), 0, 1));

        $query = Precarga_Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores'])
            ->where('empresa_id', $empresaId)
            ->whereIn('tipotransaccion_compra_id', $tipoIds)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numerocomprobante', $numerocomprobante)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
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

    public static function findDuplicadoPrecarga(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirPrecargaId = null,
    ): ?Precarga_Comprobante_Proveedor {
        return self::findDuplicadoPrecargaPorAfip(
            $empresaId,
            self::codigoAfipDesdeTipoId($tipotransaccionCompraId),
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuitDigitos,
            $excluirPrecargaId,
        );
    }

    public static function findDuplicadoPorCae(
        int $empresaId,
        string $cuitDigitos,
        string $caeDigitos,
        ?int $excluirComprobanteId = null,
    ): ?Comprobante_Proveedor {
        $query = Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores'])
            ->where('empresa_id', $empresaId)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhere('estado', '!=', ComprobanteProveedorEstados::ANULADO);
            })
            ->where(function ($q) {
                $q->whereNull('tipo_autorizacion')
                    ->orWhere('tipo_autorizacion', '!=', ComprobanteProveedorTipoAutorizacion::CAEA);
            })
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(numerocae,''), '-', ''), ' ', ''), '.', ''), '/', '') = ?",
                [$caeDigitos],
            )
            ->where(function ($q) use ($cuitDigitos): void {
                $q->where('identificacion_proveedor_cuit', $cuitDigitos)
                    ->orWhereHas('proveedores', function ($p) use ($cuitDigitos): void {
                        $p->whereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), ' ', ''), '.', ''), '/', '') = ?",
                            [$cuitDigitos],
                        );
                    });
            });

        if ($excluirComprobanteId !== null && $excluirComprobanteId > 0) {
            $query->where('id', '!=', $excluirComprobanteId);
        }

        return $query->first();
    }

    public static function findDuplicadoPrecargaPorCae(
        int $empresaId,
        string $cuitDigitos,
        string $caeDigitos,
        ?int $excluirPrecargaId = null,
    ): ?Precarga_Comprobante_Proveedor {
        $query = Precarga_Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores'])
            ->where('empresa_id', $empresaId)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->where(function ($q) {
                $q->whereNull('tipo_autorizacion')
                    ->orWhere('tipo_autorizacion', '!=', ComprobanteProveedorTipoAutorizacion::CAEA);
            })
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(numerocae,''), '-', ''), ' ', ''), '.', ''), '/', '') = ?",
                [$caeDigitos],
            )
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
        ?string $codigoAfip = null,
    ): string {
        $existente->loadMissing('tipotransaccion_compras', 'proveedores');

        $afip = $codigoAfip ?? self::normalizarCodigoAfip((string) ($existente->tipotransaccion_compras?->codigoafip ?? ''));
        $afipLabel = $afip !== '' ? str_pad($afip, 2, '0', STR_PAD_LEFT) : '??';
        $comprobante = trim(sprintf(
            'AFIP %s %s %s-%s',
            $afipLabel,
            strtoupper((string) $existente->letra),
            $existente->sucursal,
            $existente->numerocomprobante,
        ));

        $cuit = self::normalizarCuitDigitos($existente->identificacion_proveedor_cuit ?? $existente->proveedores?->nroinscripcion);
        $cuitFmt = $cuit !== '' ? self::formatearCuit($cuit) : 'sin CUIT';
        $oc = trim((string) ($existente->numeroordencompra ?? ''));
        $detalleOc = $oc !== '' ? ', OC '.$oc : '';
        $origen = PrecargaComprobanteOrigenEntrada::etiqueta($existente->origen_entrada ?? null);

        return sprintf(
            'Factura duplicada: ya existe una precarga %s para el CUIT %s (id %d, origen %s%s). No se puede cargar dos veces desde distintos orígenes.',
            $comprobante,
            $cuitFmt,
            $existente->id,
            $origen,
            $detalleOc,
        );
    }

    public static function mensajeDuplicado(Comprobante_Proveedor $existente, ?string $codigoAfip = null): string
    {
        $existente->loadMissing('tipotransaccion_compras');

        $afip = $codigoAfip ?? self::normalizarCodigoAfip((string) ($existente->tipotransaccion_compras?->codigoafip ?? ''));
        $afipLabel = $afip !== '' ? str_pad($afip, 2, '0', STR_PAD_LEFT) : '??';
        $abrev = strtoupper((string) ($existente->tipotransaccion_compras?->abreviatura ?? 'FAC'));
        $comprobante = trim(sprintf(
            '%s %s %s-%s (AFIP %s)',
            $abrev,
            strtoupper((string) $existente->letra),
            $existente->sucursal,
            $existente->numerocomprobante,
            $afipLabel,
        ));

        $cuit = self::cuitDesdeComprobante($existente);
        $cuitFmt = $cuit !== '' ? self::formatearCuit($cuit) : 'sin CUIT';
        $estado = strtoupper(trim((string) ($existente->estado ?? '')));
        $origen = ComprobanteProveedorOrigenEntrada::etiqueta((string) ($existente->origen_entrada ?? ''));

        return sprintf(
            'Ya existe el comprobante #%d (%s, CUIT %s, estado %s, origen %s). '
            .'No se puede cargar dos veces: abrí ese registro para continuar o contabilizar.',
            $existente->id,
            $comprobante,
            $cuitFmt,
            $estado !== '' ? $estado : 'sin estado',
            $origen,
        );
    }

    public static function mensajeDuplicadoCaeComprobante(Comprobante_Proveedor $existente, string $cae): string
    {
        $existente->loadMissing('tipotransaccion_compras');

        return sprintf(
            'CAE/CAI duplicado: el código %s ya figura en el comprobante #%d (%s %s %s-%s). Si es CAEA, indique tipo de autorización CAEA.',
            $cae,
            $existente->id,
            strtoupper((string) ($existente->tipotransaccion_compras?->abreviatura ?? 'FAC')),
            strtoupper((string) $existente->letra),
            $existente->sucursal,
            $existente->numerocomprobante,
        );
    }

    public static function mensajeDuplicadoCaePrecarga(Precarga_Comprobante_Proveedor $existente, string $cae): string
    {
        return sprintf(
            'CAE/CAI duplicado: el código %s ya figura en la precarga id %d (%s %s-%s). Si es CAEA, indique tipo de autorización CAEA.',
            $cae,
            $existente->id,
            strtoupper((string) $existente->letra),
            $existente->sucursal,
            $existente->numerocomprobante,
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
