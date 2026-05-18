<?php

namespace App\Support\Stock\AnitaSync\Descuento;

use App\Models\Ventas\DescuentoGastronomia;
use App\Models\Ventas\Cliente;

/**
 * Mapeo campo a campo: descuento (Anita) → descuento_gastronomia (ERP).
 */
final class DescuentoFieldMapper
{
    public static function mapCodigo(object $row): ?string
    {
        $codigo = (int) ($row->dto_codigo ?? 0);

        return $codigo > 0 ? (string) $codigo : null;
    }

    public static function mapNombre(object $row): string
    {
        $desc = trim((string) ($row->dto_desc ?? ''));
        if ($desc !== '') {
            return $desc;
        }

        $codigo = (int) ($row->dto_codigo ?? 0);

        return $codigo > 0 ? 'Descuento '.$codigo : 'Descuento sin descripción';
    }

    public static function mapTipovalor(object $row): string
    {
        $tipo = strtoupper(trim((string) ($row->dto_tipo_valor ?? '')));

        if (in_array($tipo, [
            DescuentoGastronomia::TIPO_PORCENTAJE,
            DescuentoGastronomia::TIPO_IMPORTE,
            DescuentoGastronomia::TIPO_APLICA,
        ], true)) {
            return $tipo;
        }

        throw new \InvalidArgumentException("tipo de valor inválido «{$tipo}» (se espera P, I o A).");
    }

    public static function mapValor(object $row): float
    {
        return (float) ($row->dto_valor ?? 0);
    }

    public static function mapClienteId(object $row): ?int
    {
        $codigoAnita = trim((string) ($row->dto_cliente ?? ''));
        if ($codigoAnita === '' || $codigoAnita === '0') {
            return null;
        }

        $codigo = ltrim($codigoAnita, '0');
        if ($codigo === '') {
            return null;
        }

        $cliente = Cliente::query()
            ->where('codigo', $codigo)
            ->orWhere('codigo', $codigoAnita)
            ->first();

        return $cliente ? (int) $cliente->id : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapAll(object $row): array
    {
        return [
            'codigo' => self::mapCodigo($row),
            'nombre' => self::mapNombre($row),
            'tipovalor' => self::mapTipovalor($row),
            'valor' => self::mapValor($row),
            'cliente_id' => self::mapClienteId($row),
        ];
    }
}
