<?php

namespace App\Support\Ventas;

/**
 * Referencia del pedido para Anita (penm_*_fact de pendmae).
 *
 * El código ERP del pedido es PED-{letra}-{sucursal}-{numero}; Anita guarda cada
 * parte en su columna y penm_sucursal_fact / penm_nro_fact son numéricos: mandar
 * el código completo aborta el insert con "1213: Character to numeric conversion error".
 */
final class PedidoReferenciaAnitaSupport
{
    public const TIPO = 'PED';

    private const LETRA_DEFAULT = 'X';

    private const SUCURSAL_DEFAULT = 1;

    /**
     * @return array{tipofactura: string, letrafactura: string, sucursalfactura: int, numerofactura: int}
     */
    public static function desdeCodigoPedido(?string $codigo): array
    {
        $partes = array_values(array_filter(
            array_map('trim', explode('-', (string) $codigo)),
            static fn (string $parte): bool => $parte !== '',
        ));

        $letra = self::LETRA_DEFAULT;
        $sucursal = self::SUCURSAL_DEFAULT;

        if (count($partes) >= 4) {
            $letra = strtoupper(substr($partes[1], 0, 1)) ?: self::LETRA_DEFAULT;
            $sucursal = (int) $partes[2] ?: self::SUCURSAL_DEFAULT;
        }

        return [
            'tipofactura' => self::TIPO,
            'letrafactura' => $letra,
            'sucursalfactura' => $sucursal,
            'numerofactura' => self::numero($codigo),
        ];
    }

    /**
     * Número de pedido para columnas numéricas de Anita. Acepta el código completo
     * (PED-X-00001-00509711 → 509711) y el número suelto de flujos legacy.
     */
    public static function numero(?string $codigo): int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return 0;
        }

        if (ctype_digit($codigo)) {
            return (int) $codigo;
        }

        $partes = explode('-', $codigo);
        $ultimo = trim((string) end($partes));

        return ctype_digit($ultimo) ? (int) $ultimo : 0;
    }
}
