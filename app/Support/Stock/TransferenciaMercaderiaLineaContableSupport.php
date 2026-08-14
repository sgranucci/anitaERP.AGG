<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use App\Support\Contable\CuentacontableEmpresaResolverSupport;

/**
 * Clasificación y validación contable de líneas en transferencias TRCONT.
 */
final class TransferenciaMercaderiaLineaContableSupport
{
    public const FAMILIA_TITO = 'tito';

    public const FAMILIA_OTROS_ACTIVOS = 'otros_activos';

    public const FAMILIA_NO_CONTABILIZABLE = 'no_contabilizable';

    public static function resolverFamilia(Articulo $articulo, int $empresaId): string
    {
        if (ArticuloPrecioTransferenciaContableSupport::usaPrecioPromedio($articulo)) {
            return self::FAMILIA_TITO;
        }

        $cuentaOtrosActivosIds = CuentaAutomaticaResolver::resolverIds(
            $empresaId,
            CuentaAutomaticaClaves::STOCK_TRANSFERENCIA_OTROS_ACTIVOS
        );
        $cuentaCompraId = self::resolverCuentaCompraId($articulo, $empresaId);

        if ($cuentaCompraId > 0
            && $cuentaOtrosActivosIds !== []
            && in_array($cuentaCompraId, $cuentaOtrosActivosIds, true)) {
            return self::FAMILIA_OTROS_ACTIVOS;
        }

        return self::FAMILIA_NO_CONTABILIZABLE;
    }

    public static function esContabilizable(Articulo $articulo, int $empresaId): bool
    {
        return self::resolverFamilia($articulo, $empresaId) !== self::FAMILIA_NO_CONTABILIZABLE;
    }

