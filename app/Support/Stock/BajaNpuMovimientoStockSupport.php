<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_ParteUnica;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Services\Stock\ArticuloParteUnicaService;

final class BajaNpuMovimientoStockSupport
{
    public const MOTIVO_DEFAULT = 'Rotura o no funcionamiento sin posibilidad de reparación';

    public static function esTipoBajaNpu(?Tipotransaccion_Stock $tipo): bool
    {
        return ArticuloParteUnicaDisponibilidadSupport::esTipoBajaNpu($tipo);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function esMovimientoReactivacionNpu(array $data, Tipotransaccion_Stock $tipo): bool
    {
        if (! self::esTipoBajaNpu($tipo)) {
            return false;
        }

        return (int) ($data['reactivar_npu_movimiento_origen_id'] ?? 0) > 0
            && self::signoCantidadEsEntrada($data, $tipo);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function esMovimientoSalidaBajaNpu(array $data, Tipotransaccion_Stock $tipo): bool
    {
        if (! self::esTipoBajaNpu($tipo)) {
            return false;
        }

        return ! self::esMovimientoReactivacionNpu($data, $tipo)
            && self::signoCantidadEsSalida($data, $tipo);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \RuntimeException
     */
    public static function validarAntesDeGrabar(array $data, Tipotransaccion_Stock $tipo): void
    {
        if (! self::esTipoBajaNpu($tipo)) {
            return;
        }

        if (self::esMovimientoReactivacionNpu($data, $tipo)) {
            self::validarReactivacionAntesDeGrabar($data);

            return;
        }

        if (self::esMovimientoSalidaBajaNpu($data, $tipo)) {
            self::validarSalidaBajaAntesDeGrabar($data);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function normalizarLineasParaGrabar(array &$data, Tipotransaccion_Stock $tipo): void
    {
        if (! self::esMovimientoSalidaBajaNpu($data, $tipo)) {
            return;
        }

        $articulos = self::normalizarArray($data['articulos_id'] ?? []);
        $cantidades = self::normalizarArray($data['cantidades'] ?? []);
        $numeropartes = self::normalizarArray($data['numeropartes'] ?? []);

        foreach ($articulos as $i => $articuloId) {
            $npu = trim((string) ($numeropartes[$i] ?? ''));
            if ($npu === '') {
                continue;
            }

            $parte = ArticuloParteUnicaDisponibilidadSupport::findActivaPorNumeroparte($npu);
            if ($parte !== null) {
                $articulos[$i] = (int) $parte->articulo_id;
            }
            $cantidades[$i] = 1;
        }

        $data['articulos_id'] = $articulos;
        $data['cantidades'] = $cantidades;
        $data['numeropartes'] = $numeropartes;
        $data['omitir_asiento_contable'] = true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function procesarDespuesDeGrabar(int $movimientostockId, array $data, Tipotransaccion_Stock $tipo): void
    {
        if (! self::esTipoBajaNpu($tipo)) {
            return;
        }

        if (self::esMovimientoReactivacionNpu($data, $tipo)) {
            self::procesarReactivacionDespuesDeGrabar($data);

            return;
        }

        if (! self::esMovimientoSalidaBajaNpu($data, $tipo)) {
            return;
        }

        $motivo = trim((string) ($data['leyenda'] ?? ''));
        if ($motivo === '') {
            $motivo = self::MOTIVO_DEFAULT;
        }

        $numeropartes = self::normalizarArray($data['numeropartes'] ?? []);
        $service = app(ArticuloParteUnicaService::class);

        foreach ($numeropartes as $npu) {
            $npu = trim((string) $npu);
            if ($npu === '') {
                continue;
            }

            $parte = ArticuloParteUnicaDisponibilidadSupport::assertActivaParaUso((int) $npu);
            $service->darDeBaja($parte, $movimientostockId, $motivo);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \RuntimeException
     */
    private static function validarSalidaBajaAntesDeGrabar(array $data): void
    {
        $depositoId = (int) ($data['deposito_id'] ?? 0);
        if ($depositoId <= 0) {
            throw new \RuntimeException('Debe indicar depósito para la baja de NPU.');
        }

        $articulos = self::normalizarArray($data['articulos_id'] ?? []);
        $cantidades = self::normalizarArray($data['cantidades'] ?? []);
        $numeropartes = self::normalizarArray($data['numeropartes'] ?? []);

        $lineasValidas = 0;
        $npusVistos = [];

        foreach ($articulos as $i => $articuloId) {
            $articuloId = (int) $articuloId;
            $cantidad = (float) ($cantidades[$i] ?? 0);
            $npu = trim((string) ($numeropartes[$i] ?? ''));

            if ($articuloId <= 0 && $npu === '' && abs($cantidad) < 1e-9) {
                continue;
            }

            if ($npu === '') {
                throw new \RuntimeException('Cada línea de baja NPU debe indicar el número de parte.');
            }

            $npuInt = (int) $npu;
            if ($npuInt <= 0) {
                throw new \RuntimeException("NPU inválido: {$npu}.");
            }

            if (isset($npusVistos[$npuInt])) {
                throw new \RuntimeException("El NPU {$npuInt} está repetido en el comprobante.");
            }
            $npusVistos[$npuInt] = true;

            ArticuloParteUnicaDisponibilidadSupport::assertActivaParaUso(
                $npuInt,
                $articuloId > 0 ? $articuloId : null,
            );

            if (abs($cantidad - 1) > 1e-9 && abs($cantidad) > 1e-9) {
                throw new \RuntimeException("La baja de NPU {$npuInt} debe ser por 1 unidad.");
            }

            $lineasValidas++;
        }

        if ($lineasValidas === 0) {
            throw new \RuntimeException('Debe indicar al menos un NPU para dar de baja.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \RuntimeException
     */
    private static function validarReactivacionAntesDeGrabar(array $data): void
    {
        $movimientoOrigenId = (int) ($data['reactivar_npu_movimiento_origen_id'] ?? 0);
        if ($movimientoOrigenId <= 0) {
            throw new \RuntimeException('Falta el movimiento origen para reactivar NPU.');
        }

        $numeropartes = self::normalizarArray($data['numeropartes'] ?? []);
        $npus = array_values(array_filter(array_map(
            static fn ($npu) => (int) trim((string) $npu),
            $numeropartes,
        ), static fn ($npu) => $npu > 0));

        if ($npus === []) {
            throw new \RuntimeException('El movimiento de reversión no trae NPUs para reactivar.');
        }

        foreach ($npus as $npu) {
            self::assertNpuDadoDeBajaPorMovimiento($npu, $movimientoOrigenId);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function procesarReactivacionDespuesDeGrabar(array $data): void
    {
        $movimientoOrigenId = (int) ($data['reactivar_npu_movimiento_origen_id'] ?? 0);
        if ($movimientoOrigenId <= 0) {
            return;
        }

        app(ArticuloParteUnicaService::class)->reactivarPorMovimientoOrigen($movimientoOrigenId);
    }

    /**
     * @throws \RuntimeException
     */
    public static function assertNpuDadoDeBajaPorMovimiento(int $numeroparte, int $movimientoOrigenId): Articulo_ParteUnica
    {
        $parte = ArticuloParteUnicaDisponibilidadSupport::findPorNumeroparte($numeroparte);
        if ($parte === null) {
            throw new \RuntimeException("El NPU {$numeroparte} no está registrado.");
        }

        if (! ArticuloParteUnicaEstados::esBaja($parte->estado)) {
            throw new \RuntimeException("El NPU {$numeroparte} no está dado de baja; no puede reactivarse por reversión.");
        }

        if ((int) ($parte->movimientostock_id ?? 0) !== $movimientoOrigenId) {
            throw new \RuntimeException("El NPU {$numeroparte} no fue dado de baja por el movimiento #{$movimientoOrigenId}.");
        }

        return $parte;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function signoCantidadEsSalida(array $data, Tipotransaccion_Stock $tipo): bool
    {
        $signo = strtoupper(trim((string) ($data['signo_cantidad'] ?? $tipo->signo ?? '')));

        return $signo === 'R';
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
}
