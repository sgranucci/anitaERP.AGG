<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use Illuminate\Support\Facades\Log;

/**
 * Actualiza stkmae (y stkmgastro si existe) con el push de precios/cantidades de compra
 * al confirmar una recepción de proveedor — réplica de graba_stkmae / actualiza_articulo en a-stock.c.
 */
final class StkmaePrecioCompraAnitaBridgeSupport
{
    private const TABLA_STKMAE = 'stkmae';

    private const TABLA_STKMGASTR = 'stkmgastro';

    private const CAMPO_ARTICULO = 'stkm_articulo';

    private const CHUNK_SIZE = 80;

    /**
     * @param  array<string, mixed>  $actual
     * @return array<string, float|int|string>
     */
    public static function calcularPushPrecioCompra(
        array $actual,
        float $precioPesos,
        float $cantidad,
        int $fechaAnita,
        float $saldoDeposito = 0.0,
    ): array {
        $pre1 = (float) ($actual['stkm_pre_compra2'] ?? 0);
        $pre2 = (float) ($actual['stkm_pre_compra3'] ?? 0);
        $pre3 = $precioPesos;

        $cant1 = (float) ($actual['stkm_cant_compra2'] ?? 0);
        $cant2 = (float) ($actual['stkm_cant_compra3'] ?? 0);
        $cant3 = $cantidad;

        $ppp = (float) ($actual['stkm_ppp'] ?? 0);
        if ($cantidad != 0.0 && $precioPesos != 0.0) {
            $valorInicial = $ppp == 0.0
                ? $saldoDeposito * $precioPesos
                : $saldoDeposito * $ppp;

            if ($valorInicial < 0.0) {
                $valorInicial *= -1.0;
            }

            $valorActual = ($cantidad * $precioPesos) + $valorInicial;
            $cantidadActual = $cantidad + $saldoDeposito;

            if ($cantidadActual != 0.0) {
                $ppp = $valorActual / $cantidadActual;
            }

            if ($ppp < 0.009) {
                $ppp = $precioPesos;
            }
        }

        $out = [
            'stkm_pre_compra1' => $pre1,
            'stkm_pre_compra2' => $pre2,
            'stkm_pre_compra3' => $pre3,
            'stkm_cant_compra1' => $cant1,
            'stkm_cant_compra2' => $cant2,
            'stkm_cant_compra3' => $cant3,
            'stkm_fe_ult_compra' => $fechaAnita,
            'stkm_ppp' => $ppp,
        ];

        if (array_key_exists('stkm_cod_mon_co1', $actual)) {
            $out['stkm_cod_mon_co1'] = (string) ($actual['stkm_cod_mon_co2'] ?? '1');
            $out['stkm_cod_mon_co2'] = (string) ($actual['stkm_cod_mon_co3'] ?? '1');
            $out['stkm_cod_mon_co3'] = '1';
        }

        return $out;
    }

    public static function actualizarDesdeRecepcion(Recepcion_Proveedor $recepcion): int
    {
        if ($recepcion->tipo !== Recepcion_Proveedor::TIPO_RECEPCION) {
            return 0;
        }

        $grupos = self::agruparLineasRecepcion($recepcion);
        if ($grupos === []) {
            return 0;
        }

        $fechaAnita = (int) str_replace('-', '', $recepcion->fecha->format('Y-m-d'));
        $depositoAnita = (int) ($recepcion->deposito_id ?? 0);
        $empresaId = max(1, (int) ($recepcion->empresa_id ?? 1));

        $codigos = array_values(array_unique(array_column($grupos, 'codigo_anita')));
        $actualPorCodigo = self::leerStkmaePorCodigos($codigos, $empresaId);
        $saldosDeposito = self::leerSaldosStkdep($codigos, $depositoAnita, $empresaId);

        $cfg = config('recepcion_proveedor.anita');
        $sistema = (string) ($cfg['sistema_ventas'] ?? 'ventas');
        $api = new ApiAnita;
        $actualizados = 0;

        foreach ($grupos as $grupo) {
            $codigo = $grupo['codigo_anita'];
            $actual = $actualPorCodigo[$codigo] ?? null;
            if ($actual === null) {
                continue;
            }

            $nuevo = self::calcularPushPrecioCompra(
                $actual,
                $grupo['precio_pesos'],
                $grupo['cantidad'],
                $fechaAnita,
                $saldosDeposito[$codigo] ?? 0.0,
            );

            $api->apiCallEscritura(self::payloadVentas([
                'acc' => 'update',
                'sistema' => $sistema,
                'tabla' => self::TABLA_STKMAE,
                'valores' => self::updateSetStkmae($nuevo),
                'whereArmado' => self::whereArticulo($codigo),
            ], $empresaId), 'recepcion stkmae precio compra '.$codigo);

            self::actualizarStkmgastroSiExiste($api, $sistema, $codigo, $nuevo, $empresaId);
            $actualizados++;
        }

        return $actualizados;
    }

