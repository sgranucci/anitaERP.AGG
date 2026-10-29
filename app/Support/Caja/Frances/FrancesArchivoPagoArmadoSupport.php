<?php

declare(strict_types=1);

namespace App\Support\Caja\Frances;

use App\Support\Caja\InterbankingArchivoPagoAnitaReader;
use App\Support\Compras\CbuSupport;

/**
 * Cruza pago + auxpag como p-enviafrances.c y arma el CSV.
 * No habla con Anita: el servicio le pasa las filas ya leídas.
 */
final class FrancesArchivoPagoArmadoSupport
{
    /**
     * @param  list<array<string, mixed>>  $pagos
     * @param  list<array<string, mixed>>  $auxpag
     * @param  array<string, array{nombre:string, cuit:string, cbu:string}>  $proveedores  clave = código 6
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     omitidas: list<array<string, string>>,
     *     archivo: string,
     *     cantidad: int,
     *     total: float
     * }
     */
    public static function armar(array $pagos, array $auxpag, array $proveedores, string $tipoAplicacion): array
    {
        $tipo = strtoupper(substr(trim($tipoAplicacion), 0, 3));
        $anulados = [];
        foreach ($pagos as $pago) {
            if (strtoupper(substr(trim((string) ($pago['tipo'] ?? '')), 0, 3)) === 'AOP') {
                $anulados[(int) ($pago['numero'] ?? 0)] = true;
            }
        }
        $auxPorPago = [];
        foreach ($auxpag as $linea) {
            if (strtoupper(substr(trim((string) ($linea['tipo_ap'] ?? '')), 0, 3)) !== $tipo) {
                continue;
            }
            $auxPorPago[self::clave($linea)][] = $linea;
        }

        $filas = [];
        $omitidas = [];
        foreach ($pagos as $pago) {
            $tipoPago = strtoupper(substr(trim((string) ($pago['tipo'] ?? '')), 0, 3));
            if ($tipoPago === 'AOP') {
                continue;
            }
            $mov = strtoupper(substr(trim((string) ($pago['mov_ext'] ?? '')), 0, 1));
            $clave = self::clave($pago);
            $lineas = $auxPorPago[$clave] ?? [];
            if ($lineas === []) {
                continue;
            }
            if (isset($anulados[(int) ($pago['numero'] ?? 0)])) {
                $omitidas[] = [
                    'orden_pago' => self::numero($pago),
                    'motivo' => 'La orden tiene una anulación AOP',
                ];

                continue;
            }
            if ($mov === 'T') {
                $omitidas[] = [
                    'orden_pago' => self::numero($pago),
                    'motivo' => 'Ya marcada como transferida en Anita',
                ];

                continue;
            }

            $codigo = InterbankingArchivoPagoAnitaReader::padProveedor($pago['proveedor'] ?? '');
            $prov = $proveedores[$codigo] ?? ['nombre' => $codigo, 'cuit' => '', 'cbu' => ''];
            $nombre = trim((string) ($prov['nombre'] ?? ''));
            if ($nombre === '') {
                $nombre = $codigo;
            }
            $cuit = FrancesArchivoPagoFormatoSupport::cuit11((string) ($prov['cuit'] ?? ''));
            $cbu = CbuSupport::normalizar((string) ($prov['cbu'] ?? ''));

            foreach ($lineas as $linea) {
                $monto = round((float) ($linea['monto'] ?? 0), 2);
                $op = self::numero($pago, (string) ($linea['letra'] ?? ''));
                if (abs($monto) < 0.005) {
                    $omitidas[] = ['orden_pago' => $op, 'motivo' => 'Importe en cero'];

                    continue;
                }
                if (strlen($cuit) !== 11) {
                    $omitidas[] = ['orden_pago' => $op, 'motivo' => 'CUIT del proveedor incompleto'];

                    continue;
                }
                if (! CbuSupport::esValido($cbu)) {
                    $omitidas[] = ['orden_pago' => $op, 'motivo' => 'CBU del proveedor inválido o vacío'];

                    continue;
                }

                $filas[] = [
                    'tipo' => $tipoPago,
                    'orden_pago' => $op,
                    'proveedor_codigo' => $codigo,
                    'proveedor_nombre' => $nombre,
                    'fecha' => self::fechaYmd((string) ($pago['fecha'] ?? '')),
                    'importe' => $monto,
                    'cbu' => $cbu,
                    'cuit' => $cuit,
                ];
            }
        }

        usort($filas, static function (array $a, array $b): int {
            $cmp = strcmp((string) $a['fecha'], (string) $b['fecha']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['orden_pago'], (string) $b['orden_pago']);
        });

        $archivo = '';
        $total = 0.0;
        foreach ($filas as $fila) {
            $archivo .= FrancesArchivoPagoFormatoSupport::linea(
                (string) $fila['proveedor_nombre'],
                (string) $fila['cbu'],
                (float) $fila['importe'],
                (string) $fila['cuit']
            );
            $total += (float) $fila['importe'];
        }

        return [
            'filas' => $filas,
            'omitidas' => $omitidas,
            'archivo' => $archivo,
            'cantidad' => count($filas),
            'total' => round($total, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private static function clave(array $fila): string
    {
        $tipo = strtoupper(substr(trim((string) ($fila['tipo'] ?? '')), 0, 3));
        $pro = InterbankingArchivoPagoAnitaReader::padProveedor($fila['proveedor'] ?? '');

        return $tipo.'|'.(int) ($fila['numero'] ?? 0).'|'.(int) ($fila['sucursal'] ?? 0).'|'.$pro.'|'.(int) ($fila['fecha'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $pago
     */
    private static function numero(array $pago, string $letraLinea = ''): string
    {
        $letra = trim($letraLinea) !== '' ? $letraLinea : (string) ($pago['letra'] ?? '');

        return FrancesArchivoPagoFormatoSupport::numeroOp(
            $letra,
            (int) ($pago['sucursal'] ?? 0),
            (int) ($pago['numero'] ?? 0)
        );
    }

    private static function fechaYmd(string $ymd): string
    {
        $ymd = preg_replace('/\D+/', '', $ymd) ?? '';
        if (strlen($ymd) !== 8) {
            return '';
        }

        return substr($ymd, 0, 4).'-'.substr($ymd, 4, 2).'-'.substr($ymd, 6, 2);
    }
}
