<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Services\Stock\ArticuloParteUnicaService;
use Illuminate\Support\Facades\Log;

final class AltaNpuMovimientoStockSupport
{
    public const MAX_UNIDADES_POR_LINEA = 200;

    public static function esTipoAltaNpu(?Tipotransaccion_Stock $tipo): bool
    {
        return ArticuloParteUnicaDisponibilidadSupport::esTipoAltaNpu($tipo);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function esMovimientoEliminacionNpuPorReverso(array $data, Tipotransaccion_Stock $tipo): bool
    {
        if (! self::esTipoAltaNpu($tipo)) {
            return false;
        }

        return (int) ($data['eliminar_npu_movimiento_origen_id'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function esMovimientoEntradaAltaNpu(array $data, Tipotransaccion_Stock $tipo): bool
    {
        if (! self::esTipoAltaNpu($tipo)) {
            return false;
        }

        if (self::esMovimientoEliminacionNpuPorReverso($data, $tipo)) {
            return false;
        }

        return self::signoCantidadEsEntrada($data, $tipo);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \RuntimeException
     */
    public static function validarAntesDeGrabar(array $data, Tipotransaccion_Stock $tipo): void
    {
        if (! self::esTipoAltaNpu($tipo)) {
            return;
        }

        if (self::esMovimientoEliminacionNpuPorReverso($data, $tipo)) {
            self::validarEliminacionPorReversoAntesDeGrabar($data);

            return;
        }

        if (self::esMovimientoEntradaAltaNpu($data, $tipo)) {
            self::validarEntradaAltaAntesDeGrabar($data);
        }
    }

    /**
     * Expande cantidad N → N líneas de 1 y genera NPU secuenciales.
     *
     * @param  array<string, mixed>  $data
     */
    public static function normalizarLineasParaGrabar(array &$data, Tipotransaccion_Stock $tipo): void
    {
        if (! self::esMovimientoEntradaAltaNpu($data, $tipo)) {
            return;
        }

        $articulos = self::normalizarArray($data['articulos_id'] ?? []);
        $cantidades = self::normalizarArray($data['cantidades'] ?? []);
        $precios = self::normalizarArray($data['precios'] ?? []);
        $listaprecios = self::normalizarArray($data['listasprecios_id'] ?? []);
        $incluyeimpuestos = self::normalizarArray($data['incluyeimpuestos'] ?? []);
        $monedas = self::normalizarArray($data['monedas_id'] ?? []);
        $descuentos = self::normalizarArray($data['descuentos'] ?? []);
        $cajas = self::normalizarArray($data['cajas'] ?? []);
        $piezas = self::normalizarArray($data['piezas'] ?? []);
        $skus = self::normalizarArray($data['skus'] ?? []);
        $combinaciones = self::normalizarArray($data['combinaciones_id'] ?? []);
        $modulos = self::normalizarArray($data['modulos_id'] ?? []);
        $loteids = self::normalizarArray($data['loteids'] ?? []);
        $medidas = self::normalizarArray($data['medidas'] ?? []);
        $colores = self::normalizarArray($data['colores_id'] ?? []);
        $talles = self::normalizarArray($data['talles_id'] ?? []);
        $numeropartes = self::normalizarArray($data['numeropartes'] ?? []);

        $outArticulos = [];
        $outCantidades = [];
        $outNumeropartes = [];
        $outPrecios = [];
        $outListaprecios = [];
        $outIncluye = [];
        $outMonedas = [];
        $outDescuentos = [];
        $outCajas = [];
        $outPiezas = [];
        $outSkus = [];
        $outCombinaciones = [];
        $outModulos = [];
        $outLoteids = [];
        $outMedidas = [];
        $outColores = [];
        $outTalles = [];
        $outItems = [];
        $npusGenerados = [];

        $service = app(ArticuloParteUnicaService::class);
        $itemIdx = 0;

        foreach ($articulos as $i => $articuloId) {
            $articuloId = (int) $articuloId;
            $cantidad = (float) ($cantidades[$i] ?? 0);

            if ($articuloId <= 0 && abs($cantidad) < 1e-9) {
                continue;
            }

            $npuManual = self::npuManualDeLinea($numeropartes, $i);

            $unidades = RecepcionProveedorParteUnicaSupport::unidadesDesdeCantidad($cantidad);
            for ($u = 0; $u < $unidades; $u++) {
                $parte = $service->crear($articuloId, $npuManual > 0 ? $npuManual : null);
                $npu = (int) $parte->numeroparte;
                $npusGenerados[] = $npu;

                $outArticulos[] = $articuloId;
                $outCantidades[] = 1;
                $outNumeropartes[] = (string) $npu;
                $outPrecios[] = $precios[$i] ?? 0;
                $outListaprecios[] = $listaprecios[$i] ?? null;
                $outIncluye[] = $incluyeimpuestos[$i] ?? '0';
                $outMonedas[] = $monedas[$i] ?? null;
                $outDescuentos[] = $descuentos[$i] ?? 0;
                $outCajas[] = $cajas[$i] ?? 0;
                $outPiezas[] = $piezas[$i] ?? 0;
                $outSkus[] = $skus[$i] ?? '';
                $outCombinaciones[] = $combinaciones[$i] ?? null;
                $outModulos[] = $modulos[$i] ?? null;
                $outLoteids[] = $loteids[$i] ?? 0;
                $outMedidas[] = $medidas[$i] ?? '';
                $outColores[] = $colores[$i] ?? null;
                $outTalles[] = $talles[$i] ?? null;
                $outItems[] = $itemIdx++;
            }
        }

        $data['articulos_id'] = $outArticulos;
        $data['cantidades'] = $outCantidades;
        $data['numeropartes'] = $outNumeropartes;
        $data['precios'] = $outPrecios;
        $data['listasprecios_id'] = $outListaprecios;
        $data['incluyeimpuestos'] = $outIncluye;
        $data['monedas_id'] = $outMonedas;
        $data['descuentos'] = $outDescuentos;
        $data['cajas'] = $outCajas;
        $data['piezas'] = $outPiezas;
        $data['skus'] = $outSkus;
        $data['combinaciones_id'] = $outCombinaciones;
        $data['modulos_id'] = $outModulos;
        $data['loteids'] = $outLoteids;
        $data['medidas'] = $outMedidas;
        $data['colores_id'] = $outColores;
        $data['talles_id'] = $outTalles;
        $data['items'] = $outItems;
        $data['omitir_asiento_contable'] = true;
        $data['_npus_generados_alta'] = $npusGenerados;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function procesarDespuesDeGrabar(int $movimientostockId, array $data, Tipotransaccion_Stock $tipo): void
    {
        if (! self::esTipoAltaNpu($tipo)) {
            return;
        }

        if (self::esMovimientoEliminacionNpuPorReverso($data, $tipo)) {
            self::procesarEliminacionPorReversoDespuesDeGrabar($data);

            return;
        }

        // Los NPU ya se crearon en normalizar; el vínculo queda en articulo_movimiento.numeroparte.
        unset($movimientostockId);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \RuntimeException
     */
    private static function validarEntradaAltaAntesDeGrabar(array $data): void
    {
        $depositoId = (int) ($data['deposito_id'] ?? 0);
        if ($depositoId <= 0) {
            throw new \RuntimeException('Debe indicar depósito para el alta de NPU.');
        }

        $articulos = self::normalizarArray($data['articulos_id'] ?? []);
        $cantidades = self::normalizarArray($data['cantidades'] ?? []);
        $numeropartes = self::normalizarArray($data['numeropartes'] ?? []);

        $lineasValidas = 0;
        $totalUnidades = 0;
        $npusManuales = [];

        foreach ($articulos as $i => $articuloId) {
            $articuloId = (int) $articuloId;
            $cantidad = (float) ($cantidades[$i] ?? 0);

            if ($articuloId <= 0 && abs($cantidad) < 1e-9) {
                continue;
            }

            if ($articuloId <= 0) {
                throw new \RuntimeException('Cada línea de alta NPU debe indicar un artículo.');
            }

            if ($cantidad <= 0) {
                throw new \RuntimeException('La cantidad de alta NPU debe ser mayor a cero.');
            }

            if (abs($cantidad - round($cantidad)) > 1e-9) {
                throw new \RuntimeException('La cantidad de alta NPU debe ser un número entero (unidades físicas).');
            }

            $unidades = RecepcionProveedorParteUnicaSupport::unidadesDesdeCantidad($cantidad);
            if ($unidades > self::MAX_UNIDADES_POR_LINEA) {
                throw new \RuntimeException(
                    'Alta NPU: máximo '.self::MAX_UNIDADES_POR_LINEA.' unidades por línea (pidió '.$unidades.').'
                );
            }

            $articulo = Articulo::query()->find($articuloId);
            if ($articulo === null) {
                throw new \RuntimeException("Artículo id {$articuloId} no encontrado.");
            }

            if (! RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($articulo)) {
                $sku = trim((string) ($articulo->sku ?? ''));
                throw new \RuntimeException(
                    'El artículo '.($sku !== '' ? $sku : '#'.$articuloId)
                    .' no está configurado para llevar número de parte (NPU).'
                );
            }

            $npuManual = self::npuManualDeLinea($numeropartes, $i);
            if ($npuManual > 0) {
                if ($unidades !== 1) {
                    throw new \RuntimeException(
                        'El NPU '.$npuManual.' se indica en una línea con cantidad 1. '
                        .'Use una línea por NPU o deje el NPU vacío para que el sistema lo genere.'
                    );
                }

                if (isset($npusManuales[$npuManual])) {
                    throw new \RuntimeException('El NPU '.$npuManual.' está repetido en el comprobante.');
                }

                $npusManuales[$npuManual] = true;
                self::assertNpuDisponibleParaAlta($npuManual, $articulo);
            }

            $lineasValidas++;
            $totalUnidades += $unidades;
        }

        if ($lineasValidas === 0 || $totalUnidades === 0) {
            throw new \RuntimeException('Debe indicar al menos un artículo con cantidad para alta de NPU.');
        }
    }

    /**
     * NPU tipeado por el operador en la línea; 0 = lo genera el sistema.
     *
     * @param  list<mixed>  $numeropartes
     *
     * @throws \RuntimeException
     */
    private static function npuManualDeLinea(array $numeropartes, int|string $indice): int
    {
        $valor = trim((string) ($numeropartes[$indice] ?? ''));
        if ($valor === '') {
            return 0;
        }

        if (! ctype_digit($valor)) {
            throw new \RuntimeException("El NPU «{$valor}» debe ser un número entero sin espacios ni letras.");
        }

        $npu = (int) $valor;
        if ($npu <= 0) {
            throw new \RuntimeException('El NPU debe ser mayor a cero.');
        }

        return $npu;
    }

    /**
     * NPU libre en el ERP. Si ya existe en Anita (etiqueta histórica) solo se admite
     * para el mismo artículo: el alta lo registra en el ERP sin duplicarlo en Anita.
     *
     * @throws \RuntimeException
     */
    private static function assertNpuDisponibleParaAlta(int $npu, Articulo $articulo): void
    {
        $parte = ArticuloParteUnicaDisponibilidadSupport::findPorNumeroparte($npu);
        if ($parte !== null) {
            $skuParte = trim((string) ($parte->articulos->sku ?? ''));
            $detalle = $skuParte !== '' ? " (artículo {$skuParte})" : '';
            throw new \RuntimeException("El NPU {$npu} ya está registrado en el sistema{$detalle}.");
        }

        try {
            $skuAnita = StkParteUnicaAnitaBridgeSupport::skuAnitaDeNumeroparte($npu);
        } catch (\Throwable $e) {
            Log::warning('AltaNpuMovimientoStock: no se pudo verificar el NPU en Anita', [
                'numeroparte' => $npu,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($skuAnita === null || StkParteUnicaAnitaBridgeSupport::mismoSku($skuAnita, $articulo->sku)) {
            return;
        }

        $skuLinea = trim((string) ($articulo->sku ?? ''));
        throw new \RuntimeException(
            "El NPU {$npu} ya existe en Anita para el artículo ".(ltrim($skuAnita, '0') ?: $skuAnita)
            .", no para {$skuLinea}."
        );
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \RuntimeException
     */
    private static function validarEliminacionPorReversoAntesDeGrabar(array $data): void
    {
        $movimientoOrigenId = (int) ($data['eliminar_npu_movimiento_origen_id'] ?? 0);
        if ($movimientoOrigenId <= 0) {
            throw new \RuntimeException('Falta el movimiento origen para eliminar NPU por reversión.');
        }

        $npus = self::npusDeMovimientoOrigen($movimientoOrigenId);
        if ($npus === []) {
            throw new \RuntimeException('El movimiento de alta NPU #'.$movimientoOrigenId.' no tiene NPU asociados para eliminar.');
        }

        foreach ($npus as $npu) {
            ArticuloParteUnicaDisponibilidadSupport::assertActivaParaUso($npu);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function procesarEliminacionPorReversoDespuesDeGrabar(array $data): void
    {
        $movimientoOrigenId = (int) ($data['eliminar_npu_movimiento_origen_id'] ?? 0);
        if ($movimientoOrigenId <= 0) {
            return;
        }

        $service = app(ArticuloParteUnicaService::class);
        foreach (self::npusDeMovimientoOrigen($movimientoOrigenId) as $npu) {
            $parte = ArticuloParteUnicaDisponibilidadSupport::assertActivaParaUso($npu);
            $service->eliminar($parte);
        }
    }

    /**
     * @return list<int>
     */
    private static function npusDeMovimientoOrigen(int $movimientoOrigenId): array
    {
        $valores = Articulo_Movimiento::query()
            ->where('movimientostock_id', $movimientoOrigenId)
            ->whereNotNull('numeroparte')
            ->pluck('numeroparte');

        $npus = [];
        foreach ($valores as $valor) {
            $npu = (int) trim((string) $valor);
            if ($npu > 0) {
                $npus[$npu] = $npu;
            }
        }

        return array_values($npus);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function signoCantidadEsEntrada(array $data, Tipotransaccion_Stock $tipo): bool
    {
        $signo = strtoupper(trim((string) ($data['signo_cantidad'] ?? $tipo->signo ?? '')));

        return $signo === 'S';
    }

    /**
     * @return list<mixed>
     */
    private static function normalizarArray(mixed $valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }

        if ($valor === null || $valor === '') {
            return [];
        }

        return [$valor];
    }
};
