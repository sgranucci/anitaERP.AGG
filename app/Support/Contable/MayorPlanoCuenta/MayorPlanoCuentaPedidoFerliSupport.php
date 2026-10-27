<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Pedido de venta en el mayor, solo Ferli.
 *
 * O.Compra es la orden de compra de proveedores y en una FAC/NCD queda vacía.
 * El número para cruzar con la plataforma de cobros es el pedido de Tiendanube
 * (o el que venía pegado al nombre: «16183/ROMINA MIR»). El cliente de la
 * factura va en Nombre emisor: el maestro suele ser consumidor final.
 */
final class MayorPlanoCuentaPedidoFerliSupport
{
    public static function mostrarColumna(): bool
    {
        try {
            return EntornoEmpresaSupport::esFerli();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{pedido: string, nombre: string}
     */
    public static function resolver(string $nombreVenta, string $leyenda, string $orderNumberTn): array
    {
        $pedido = trim($orderNumberTn);
        if ($pedido === '' && preg_match('/Tiendanube pedido #(\d+)/i', $leyenda, $mLeyenda) === 1) {
            $pedido = $mLeyenda[1];
        }

        $nombre = trim($nombreVenta);
        if (preg_match('/^(.+?)\s*\/\s*(\d+)\s*$/u', $nombre, $mNombre) === 1) {
            $nombre = trim($mNombre[1]);
            if ($pedido === '') {
                $pedido = $mNombre[2];
            }
        }

        if (self::esConsumidorFinal($nombre)) {
            $nombre = '';
        }

        return [
            'pedido' => $pedido,
            'nombre' => $nombre,
        ];
    }

    public static function esConsumidorFinal(string $nombre): bool
    {
        $normalizado = mb_strtoupper(trim($nombre));

        return $normalizado === '' || str_starts_with($normalizado, 'CONSUMIDOR FINAL');
    }

    /**
     * Inserta un valor en el mapa de columnas Excel justo después de $despuesDe
     * y corre una letra las columnas que estaban a la derecha. Tolera mapas
     * con letras salteadas (formatos que no declaran todas las columnas).
     *
     * @param  array<string, mixed>  $porLetra
     * @return array<string, mixed>
     */
    public static function insertarColumna(array $porLetra, string $despuesDe, mixed $valor): array
    {
        $corte = ord($despuesDe);
        $salida = [];
        foreach ($porLetra as $letra => $actual) {
            $codigo = ord((string) $letra);
            $salida[$codigo > $corte ? chr($codigo + 1) : (string) $letra] = $actual;
        }
        $salida[chr($corte + 1)] = $valor;
        ksort($salida);

        return $salida;
    }

    /**
     * @param  list<string>  $letras
     * @return list<string>
     */
    public static function desplazarLetras(array $letras, string $despuesDe): array
    {
        $corte = ord($despuesDe);

        return array_map(
            static fn (string $letra): string => ord($letra) > $corte ? chr(ord($letra) + 1) : $letra,
            $letras
        );
    }

    public static function letraSiguiente(string $letra): string
    {
        return chr(ord($letra) + 1);
    }
}