    /**
     * La selección automática de TRCONT mira solamente la configuración contable
     * del artículo. Precio y depósito se validan luego, al armar la transferencia.
     *
     * @param  list<int>  $articuloIds
     */
    public static function todosContabilizables(array $articuloIds, int $empresaId): bool
    {
        $articuloIds = array_values(array_unique(array_filter(
            array_map('intval', $articuloIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($empresaId <= 0 || $articuloIds === []) {
            return false;
        }

        $articulos = Articulo::query()
            ->with('articulo_cuentacontables')
            ->whereIn('id', $articuloIds)
            ->get();

        return $articulos->count() === count($articuloIds)
            && $articulos->every(
                static fn (Articulo $articulo): bool => self::esContabilizable($articulo, $empresaId)
            );
    }

    public static function lineaGeneraAsiento(
        Articulo $articulo,
        int $empresaId,
        int $depositoOrigenId,
        ?string $fechaHasta = null,
        bool $omitirValidacionDeposito = false,
    ): bool
    {
        $resultado = self::validarLinea(
            $articulo,
            $empresaId,
            $depositoOrigenId,
            $fechaHasta,
            $omitirValidacionDeposito
        );

        return $resultado['permitido'];
    }

    /**
     * @return array{
     *     permitido: bool,
     *     familia: string,
     *     motivo: string,
     *     deposito_recepcion_id: ?int,
     *     deposito_recepcion_codigo: ?string
     * }
     */
    public static function validarLinea(
        Articulo $articulo,
        int $empresaId,
        int $depositoOrigenId,
        ?string $fechaHasta = null,
        bool $omitirValidacionDeposito = false,
    ): array
    {
        $sku = trim((string) ($articulo->sku ?? ''));
        $familia = self::resolverFamilia($articulo, $empresaId);

        if ($familia === self::FAMILIA_NO_CONTABILIZABLE) {
            return self::resultado(
                false,
                $familia,
                'Artículo '.$sku.': no es contabilizable en transferencia TRCONT. Use otro tipo de transferencia sin contabilidad.',
            );
        }

        if (! $omitirValidacionDeposito && $depositoOrigenId <= 0) {
            return self::resultado(
                false,
                $familia,
                'Artículo '.$sku.': debe indicar depósito de salida para validar contabilidad.',
            );
        }

        $depositoRecepcionId = null;
        $depositoRecepcionCodigo = null;
        if (! $omitirValidacionDeposito) {
            $recepcion = TransferenciaMercaderiaDepositoRecepcionSupport::resolver(
                (int) $articulo->id,
                $empresaId,
                $fechaHasta
            );
            $depositoRecepcionId = $recepcion['deposito_id'];
            $depositoRecepcion = $depositoRecepcionId > 0
                ? Depmae::query()->find($depositoRecepcionId)
                : null;
            $depositoRecepcionCodigo = $depositoRecepcion ? (string) ($depositoRecepcion->codigo ?? '') : null;

            if ($depositoRecepcionId === null || $depositoRecepcionId <= 0) {
                return self::resultado(
                    false,
                    $familia,
                    'Artículo '.$sku.': no tiene recepción de compra confirmada para determinar depósito.',
                    null,
                    $depositoRecepcionCodigo
                );
            }

            if ($depositoOrigenId !== $depositoRecepcionId) {
                $etiquetaDep = $depositoRecepcion
                    ? Depmae::etiquetaDesdePartes(
                        (string) ($depositoRecepcion->codigo ?? ''),
                        (string) ($depositoRecepcion->nombre ?? ''),
                        (int) $depositoRecepcion->id
                    )
                    : '#'.$depositoRecepcionId;

                return self::resultado(
                    false,
                    $familia,
                    'Artículo '.$sku.': el depósito de salida debe coincidir con el de la última recepción de compra ('.$etiquetaDep.').',
                    $depositoRecepcionId,
                    $depositoRecepcionCodigo
                );
            }
        }

        $precio = ArticuloPrecioTransferenciaContableSupport::resolverPrecioUnitario($articulo);
        if ($precio === null || $precio <= 0) {
            $modo = $familia === self::FAMILIA_TITO
                ? 'promedio de últimas compras'
                : 'última compra';

            return self::resultado(
                false,
                $familia,
                'Artículo '.$sku.': sin precio de '.$modo.' para contabilidad.',
                $depositoRecepcionId,
                $depositoRecepcionCodigo
            );
        }

        $cuentaGastoId = self::resolverCuentaGastoId($articulo, $empresaId);
        $cuentaCompraId = self::resolverCuentaCompraId($articulo, $empresaId);
        if ($cuentaGastoId <= 0 || $cuentaCompraId <= 0) {
            return self::resultado(
                false,
                $familia,
                'Artículo '.$sku.': faltan cuentas contables (gasto y/o compra).',
                $depositoRecepcionId,
                $depositoRecepcionCodigo
            );
        }

        if ($cuentaGastoId === $cuentaCompraId) {
            return self::resultado(
                false,
                $familia,
                'Artículo '.$sku.': la cuenta de gasto y la de compra son iguales; no genera asiento.',
                $depositoRecepcionId,
                $depositoRecepcionCodigo
            );
        }

        return self::resultado(
            true,
            $familia,
            '',
            $depositoRecepcionId,
            $depositoRecepcionCodigo
        );
    }

    /**
     * @param  list<int>  $articuloIds
     */
    public static function assertLineasValidasParaTrcont(
        array $articuloIds,
        int $depositoOrigenId,
        int $empresaId,
        ?string $fechaHasta = null,
        bool $omitirValidacionDeposito = false,
    ): void {
        $articuloIds = array_values(array_unique(array_filter(array_map('intval', $articuloIds), static fn ($id) => $id > 0)));
        if ($articuloIds === []) {
            throw new \RuntimeException(
                'La transferencia TRCONT no puede contabilizarse sin artículos. Use otro tipo de transferencia sin contabilidad.'
            );
        }

        if ($depositoOrigenId <= 0) {
            throw new \RuntimeException(
                'Las transferencias contables (TRCONT) requieren depósito de salida (no bien de uso como origen).'
            );
        }

        $articulos = Articulo::query()
            ->with('articulo_cuentacontables')
            ->whereIn('id', $articuloIds)
            ->get()
            ->keyBy('id');

        $errores = [];
        $validas = 0;

        foreach ($articuloIds as $articuloId) {
            $articulo = $articulos->get($articuloId);
            if (! $articulo instanceof Articulo) {
                $errores[] = 'Artículo id '.$articuloId.': no encontrado.';

                continue;
            }

            $resultado = self::validarLinea(
                $articulo,
                $empresaId,
                $depositoOrigenId,
                $fechaHasta,
                $omitirValidacionDeposito
            );
            if ($resultado['permitido']) {
                $validas++;

                continue;
            }

            $errores[] = $resultado['motivo'];
        }

        if ($validas === 0) {
            $msg = 'La transferencia TRCONT no puede contabilizarse con los artículos indicados. Use otro tipo de transferencia sin contabilidad.';
            if ($errores !== []) {
                $msg .= ' '.implode(' ', array_slice($errores, 0, 3));
                if (count($errores) > 3) {
                    $msg .= ' (y '.(count($errores) - 3).' más)';
                }
            }
            throw new \RuntimeException($msg);
        }

        if ($errores !== []) {
            throw new \RuntimeException(implode(' ', $errores));
        }
    }

    public static function resolverCuentaGastoId(Articulo $articulo, int $empresaId): int
    {
        $cuentaGrid = $articulo->articulo_cuentacontables
            ?->first(fn ($row) => (int) $row->empresa_id === $empresaId
                && strtoupper((string) $row->tipoimputacion) === 'GASTOS');

        $cuentaId = (int) ($cuentaGrid?->cuentacontable_id ?? $articulo->cuentacontablecompra_id ?? 0);

        return (int) (CuentacontableEmpresaResolverSupport::resolverIdDesdeId(
            $cuentaId,
            $empresaId
        ) ?? 0);
    }

    public static function resolverCuentaCompraId(Articulo $articulo, int $empresaId): int
    {
        $cuentaGrid = $articulo->articulo_cuentacontables
            ?->first(fn ($row) => (int) $row->empresa_id === $empresaId
                && strtoupper((string) $row->tipoimputacion) === 'COMPRAS');
        $cuentaId = (int) ($cuentaGrid?->cuentacontable_id ?? $articulo->cuentacontablecompra_id ?? 0);

        return (int) (CuentacontableEmpresaResolverSupport::resolverIdDesdeId(
            $cuentaId,
            $empresaId
        ) ?? 0);
    }

    /**
     * @return array{
     *     permitido: bool,
     *     familia: string,
     *     motivo: string,
     *     deposito_recepcion_id: ?int,
     *     deposito_recepcion_codigo: ?string
     * }
     */
    private static function resultado(
        bool $permitido,
        string $familia,
        string $motivo,
        ?int $depositoRecepcionId = null,
        ?string $depositoRecepcionCodigo = null,
    ): array {
        return [
            'permitido' => $permitido,
            'familia' => $familia,
            'motivo' => $motivo,
            'deposito_recepcion_id' => $depositoRecepcionId,
            'deposito_recepcion_codigo' => $depositoRecepcionCodigo,
        ];
    }
}