    /**
     * Push stkm_pre_compra3 (y PPP) en artículos destino al confirmar transferencia de stock.
     * Usa precio_costo_destino (última compra del origen, ÷ coef. si depósito Fórmulas).
     */
    public static function actualizarDesdeTransferencia(Transferencia_Mercaderia $transferencia): int
    {
        if ($transferencia->estado !== TransferenciaMercaderiaEstados::CONFIRMADA) {
            return 0;
        }
        if ((int) ($transferencia->movimientostock_entrada_id ?? 0) <= 0) {
            return 0;
        }

        $grupos = self::agruparLineasTransferencia($transferencia);
        if ($grupos === []) {
            return 0;
        }

        $fecha = $transferencia->fecha ?? now();
        $fechaAnita = (int) str_replace('-', '', $fecha->format('Y-m-d'));
        $depositoAnita = (int) ($transferencia->deposito_destino_id ?? 0);
        $empresaId = max(1, (int) ($transferencia->empresa_id ?? 1));

        $codigos = array_values(array_unique(array_column($grupos, 'codigo_anita')));
        $actualPorCodigo = self::leerStkmaePorCodigos($codigos, $empresaId);
        $saldosDeposito = self::leerSaldosStkdep($codigos, $depositoAnita, $empresaId);

        $cfg = config('recepcion_proveedor.anita');
        $sistema = (string) ($cfg['sistema_ventas'] ?? 'ventas');
        $api = new ApiAnita;
        $actualizados = 0;

        foreach ($grupos as $grupo) {
            $codigo = $grupo['codigo_anita'];
            $actual = $actualPorCodigo[$codigo] ?? null;
            if ($actual === null) {
                continue;
            }

            $nuevo = self::calcularPushPrecioCompra(
                $actual,
                $grupo['precio_pesos'],
                $grupo['cantidad'],
                $fechaAnita,
                $saldosDeposito[$codigo] ?? 0.0,
            );

            $api->apiCallEscritura(self::payloadVentas([
                'acc' => 'update',
                'sistema' => $sistema,
                'tabla' => self::TABLA_STKMAE,
                'valores' => self::updateSetStkmae($nuevo),
                'whereArmado' => self::whereArticulo($codigo),
            ], $empresaId), 'transferencia stkmae precio compra '.$codigo);

            self::actualizarStkmgastroSiExiste($api, $sistema, $codigo, $nuevo, $empresaId);
            $actualizados++;
        }

        return $actualizados;
    }

