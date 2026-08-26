<?php

namespace App\Support\Stock;

use App\Models\Stock\Transferencia_Mercaderia;
use App\Support\Configuracion\SistemaNumeradorSupport;
use App\Support\Database\DbContencionSupport;
use Throwable;

/**
 * Código secuencial TR-00000840 vía sistema_numerador (stock.transferencia).
 * Los TR-{YmdHis} viejos se ignoran al calcular el piso.
 */
final class TransferenciaMercaderiaCodigoSupport
{
    public const UNIQUE_INDEX = 'uk_transferencia_mercaderia_codigo';

    public const ANCHO = 8;

    public static function formatear(int $numero): string
    {
        return 'TR-'.str_pad((string) max(1, $numero), self::ANCHO, '0', STR_PAD_LEFT);
    }

    public static function extraerSecuencial(string $codigo): ?int
    {
        if (! preg_match('/^TR-(\d+)$/', trim($codigo), $m)) {
            return null;
        }

        // YmdHis / ymdHis legacy (12+ dígitos). El correlativo nuevo va pad 8.
        if (strlen($m[1]) >= 12) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * @param  iterable<string>  $codigos
     */
    public static function pisoDesdeCodigos(iterable $codigos): int
    {
        $max = 0;
        foreach ($codigos as $codigo) {
            $n = self::extraerSecuencial((string) $codigo);
            if ($n !== null && $n > $max) {
                $max = $n;
            }
        }

        return $max;
    }

    /**
     * @return array{codigo: string, lote: int}
     */
    public static function reservar(): array
    {
        $piso = max(
            (int) Transferencia_Mercaderia::query()->max('id'),
            self::pisoDesdeExistentes()
        );
        $nro = SistemaNumeradorSupport::reservarSiguienteStockTransferencia($piso);

        return [
            'codigo' => self::formatear($nro),
            'lote' => $nro,
        ];
    }

    public static function esCodigoDuplicado(Throwable $e): bool
    {
        return DbContencionSupport::esViolacionUnicidad(
            $e,
            self::UNIQUE_INDEX,
            'transferencia_mercaderia',
        );
    }

    private static function pisoDesdeExistentes(): int
    {
        $codigos = Transferencia_Mercaderia::query()
            ->where('codigo', 'like', 'TR-%')
            ->whereRaw('CHAR_LENGTH(codigo) <= ?', [3 + 11])
            ->pluck('codigo');

        return self::pisoDesdeCodigos($codigos);
    }
}
