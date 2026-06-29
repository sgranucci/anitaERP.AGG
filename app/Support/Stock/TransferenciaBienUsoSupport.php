<?php

namespace App\Support\Stock;

use App\Models\Contable\BienUso;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Stock\Transferencia_Mercaderia;

final class TransferenciaBienUsoSupport
{
    /** @var list<string> */
    public const DEPOSITO_RELATION_COLUMNS = ['id', 'codigo', 'nombre'];
    public static function tipoDestinoBienUso(?Tipotransaccion_Stock $tipo): bool
    {
        return (bool) ($tipo?->destino_bien_uso ?? false);
    }

    public static function tipoOrigenBienUso(?Tipotransaccion_Stock $tipo): bool
    {
        return (bool) ($tipo?->origen_bien_uso ?? false);
    }

    public static function validarFlagsTipo(?Tipotransaccion_Stock $tipo): void
    {
        if ($tipo === null) {
            return;
        }
        if ($tipo->origen_bien_uso && $tipo->destino_bien_uso) {
            throw new \InvalidArgumentException('Un tipo de transferencia no puede tener origen y destino en bien de uso a la vez.');
        }
    }

    public static function etiquetaBien(?BienUso $bien): string
    {
        if ($bien === null) {
            return '—';
        }

        $partes = array_filter([
            $bien->codigo_inventario ? '#'.$bien->codigo_inventario : null,
            $bien->hostname,
            $bien->modelo,
        ]);

        return implode(' — ', $partes) ?: 'Bien #'.$bien->id;
    }

    public static function etiquetaOrigenTransferencia(?Transferencia_Mercaderia $transferencia): string
    {
        if ($transferencia === null) {
            return '—';
        }

        return $transferencia->depositoOrigen?->etiqueta()
            ?? self::etiquetaBien($transferencia->bienUsoOrigen);
    }

    public static function etiquetaDestinoTransferencia(?Transferencia_Mercaderia $transferencia): string
    {
        if ($transferencia === null) {
            return '—';
        }

        return $transferencia->depositoDestino?->etiqueta()
            ?? self::etiquetaBien($transferencia->bienUsoDestino);
    }

    public static function assertBienActivo(int $bienUsoId): BienUso
    {
        $bien = BienUso::query()->whereKey($bienUsoId)->first();
        if ($bien === null) {
            throw new \InvalidArgumentException('Bien de uso no encontrado.');
        }
        if ($bien->estado !== 'A') {
            throw new \InvalidArgumentException('El bien de uso seleccionado no está activo.');
        }

        return $bien;
    }
}