    /**
     * Agrupa líneas por artículo Anita (13) + precio unitario, sumando cantidades — graba_stkmae en a-stock.c.
     *
     * @return list<array{codigo_anita: string, precio_pesos: float, cantidad: float}>
     */
    public static function agruparLineasRecepcion(Recepcion_Proveedor $recepcion): array
    {
        $recepcion->loadMissing([
            'recepcion_proveedor_articulos.articulos',
            'recepcion_proveedor_articulos.articulo_stock',
        ]);

        /** @var array<string, array{codigo_anita: string, precio_raw: float, precio_pesos: float, cantidad: float}> $acum */
        $acum = [];

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $articuloMovimiento = (int) ($linea->articulo_stock_id ?? 0) > 0
                ? ($linea->articulo_stock ?? $linea->articulos)
                : $linea->articulos;

            $sku = trim((string) ($articuloMovimiento->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $cantidad = (float) ($linea->cantidad_stock ?: $linea->cantidad);
            if ($cantidad <= 0.000001) {
                continue;
            }

            $precioRaw = (float) ($linea->precio_stock ?? $linea->precio);
            $monedaAnita = self::codigoMonedaAnita((int) $linea->moneda_id);
            $coef = $monedaAnita !== '1' ? (float) ($linea->cotizacion ?? 1) : 1.0;
            $precioPesos = $precioRaw * $coef;

            $codigo = RecepcionProveedorAnitaEscrituraSupport::skuAnita13($sku);
            $clave = $codigo.'|'.number_format($precioRaw, 4, '.', '');

            if (! isset($acum[$clave])) {
                $acum[$clave] = [
                    'codigo_anita' => $codigo,
                    'precio_raw' => $precioRaw,
                    'precio_pesos' => $precioPesos,
                    'cantidad' => 0.0,
                ];
            }

            $acum[$clave]['cantidad'] += $cantidad;
        }

        return array_values(array_map(
            static fn (array $row) => [
                'codigo_anita' => $row['codigo_anita'],
                'precio_pesos' => $row['precio_pesos'],
                'cantidad' => $row['cantidad'],
            ],
            $acum
        ));
    }

    /**
     * @return list<array{codigo_anita: string, precio_pesos: float, cantidad: float}>
     */
    public static function agruparLineasTransferencia(Transferencia_Mercaderia $transferencia): array
    {
        $transferencia->loadMissing(['articulos.articuloDestino']);

        /** @var array<string, array{codigo_anita: string, precio_raw: float, precio_pesos: float, cantidad: float}> $acum */
        $acum = [];

        foreach ($transferencia->articulos as $linea) {
            $articulo = $linea->articuloDestino;
            if ($articulo === null) {
                continue;
            }

            $sku = trim((string) ($articulo->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $cantidad = abs((float) $linea->cantidad_destino);
            if ($cantidad <= 0.000001) {
                continue;
            }

            $precioRaw = (float) $linea->precio_costo_destino;
            if ($precioRaw <= 0.000001) {
                continue;
            }

            $codigo = RecepcionProveedorAnitaEscrituraSupport::skuAnita13($sku);
            $clave = $codigo.'|'.number_format($precioRaw, 4, '.', '');

            if (! isset($acum[$clave])) {
                $acum[$clave] = [
                    'codigo_anita' => $codigo,
                    'precio_raw' => $precioRaw,
                    'precio_pesos' => $precioRaw,
                    'cantidad' => 0.0,
                ];
            }

            $acum[$clave]['cantidad'] += $cantidad;
        }

        return array_values(array_map(
            static fn (array $row) => [
                'codigo_anita' => $row['codigo_anita'],
                'precio_pesos' => $row['precio_pesos'],
                'cantidad' => $row['cantidad'],
            ],
            $acum
        ));
    }

    /**
     * @param  list<string>  $codigosAnita
     * @return array<string, array<string, mixed>>
     */
    public static function leerStkmaePorCodigos(array $codigosAnita, int $empresaId = 1): array
    {
        if ($codigosAnita === []) {
            return [];
        }

        $cfg = config('recepcion_proveedor.anita');
        $sistema = (string) ($cfg['sistema_ventas'] ?? 'ventas');
        $camposBase = [
            self::CAMPO_ARTICULO,
            'stkm_pre_compra1',
            'stkm_pre_compra2',
            'stkm_pre_compra3',
            'stkm_cant_compra1',
            'stkm_cant_compra2',
            'stkm_cant_compra3',
            'stkm_fe_ult_compra',
            'stkm_ppp',
        ];
        if (config('app.empresa') === 'AGG') {
            $camposBase[] = 'stkm_cod_mon_co1';
            $camposBase[] = 'stkm_cod_mon_co2';
            $camposBase[] = 'stkm_cod_mon_co3';
        }
        $campos = implode(', ', $camposBase);

        $out = [];
        $api = new ApiAnita;

        foreach (array_chunk($codigosAnita, self::CHUNK_SIZE) as $chunk) {
            $lista = implode(',', array_map(
                static fn (string $c) => "'".str_replace("'", "''", $c)."'",
                $chunk
            ));

            try {
                $raw = $api->apiCall(self::payloadVentas([
                    'acc' => 'list',
                    'sistema' => $sistema,
                    'tabla' => self::TABLA_STKMAE,
                    'campos' => $campos,
                    'whereArmado' => ' WHERE '.self::CAMPO_ARTICULO.' IN ('.$lista.') ',
                ], $empresaId));
            } catch (\Throwable $e) {
                Log::warning('StkmaePrecioCompraAnitaBridge: error lectura stkmae', ['exception' => $e]);

                continue;
            }

            foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
                $row = is_array($fila) ? $fila : get_object_vars($fila);
                $codigo = trim((string) ($row[self::CAMPO_ARTICULO] ?? ''));
                if ($codigo === '') {
                    continue;
                }
                $out[$codigo] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $codigosAnita
     * @return array<string, float>
     */
    private static function leerSaldosStkdep(array $codigosAnita, int $depositoAnita, int $empresaId = 1): array
    {
        if ($codigosAnita === [] || $depositoAnita <= 0) {
            return [];
        }

        $cfg = config('recepcion_proveedor.anita');
        $sistema = (string) ($cfg['sistema_ventas'] ?? 'ventas');
        $api = new ApiAnita;
        $out = [];

        foreach (array_chunk($codigosAnita, self::CHUNK_SIZE) as $chunk) {
            $lista = implode(',', array_map(
                static fn (string $c) => "'".str_replace("'", "''", $c)."'",
                $chunk
            ));

            try {
                $raw = $api->apiCall(self::payloadVentas([
                    'acc' => 'list',
                    'sistema' => $sistema,
                    'tabla' => 'stkdep',
                    'campos' => 'stkd_articulo, stkd_cantidad',
                    'whereArmado' => ' WHERE stkd_articulo IN ('.$lista.') AND stkd_deposito = '.$depositoAnita,
                ], $empresaId));
            } catch (\Throwable $e) {
                Log::warning('StkmaePrecioCompraAnitaBridge: error lectura stkdep', ['exception' => $e]);

                continue;
            }

            foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
                $row = is_array($fila) ? $fila : get_object_vars($fila);
                $codigo = trim((string) ($row['stkd_articulo'] ?? ''));
                if ($codigo === '') {
                    continue;
                }
                $out[$codigo] = (float) ($row['stkd_cantidad'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, float|int|string>  $valores
     */
    private static function updateSetStkmae(array $valores): string
    {
        $asignaciones = [];
        foreach ($valores as $columna => $valor) {
            if (str_starts_with($columna, 'stkm_cod_mon_co')) {
                $asignaciones[$columna] = RecepcionProveedorAnitaEscrituraSupport::textoSql((string) $valor, 1);
            } elseif ($columna === 'stkm_fe_ult_compra') {
                $asignaciones[$columna] = RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $valor);
            } else {
                $asignaciones[$columna] = RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) $valor);
            }
        }

        return RecepcionProveedorAnitaEscrituraSupport::updateSet($asignaciones);
    }

    /**
     * @param  array<string, float|int|string>  $valoresStkmae
     */
    private static function actualizarStkmgastroSiExiste(
        ApiAnita $api,
        string $sistema,
        string $codigoAnita,
        array $valoresStkmae,
        int $empresaId = 1,
    ): void {
        try {
            $raw = $api->apiCall(self::payloadVentas([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => self::TABLA_STKMGASTR,
                'campos' => 'stkmg_articulo',
                'whereArmado' => " WHERE stkmg_articulo = '".str_replace("'", "''", $codigoAnita)."' ",
                'limit' => 'FIRST 1',
            ], $empresaId));
        } catch (\Throwable $e) {
            return;
        }

        if (ApiAnita::primeraFilaLista($raw) === null) {
            return;
        }

        $mapa = [
            'stkm_pre_compra1' => 'stkmg_pre_compra1',
            'stkm_pre_compra2' => 'stkmg_pre_compra2',
            'stkm_pre_compra3' => 'stkmg_pre_compra3',
            'stkm_cant_compra1' => 'stkmg_cant_compra1',
            'stkm_cant_compra2' => 'stkmg_cant_compra2',
            'stkm_cant_compra3' => 'stkmg_cant_compra3',
            'stkm_fe_ult_compra' => 'stkmg_fe_ult_compra',
            'stkm_ppp' => 'stkmg_ppp',
        ];

        $asignaciones = [];
        foreach ($mapa as $origen => $destino) {
            if (! array_key_exists($origen, $valoresStkmae)) {
                continue;
            }
            $valor = $valoresStkmae[$origen];
            if ($destino === 'stkmg_fe_ult_compra') {
                $asignaciones[$destino] = RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $valor);
            } else {
                $asignaciones[$destino] = RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) $valor);
            }
        }

        if ($asignaciones === []) {
            return;
        }

        try {
            $api->apiCallEscritura(self::payloadVentas([
                'acc' => 'update',
                'sistema' => $sistema,
                'tabla' => self::TABLA_STKMGASTR,
                'valores' => RecepcionProveedorAnitaEscrituraSupport::updateSet($asignaciones),
                'whereArmado' => " WHERE stkmg_articulo = '".str_replace("'", "''", $codigoAnita)."' ",
            ], $empresaId), 'recepcion stkmgastro precio compra '.$codigoAnita);
        } catch (\Throwable $e) {
            Log::warning('StkmaePrecioCompraAnitaBridge: stkmgastro no actualizado (stkmae sí)', [
                'articulo' => $codigoAnita,
                'mensaje' => $e->getMessage(),
            ]);
        }
    }

    private static function whereArticulo(string $codigoAnita): string
    {
        return " WHERE ".self::CAMPO_ARTICULO." = '".str_replace("'", "''", $codigoAnita)."' ";
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function payloadVentas(array $payload, int $empresaId): array
    {
        return StockAnitaBridgeSupport::mergePayload($payload, $empresaId);
    }

    private static function codigoMonedaAnita(int $monedaId): string
    {
        return match ($monedaId) {
            2 => '2',
            3 => '3',
            default => '1',
        };
    }
}
