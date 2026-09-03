<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Traits\Stock\DepmaeTrait;
use Illuminate\Support\Collection;

class RecepcionProveedorDepositoSupport
{
    public const TIPO_DEPOSITO_FORMULAS = 'Formulas';

    /** @var Collection<int, Articulo|null> */
    private static Collection $cacheInsumoPorArticuloCompra;

    public static function reiniciarCache(): void
    {
        self::$cacheInsumoPorArticuloCompra = collect();
    }

    /**
     * Candidatos SKU del insumo a partir de skualternativo (ej. 193 → I0193).
     *
     * @return list<string>
     */
    public static function candidatosSkuInsumo(string $skuAlternativo): array
    {
        $alt = trim($skuAlternativo);
        if ($alt === '' || $alt === '0') {
            return [];
        }

        $candidatos = [$alt];
        $numerico = preg_replace('/\D+/', '', $alt) ?? '';
        if ($numerico !== '') {
            $candidatos[] = $numerico;
            $candidatos[] = 'I'.$numerico;
            $candidatos[] = 'I'.str_pad($numerico, 4, '0', STR_PAD_LEFT);
            $candidatos[] = 'I'.str_pad($numerico, 5, '0', STR_PAD_LEFT);
        }

        return array_values(array_unique(array_filter($candidatos, static fn (string $sku): bool => $sku !== '')));
    }

    /**
     * Valores posibles de skualternativo que apuntan al SKU del insumo indicado
     * (inverso de candidatosSkuInsumo para consultas indexadas).
     *
     * @return list<string>
     */
    public static function candidatosSkualternativoParaInsumo(string $skuInsumo): array
    {
        $sku = trim($skuInsumo);
        if ($sku === '' || $sku === '0') {
            return [];
        }

        $candidatos = [$sku];

        $numericoInsumo = preg_replace('/\D+/', '', $sku) ?? '';
        if ($numericoInsumo !== '') {
            $candidatos[] = $numericoInsumo;
            $sinCerosIzq = ltrim($numericoInsumo, '0');
            if ($sinCerosIzq !== '') {
                $candidatos[] = $sinCerosIzq;
            }
            $candidatos[] = 'I'.$numericoInsumo;
            if ($sinCerosIzq !== '' && $sinCerosIzq !== $numericoInsumo) {
                $candidatos[] = 'I'.$sinCerosIzq;
            }
        }

        if (preg_match('/^I(\d+)$/i', $sku, $coincidencia)) {
            $parteNumerica = (string) $coincidencia[1];
            $candidatos[] = $parteNumerica;
            $sinCerosParte = ltrim($parteNumerica, '0');
            if ($sinCerosParte !== '' && $sinCerosParte !== $parteNumerica) {
                $candidatos[] = $sinCerosParte;
            }
        }

        return array_values(array_unique(array_filter(
            $candidatos,
            static fn (string $valor): bool => $valor !== '' && $valor !== '0'
        )));
    }

    /**
     * En AGG el maestro de artículos no segmenta por empresa_id; insumo ↔ compra es global.
     */
    private static function articuloMaestroIgnoraEmpresa(): bool
    {
        return config('app.empresa') === 'AGG';
    }

