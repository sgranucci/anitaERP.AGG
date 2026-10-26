<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownCierreFiscal;
use App\Models\Caja\TesoreriaCrownComprobante;
use RuntimeException;

/**
 * Lee ventas.cierrefiscal, el cierre que graba p-cierrefact.
 * Un informe con número Z es el cierre fiscal. Un informe con número X y Z en cero
 * es el que la rendición de hotel toma y marca rendido.
 */
final class TesoreriaCrownCierreFiscalReader
{
    /**
     * @return array{error: ?string, filas: list<array<string, mixed>>}
     */
    public static function paraRendicion(TesoreriaCrownComprobante $comprobante): array
    {
        $propios = $comprobante->cierresFiscales;
        $fecha = $comprobante->jornada->fecha->format('Y-m-d');
        $elegidos = [];
        foreach ($propios as $fila) {
            $elegidos[$fila->clave()] = $fila;
        }

        try {
            $pendientes = self::pendientes($fecha);
        } catch (RuntimeException $e) {
            return [
                'error' => $e->getMessage(),
                'filas' => array_values(array_map(fn (TesoreriaCrownCierreFiscal $fila) => self::desdeGuardado($fila), $elegidos)),
            ];
        }

        $vistos = [];
        $filas = [];
        foreach ($pendientes as $fila) {
            $fila['elegido'] = isset($elegidos[$fila['clave']]);
            $vistos[$fila['clave']] = true;
            $filas[] = $fila;
        }
        foreach ($elegidos as $clave => $guardado) {
            if (isset($vistos[$clave])) {
                continue;
            }
            $filas[] = self::desdeGuardado($guardado);
        }
        usort($filas, function (array $a, array $b): int {
            return [$a['sucursal'], $a['hora'], $a['tipo']] <=> [$b['sucursal'], $b['hora'], $b['tipo']];
        });

        return ['error' => null, 'filas' => $filas];
    }

    /**
     * @param  list<string>  $claves
     * @return list<array<string, mixed>>
     */
    public static function filasPorClaves(TesoreriaCrownComprobante $comprobante, array $claves): array
    {
        $pedido = array_fill_keys($claves, true);
        $catalogo = self::paraRendicion($comprobante);
        if ($catalogo['error'] !== null && $catalogo['filas'] === []) {
            throw new RuntimeException($catalogo['error']);
        }
        $out = [];
        foreach ($catalogo['filas'] as $fila) {
            if (isset($pedido[$fila['clave']])) {
                $out[] = $fila;
                unset($pedido[$fila['clave']]);
            }
        }
        if ($pedido !== []) {
            throw new RuntimeException('Hay un cierre de ventas que ya no está pendiente.');
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function pendientes(string $fechaYmd): array
    {
        $fecha = (int) str_replace('-', '', $fechaYmd);
        $crudos = self::consultar(
            'sucursal,fecha_jor,hora,nro_x,nro_z,tot_venta,tot_cred,tot_iva,tot_iva_cred,ult_fact_b',
            'SELECT cief_sucursal, cief_fecha_jor, cief_hora, cief_nro_x, cief_nro_z, cief_tot_venta, cief_tot_cred, cief_tot_iva, cief_tot_iva_cred, cief_ult_fact_b'
            .' FROM cierrefiscal WHERE cief_fecha_jor = '.$fecha.' AND cief_estado = "P"'
        );
        $sucursales = [];
        foreach ($crudos as $fila) {
            $sucursales[(int) $fila->sucursal] = true;
        }
        $nombres = self::nombresSucursal(array_keys($sucursales));
        $out = [];
        foreach ($crudos as $fila) {
            $nroZ = (int) $fila->nro_z;
            $nroX = (int) $fila->nro_x;
            if ($nroZ === 0 && $nroX === 0) {
                continue;
            }
            $tipo = $nroZ > 0 ? 'Z' : 'X';
            $sucursal = (int) $fila->sucursal;
            $hora = substr(trim((string) $fila->hora), 0, 8);
            $out[] = [
                'clave' => TesoreriaCrownCierreFiscal::armarClave($sucursal, (string) $fecha, $hora, $nroX, $nroZ),
                'sucursal' => $sucursal,
                'nombre' => $nombres[$sucursal] ?? '',
                'fecha' => $fechaYmd,
                'hora' => $hora,
                'tipo' => $tipo,
                'numero' => $tipo === 'Z' ? $nroZ : $nroX,
                'nro_x' => $nroX,
                'nro_z' => $nroZ,
                'venta' => round((float) $fila->tot_venta, 2),
                'credito' => round((float) $fila->tot_cred, 2),
                'iva' => round((float) $fila->tot_iva, 2),
                'iva_credito' => round((float) $fila->tot_iva_cred, 2),
                'ult_ticket' => (int) $fila->ult_fact_b,
                'elegido' => false,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $numeros
     * @return array<int, string>
     */
    private static function nombresSucursal(array $numeros): array
    {
        $numeros = array_values(array_filter(array_map('intval', $numeros)));
        if ($numeros === []) {
            return [];
        }
        $filas = self::consultar(
            'numero,empresa',
            'SELECT suc_numero, suc_empresa FROM sucursal WHERE suc_numero IN ('.implode(',', $numeros).')'
        );
        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->numero] = trim((string) $fila->empresa);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function desdeGuardado(TesoreriaCrownCierreFiscal $fila): array
    {
        return [
            'clave' => $fila->clave(),
            'sucursal' => (int) $fila->sucursal,
            'nombre' => (string) $fila->nombre_sucursal,
            'fecha' => $fila->fecha_jor?->format('Y-m-d') ?? '',
            'hora' => trim((string) $fila->hora),
            'tipo' => (string) $fila->tipo,
            'numero' => $fila->tipo === 'Z' ? (int) $fila->nro_z : (int) $fila->nro_x,
            'nro_x' => (int) $fila->nro_x,
            'nro_z' => (int) $fila->nro_z,
            'venta' => round((float) $fila->tot_venta, 2),
            'credito' => round((float) $fila->tot_cred, 2),
            'iva' => round((float) $fila->tot_iva, 2),
            'iva_credito' => round((float) $fila->tot_iva_cred, 2),
            'ult_ticket' => (int) $fila->ult_fact_b,
            'elegido' => true,
        ];
    }

    /**
     * @return list<object>
     */
    private static function consultar(string $campos, string $sql): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'customSql',
            'campos' => $campos,
            'customSql' => $sql,
            'sistema' => 'ventas',
            'ifx_server' => config('caja_anita.ifx_server'),
            'path_sistema' => config('caja_anita.path_sistema'),
        ];
        $raw = $api->apiCall($payload);
        $error = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($error !== null && $error !== '') {
            throw new RuntimeException($error);
        }
        $filas = json_decode(is_string($raw) ? $raw : '[]');

        return is_array($filas) ? $filas : [];
    }
}
