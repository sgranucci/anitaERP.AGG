<?php

namespace App\Support\Caja;

use App\Models\Configuracion\Moneda;
use Illuminate\Support\Collection;

/**
 * Monedas Anita 2..9 de cotiz_tes (cott_cambio_comN / cott_cambio_vtaN).
 */
class CotizacionTesoreriaMonedasSupport
{
    /** @var list<int> */
    public const CODIGOS = [2, 3, 4, 5, 6, 7, 8, 9];

    public static function columnaCompra(int $codigo): string
    {
        return 'cambio_compra_'.$codigo;
    }

    public static function columnaVenta(int $codigo): string
    {
        return 'cambio_venta_'.$codigo;
    }

    /**
     * @return list<string>
     */
    public static function columnasTasas(): array
    {
        $cols = [];
        foreach (self::CODIGOS as $codigo) {
            $cols[] = self::columnaCompra($codigo);
            $cols[] = self::columnaVenta($codigo);
        }

        return $cols;
    }

    /**
     * @return Collection<int, object{codigo: int, label: string, abreviatura: string}>
     */
    public static function monedasParaColumnas(): Collection
    {
        $porCodigo = Moneda::query()
            ->whereIn('codigo', array_map('strval', self::CODIGOS))
            ->get(['id', 'codigo', 'nombre', 'abreviatura'])
            ->keyBy(fn ($m) => (int) $m->codigo);

        return collect(self::CODIGOS)->map(function (int $codigo) use ($porCodigo) {
            $moneda = $porCodigo->get($codigo);

            return (object) [
                'codigo' => $codigo,
                'label' => $moneda
                    ? trim((string) ($moneda->abreviatura ?: $moneda->nombre))
                    : 'Mon. '.$codigo,
                'nombre' => $moneda ? (string) $moneda->nombre : 'Moneda '.$codigo,
                'abreviatura' => $moneda ? (string) $moneda->abreviatura : (string) $codigo,
            ];
        });
    }

    public static function formatear(?float $valor): string
    {
        if ($valor === null) {
            return '';
        }

        return number_format((float) $valor, 4, ',', '.');
    }

    public static function totalColumnasDatos(): int
    {
        // ID + Empresa + Fecha + compra/venta por moneda
        return 3 + (count(self::CODIGOS) * 2);
    }

    public static function letraUltimaColumna(): string
    {
        $n = self::totalColumnasDatos();
        $letra = '';
        while ($n > 0) {
            $n--;
            $letra = chr(65 + ($n % 26)).$letra;
            $n = intdiv($n, 26);
        }

        return $letra;
    }

    /**
     * Normaliza request → columnas de tasas (null si vacío).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, float|null>
     */
    public static function tasasDesdeRequest(array $data): array
    {
        $out = [];
        foreach (self::CODIGOS as $codigo) {
            $colCompra = self::columnaCompra($codigo);
            $colVenta = self::columnaVenta($codigo);
            $out[$colCompra] = self::parseTasa($data[$colCompra] ?? null);
            $out[$colVenta] = self::parseTasa($data[$colVenta] ?? null);
        }

        return $out;
    }

    private static function parseTasa(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_string($valor)) {
            $limpio = preg_replace('/[^\d,.\-]/', '', $valor) ?? '';
            if (str_contains($limpio, ',') && str_contains($limpio, '.')) {
                $limpio = str_replace('.', '', $limpio);
                $limpio = str_replace(',', '.', $limpio);
            } elseif (str_contains($limpio, ',')) {
                $limpio = str_replace(',', '.', $limpio);
            }
            $valor = $limpio;
        }

        return is_numeric($valor) ? (float) $valor : null;
    }
}