    /**
     * Resuelve el artículo insumo/granel vinculado por skualternativo del artículo de compra.
     *
     * El vínculo SKU alternativo → insumo es intrínseco al artículo de compra y no debe
     * bloquearse porque la operación corra en otra empresa (ej. transferencia intercompany):
     * se prioriza la empresa de la operación, luego la del propio artículo de compra y por
     * último el insumo multiempresa (empresa_id nulo/0). En AGG no se filtra por empresa_id.
     */
    public static function resolverArticuloInsumo(Articulo $articuloCompra, ?int $empresaId = null): ?Articulo
    {
        if (! isset(self::$cacheInsumoPorArticuloCompra)) {
            self::reiniciarCache();
        }

        $empresaOperacion = ($empresaId !== null && $empresaId > 0) ? (int) $empresaId : 0;
        $empresaCompra = (int) ($articuloCompra->empresa_id ?? 0);

        $cacheKey = (int) $articuloCompra->id.':'.$empresaOperacion;
        if (self::$cacheInsumoPorArticuloCompra->has($cacheKey)) {
            return self::$cacheInsumoPorArticuloCompra->get($cacheKey);
        }

        $candidatos = self::candidatosSkuInsumo((string) ($articuloCompra->skualternativo ?? ''));
        if ($candidatos === []) {
            self::$cacheInsumoPorArticuloCompra->put($cacheKey, null);

            return null;
        }

        $empresasPermitidas = array_values(array_unique(array_filter(
            [$empresaOperacion, $empresaCompra],
            static fn (int $empresa): bool => $empresa > 0
        )));

        $query = Articulo::query()->whereIn('sku', $candidatos);
        if (! self::articuloMaestroIgnoraEmpresa()) {
            $query->where(function ($q) use ($empresasPermitidas): void {
                $q->whereNull('empresa_id')->orWhere('empresa_id', 0);
                if ($empresasPermitidas !== []) {
                    $q->orWhereIn('empresa_id', $empresasPermitidas);
                }
            });
        }

        ArticuloSeleccionOperativaSupport::aplicarSoloActivosTablaArticulo($query);

        $filas = $query->get(['id', 'sku', 'empresa_id']);
        $insumo = null;
        foreach ($candidatos as $sku) {
            $coincidencias = $filas->filter(static fn (Articulo $row): bool => (string) $row->sku === $sku);
            if ($coincidencias->isEmpty()) {
                continue;
            }

            if (self::articuloMaestroIgnoraEmpresa()) {
                $insumo = $coincidencias->sortBy('id')->first();
            } else {
                $insumo = self::elegirInsumoPorEmpresa($coincidencias, $empresaOperacion, $empresaCompra);
            }
            if ($insumo !== null) {
                break;
            }
        }

        self::$cacheInsumoPorArticuloCompra->put($cacheKey, $insumo);

        return $insumo;
    }

    /**
     * Elige el insumo priorizando empresa de la operación, luego la del artículo de compra
     * y por último el insumo multiempresa (empresa_id nulo/0). Orden determinista por id.
     *
     * @param  Collection<int, Articulo>  $coincidencias
     */
    private static function elegirInsumoPorEmpresa(Collection $coincidencias, int $empresaOperacion, int $empresaCompra): ?Articulo
    {
        $ordenadas = $coincidencias->sortBy('id')->values();

        foreach ([$empresaOperacion, $empresaCompra] as $empresa) {
            if ($empresa <= 0) {
                continue;
            }
            $match = $ordenadas->first(static fn (Articulo $row): bool => (int) ($row->empresa_id ?? 0) === $empresa);
            if ($match !== null) {
                return $match;
            }
        }

        return $ordenadas->first(static fn (Articulo $row): bool => (int) ($row->empresa_id ?? 0) === 0)
            ?? $ordenadas->first();
    }

    /**
     * Artículos de compra cuyo SKU alternativo apunta al insumo indicado.
     *
     * @return \Illuminate\Support\Collection<int, Articulo>
     */
    public static function resolverArticulosCompraDesdeInsumo(Articulo $insumo, ?int $empresaId = null): Collection
    {
        $skuInsumo = trim((string) ($insumo->sku ?? ''));
        if ($skuInsumo === '') {
            return collect();
        }

        $candidatosAlt = self::candidatosSkualternativoParaInsumo($skuInsumo);
        if ($candidatosAlt === []) {
            return collect();
        }

        $empresaInsumo = (int) ($insumo->empresa_id ?? 0);
        $empresasPermitidas = array_values(array_unique(array_filter(
            [($empresaId !== null && $empresaId > 0) ? (int) $empresaId : 0, $empresaInsumo],
            static fn (int $empresa): bool => $empresa > 0
        )));

        $query = Articulo::query()->whereIn('skualternativo', $candidatosAlt);
        if (! self::articuloMaestroIgnoraEmpresa() && $empresasPermitidas !== []) {
            $query->where(function ($q) use ($empresasPermitidas): void {
                $q->whereNull('empresa_id')
                    ->orWhere('empresa_id', 0)
                    ->orWhereIn('empresa_id', $empresasPermitidas);
            });
        }

        return $query->get()->filter(function (Articulo $compra) use ($insumo, $empresaId): bool {
            $resuelto = self::resolverArticuloInsumo($compra, $empresaId);

            return $resuelto !== null && (int) $resuelto->id === (int) $insumo->id;
        })->values();
    }

    public static function depositoPermitidoUsuario(int $depositoId, int $empresaId): bool
    {
        return $depositoId > 0 && Depmae::autorizadoParaUsuarioYEmpresa($depositoId, $empresaId);
    }

    /** ID del depósito si el usuario puede operarlo; null si no está autorizado o no hay restricción aplicable. */
    public static function depositoEntregaVisible(?int $depositoId, int $empresaId): ?int
    {
        if ($depositoId === null || $depositoId <= 0) {
            return null;
        }

        return self::depositoPermitidoUsuario($depositoId, $empresaId) ? $depositoId : null;
    }

