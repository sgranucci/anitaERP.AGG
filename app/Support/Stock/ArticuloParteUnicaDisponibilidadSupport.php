<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_ParteUnica;
use App\Models\Stock\Tipotransaccion_Stock;

final class ArticuloParteUnicaDisponibilidadSupport
{
    public static function findPorNumeroparte(int|string $numeroparte): ?Articulo_ParteUnica
    {
        $npu = (int) $numeroparte;
        if ($npu <= 0) {
            return null;
        }

        return Articulo_ParteUnica::query()
            ->with('articulos')
            ->where('numeroparte', $npu)
            ->first();
    }

    public static function findActivaPorNumeroparte(int|string $numeroparte): ?Articulo_ParteUnica
    {
        $parte = self::findPorNumeroparte($numeroparte);
        if ($parte === null || ! ArticuloParteUnicaEstados::esActivo($parte->estado)) {
            return null;
        }

        return $parte;
    }

    public static function estaDadaDeBaja(int|string $numeroparte): bool
    {
        $parte = self::findPorNumeroparte($numeroparte);

        return $parte !== null && ArticuloParteUnicaEstados::esBaja($parte->estado);
    }

    /**
     * @throws \RuntimeException
     */
    public static function assertActivaParaUso(int|string $numeroparte, ?int $articuloIdEsperado = null): Articulo_ParteUnica
    {
        $npu = (int) $numeroparte;
        if ($npu <= 0) {
            throw new \RuntimeException('Debe indicar un número de parte única (NPU).');
        }

        $parte = self::findPorNumeroparte($npu);
        if ($parte === null) {
            throw new \RuntimeException("El NPU {$npu} no está registrado en el sistema.");
        }

        if (ArticuloParteUnicaEstados::esBaja($parte->estado)) {
            $motivo = trim((string) ($parte->motivo_baja ?? ''));
            $detalle = $motivo !== '' ? " ({$motivo})" : '';
            throw new \RuntimeException("El NPU {$npu} fue dado de baja y no puede utilizarse{$detalle}.");
        }

        if ($articuloIdEsperado !== null && $articuloIdEsperado > 0
            && (int) $parte->articulo_id !== $articuloIdEsperado) {
            throw new \RuntimeException("El NPU {$npu} pertenece a otro artículo.");
        }

        $parte->loadMissing('articulos');
        if (! RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($parte->articulos)) {
            throw new \RuntimeException('El artículo del NPU no está configurado para llevar número de parte.');
        }

        return $parte;
    }

    public static function esTipoBajaNpu(?Tipotransaccion_Stock $tipo): bool
    {
        if ($tipo === null) {
            return false;
        }

        return (bool) ($tipo->baja_npu ?? false);
    }

    public static function esTipoAltaNpu(?Tipotransaccion_Stock $tipo): bool
    {
        if ($tipo === null) {
            return false;
        }

        return (bool) ($tipo->alta_npu ?? false);
    }
}
