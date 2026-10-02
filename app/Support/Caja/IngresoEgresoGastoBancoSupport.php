<?php

namespace App\Support\Caja;

use App\Models\Caja\Cuentacaja;
use App\Models\Compras\Proveedor;
use App\Support\Compras\ComprobanteProveedorTipoTesoreria;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;

/**
 * En un gasto bancario el emisor es el banco de la cuenta de caja del egreso.
 * No se pide un proveedor: si el CUIT del banco está en el maestro se usa ese
 * proveedor; si no, el comprobante queda a nombre del banco.
 */
final class IngresoEgresoGastoBancoSupport
{
    public static function esGastoBanco(?string $tipoTesoreria): bool
    {
        return (string) $tipoTesoreria === ComprobanteProveedorTipoTesoreria::GASTO_BANCO;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas  cuentacaja_id + monto
     * @return array<string, mixed>
     */
    public static function resolver(array $lineas, ?int $cuentacajaElegida = null): array
    {
        $eleccion = self::elegir(self::cargarCuentas($lineas), $cuentacajaElegida);
        if (! ($eleccion['ok'] ?? false)) {
            return $eleccion;
        }

        return self::conIdentidadFiscal($eleccion);
    }

    /**
     * @param  list<array<string, mixed>>  $cuentas
     * @return array<string, mixed>
     */
    public static function elegir(array $cuentas, ?int $cuentacajaElegida = null): array
    {
        $conBanco = array_values(array_filter(
            $cuentas,
            static fn (array $cuenta): bool => (int) ($cuenta['banco_id'] ?? 0) > 0
        ));

        if ($conBanco === []) {
            return self::rechazo(
                'Cargá primero la cuenta de caja del banco. El gasto bancario toma el banco de esa cuenta.'
            );
        }

        if ($cuentacajaElegida !== null && $cuentacajaElegida > 0) {
            foreach ($conBanco as $cuenta) {
                if ((int) $cuenta['cuentacaja_id'] === $cuentacajaElegida) {
                    return self::aceptada($cuenta);
                }
            }
        }

        $porBanco = [];
        foreach ($conBanco as $cuenta) {
            $porBanco[(int) $cuenta['banco_id']][] = $cuenta;
        }

        if (count($porBanco) === 1) {
            return self::aceptada(self::preferida($conBanco));
        }

        return [
            'ok' => false,
            'ambiguo' => true,
            'mensaje' => 'Hay más de un banco en las cuentas de caja. Elegí de cuál es el gasto.',
            'candidatos' => array_map(static fn (array $cuenta): array => self::candidato($cuenta), $conBanco),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $resolucion
     * @return array<string, mixed>
     */
    public static function volcarEnPayload(array $payload, array $resolucion): array
    {
        $payload['cuentacaja_id'] = (int) ($resolucion['cuentacaja_id'] ?? 0);
        $proveedorId = (int) ($resolucion['proveedor_id'] ?? 0);
        $payload['proveedor_id'] = $proveedorId;
        $payload['proveedor_codigo'] = (string) ($resolucion['proveedor_codigo'] ?? '');
        $payload['proveedor_nombre'] = (string) ($resolucion['etiqueta'] ?? $resolucion['banco_nombre'] ?? '');

        if ($proveedorId > 0) {
            $payload['proveedor_nombre_eventual'] = '';
            $payload['proveedor_documento_eventual'] = '';
            $payload['proveedor_condicioniva_id_eventual'] = null;

            return $payload;
        }

        $payload['proveedor_nombre_eventual'] = (string) ($resolucion['banco_nombre'] ?? '');
        $payload['proveedor_documento_eventual'] = (string) ($resolucion['cuit'] ?? '');
        $condicion = (int) ($resolucion['condicioniva_id'] ?? 0);
        $payload['proveedor_condicioniva_id_eventual'] = $condicion > 0 ? $condicion : null;

        return $payload;
    }

    public static function payloadYaIdentificaBanco(array $payload): bool
    {
        if ((int) ($payload['proveedor_id'] ?? 0) > 0) {
            return true;
        }

        return ComprobanteProveedorUnicidadSupport::normalizarCuitDigitos(
            (string) ($payload['proveedor_documento_eventual'] ?? '')
        ) !== '';
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private static function cargarCuentas(array $lineas): array
    {
        $ids = [];
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $id = (int) ($linea['cuentacaja_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $cuentas = Cuentacaja::query()
            ->with('bancos')
            ->whereIn('id', array_values($ids))
            ->get()
            ->keyBy('id');

        $salida = [];
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $id = (int) ($linea['cuentacaja_id'] ?? 0);
            $cuenta = $cuentas->get($id);
            if ($cuenta === null) {
                continue;
            }
            $banco = $cuenta->bancos;
            $salida[] = [
                'cuentacaja_id' => (int) $cuenta->id,
                'codigo' => (string) ($cuenta->codigo ?? ''),
                'nombre' => (string) ($cuenta->nombre ?? ''),
                'monto' => (float) ($linea['monto'] ?? 0),
                'banco_id' => (int) ($cuenta->banco_id ?? 0),
                'banco_nombre' => (string) ($banco->nombre ?? ''),
                'banco_cuit' => (string) ($banco->nroinscripcion ?? ''),
                'condicioniva_id' => (int) ($banco->condicioniva_id ?? 0),
            ];
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $eleccion
     * @return array<string, mixed>
     */
    private static function conIdentidadFiscal(array $eleccion): array
    {
        $cuit = ComprobanteProveedorUnicidadSupport::normalizarCuitDigitos((string) ($eleccion['banco_cuit'] ?? ''));
        if ($cuit === '') {
            return self::rechazo(
                'La cuenta '.$eleccion['cuenta_codigo'].' es del banco '.$eleccion['banco_nombre']
                .', pero ese banco no tiene CUIT en el maestro. Cargalo en Bancos; no hace falta elegir un proveedor.'
            );
        }

        $proveedor = Proveedor::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), ' ', ''), '.', '') = ?",
                [$cuit]
            )
            ->orderBy('id')
            ->first(['id', 'codigo', 'nombre']);

        $eleccion['cuit'] = $cuit;
        $eleccion['cuit_formato'] = substr($cuit, 0, 2).'-'.substr($cuit, 2, 8).'-'.substr($cuit, 10, 1);
        $eleccion['proveedor_id'] = (int) ($proveedor->id ?? 0);
        $eleccion['proveedor_codigo'] = (string) ($proveedor->codigo ?? '');
        $eleccion['proveedor_nombre'] = (string) ($proveedor->nombre ?? '');
        $eleccion['etiqueta'] = trim($eleccion['banco_nombre'].' · '.$eleccion['cuit_formato']);
        if ($eleccion['cuenta_codigo'] !== '') {
            $eleccion['etiqueta'] .= ' · cuenta '.$eleccion['cuenta_codigo'];
        }

        return $eleccion;
    }

    /**
     * Entre cuentas del mismo banco, la del egreso (monto más negativo).
     *
     * @param  list<array<string, mixed>>  $cuentas
     * @return array<string, mixed>
     */
    private static function preferida(array $cuentas): array
    {
        usort($cuentas, static function (array $a, array $b): int {
            $monto = ((float) $a['monto']) <=> ((float) $b['monto']);
            if ($monto !== 0) {
                return $monto;
            }

            return ((int) $a['cuentacaja_id']) <=> ((int) $b['cuentacaja_id']);
        });

        return $cuentas[0];
    }

    /**
     * @param  array<string, mixed>  $cuenta
     * @return array<string, mixed>
     */
    private static function aceptada(array $cuenta): array
    {
        return array_merge(self::candidato($cuenta), [
            'ok' => true,
            'ambiguo' => false,
            'mensaje' => null,
            'banco_cuit' => (string) ($cuenta['banco_cuit'] ?? ''),
            'condicioniva_id' => (int) ($cuenta['condicioniva_id'] ?? 0),
        ]);
    }

    /**
     * @param  array<string, mixed>  $cuenta
     * @return array<string, mixed>
     */
    private static function candidato(array $cuenta): array
    {
        return [
            'cuentacaja_id' => (int) $cuenta['cuentacaja_id'],
            'cuenta_codigo' => (string) ($cuenta['codigo'] ?? ''),
            'cuenta_nombre' => (string) ($cuenta['nombre'] ?? ''),
            'monto' => (float) ($cuenta['monto'] ?? 0),
            'banco_id' => (int) ($cuenta['banco_id'] ?? 0),
            'banco_nombre' => (string) ($cuenta['banco_nombre'] ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private static function rechazo(string $mensaje): array
    {
        return [
            'ok' => false,
            'ambiguo' => false,
            'mensaje' => $mensaje,
            'candidatos' => [],
        ];
    }
}