    public static function esDepositoFormula(?Depmae $deposito): bool
    {
        if ($deposito === null) {
            return false;
        }

        $tipo = trim((string) ($deposito->tipodeposito ?? ''));

        if ($tipo === self::TIPO_DEPOSITO_FORMULAS) {
            return true;
        }

        foreach (DepmaeTrait::$enumTipoDeposito as $fila) {
            if (($fila['nombre'] ?? '') === self::TIPO_DEPOSITO_FORMULAS
                && in_array($tipo, [(string) ($fila['valor'] ?? ''), (string) ($fila['id'] ?? '')], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Depósito destino de la línea: cabecera (si existe) o depósito de entrega del artículo.
     */
    public static function resolverDepositoLinea(?int $depositoCabeceraId, Articulo $articulo): int
    {
        if ($depositoCabeceraId !== null && $depositoCabeceraId > 0) {
            return $depositoCabeceraId;
        }

        $depositoArticulo = (int) ($articulo->depositoentrega_id ?? 0);
        if ($depositoArticulo <= 0) {
            throw new \RuntimeException(
                'Artículo '.($articulo->sku ?? $articulo->id).': sin depósito de entrega configurado. '
                .'Indique un depósito general en la recepción o configure depositoentrega_id en el artículo.'
            );
        }

        return $depositoArticulo;
    }

    /**
     * Convierte cantidad/precio de UM compra a UM stock según depósito destino.
     *
     * @return array{
     *     cantidad_stock: float,
     *     precio_stock: float,
     *     coeficienteconversion: float,
     *     fl_conversion_formula: bool,
     *     usa_deposito_articulo: bool,
     *     articulo_stock_id: int|null,
     *     articulo_stock_sku: string|null
     * }
     */
    public static function calcularConversionStock(
        Articulo $articulo,
        Depmae $deposito,
        float $cantidadCompra,
        float $precioCompra,
        float $coefProveedor,
        bool $usaDepositoArticulo,
        ?int $empresaId = null
    ): array {
        $coefProv = $coefProveedor > 0 ? $coefProveedor : 1.0;
        $esFormula = self::esDepositoFormula($deposito);

        if ($esFormula) {
            $insumo = self::resolverArticuloInsumo($articulo, $empresaId);
            if ($insumo === null) {
                throw new \RuntimeException(DepositoFormulaInsumoFaltanteSupport::mensajeArticulo($articulo));
            }

            $coefArt = self::coeficienteConversionFormula($articulo, $insumo);

            return [
                'cantidad_stock' => RecepcionProveedorConversionSupport::cantidadStock($cantidadCompra, $coefArt),
                'precio_stock' => round($precioCompra / $coefArt, 6),
                'coeficienteconversion' => $coefArt,
                'fl_conversion_formula' => true,
                'usa_deposito_articulo' => true,
                'articulo_stock_id' => (int) $insumo->id,
                'articulo_stock_sku' => (string) $insumo->sku,
            ];
        }

        $precioStock = $coefProv !== 1.0 ? round($precioCompra / $coefProv, 6) : round($precioCompra, 6);

        return [
            'cantidad_stock' => RecepcionProveedorConversionSupport::cantidadStock($cantidadCompra, $coefProv),
            'precio_stock' => $precioStock,
            'coeficienteconversion' => $coefProv,
            'fl_conversion_formula' => false,
            'usa_deposito_articulo' => $usaDepositoArticulo,
            'articulo_stock_id' => null,
            'articulo_stock_sku' => null,
        ];
    }

    /**
     * Coef. caja → UM insumo. Si el artículo de compra no tiene coeficiente (&gt; 0),
     * usa el del insumo; si ambos faltan, 1 (no convertir).
     */
    public static function coeficienteConversionFormula(Articulo $articuloCompra, Articulo $insumo): float
    {
        $coefCompra = (float) ($articuloCompra->coeficienteconversion ?? 0);
        if ($coefCompra > 0) {
            return $coefCompra;
        }

        $coefInsumo = (float) ($insumo->coeficienteconversion ?? 0);

        return $coefInsumo > 0 ? $coefInsumo : 1.0;
    }

    public static function coeficienteProveedor(
        int $articuloId,
        int $proveedorId,
        ?string $codigoArticuloProveedor = null,
    ): float {
        return RecepcionProveedorConversionSupport::resolverCoeficiente(
            $articuloId,
            $proveedorId,
            $codigoArticuloProveedor
        );
    }
}
