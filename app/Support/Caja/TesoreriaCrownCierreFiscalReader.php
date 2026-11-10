<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownCierreFiscal;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Services\Caja\TesoreriaCrownCajaAnitaImportService;
use RuntimeException;

/**
 * Catálogo de cierres para rendiciones Crown gastro/hotel (como a-transfefe
 * → consulta_cierrefiscal.fc: solo tabla cierrefiscal, nunca resvta).
 *
 * Por día: pendientes del ERP + los de Anita que todavía no están en el ERP
 * (mismo X/Z/sucursal). Un cajero no opera a medias en los dos sistemas;
 * el cruce sirve de red de seguridad en la transición. Al guardar la
 * rendición, los elegidos quedan grabados en el ERP.
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

        $error = null;
        try {
            $pendientes = self::pendientes($fecha);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
            $pendientes = [];
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

        return ['error' => $error, 'filas' => $filas];
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
     * Pendientes del día: ERP primero; Anita completa lo que falta (sin repetir clave).
     *
     * @return list<array<string, mixed>>
     */
    public static function pendientes(string $fechaYmd): array
    {
        $erp = self::pendientesErp($fechaYmd);
        $anita = [];
        try {
            $anita = self::pendientesAnita($fechaYmd);
        } catch (RuntimeException $e) {
            if ($erp === []) {
                throw $e;
            }
            // Con ERP hay filas: Anita caído no bloquea la rendición.
        }

        $vistos = [];
        $out = [];
        foreach (array_merge($erp, $anita) as $fila) {
            if (isset($vistos[$fila['clave']])) {
                continue;
            }
            $vistos[$fila['clave']] = true;
            $out[] = $fila;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function pendientesAnita(string $fechaYmd): array
    {
        try {
            $filas = app(TesoreriaCrownCajaAnitaImportService::class)->cierresFiscalesAnita($fechaYmd);
        } catch (RuntimeException $e) {
            throw new RuntimeException('No se pudieron leer los cierres de Anita: '.$e->getMessage());
        }
        $tomados = [];
        foreach (TesoreriaCrownCierreFiscal::query()->whereDate('fecha_jor', $fechaYmd)->get(['sucursal', 'nro_x', 'nro_z']) as $erp) {
            $tomados[(int) $erp->sucursal.'|'.(int) $erp->nro_x.'|'.(int) $erp->nro_z] = true;
        }
        $fechaClave = str_replace('-', '', $fechaYmd);
        $out = [];
        foreach ($filas as $fila) {
            $nroX = (int) $fila->nro_x;
            $nroZ = (int) $fila->nro_z;
            if (($nroX === 0 && $nroZ === 0) || isset($tomados[(int) $fila->sucursal.'|'.$nroX.'|'.$nroZ])) {
                continue;
            }
            $tipo = $nroZ > 0 ? 'Z' : 'X';
            $hora = trim((string) $fila->hora);
            $out[] = [
                'clave' => TesoreriaCrownCierreFiscal::armarClave((int) $fila->sucursal, $fechaClave, $hora, $nroX, $nroZ),
                'sucursal' => (int) $fila->sucursal,
                'nombre' => trim((string) ($fila->nombre ?? '')),
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
                'fuente' => 'anita',
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function pendientesErp(string $fechaYmd): array
    {
        $rows = TesoreriaCrownCierreFiscal::query()
            ->whereDate('fecha_jor', $fechaYmd)
            ->whereNull('comprobante_id')
            ->orderBy('sucursal')
            ->orderBy('hora')
            ->get();

        $out = [];
        foreach ($rows as $fila) {
            $nroZ = (int) $fila->nro_z;
            $nroX = (int) $fila->nro_x;
            if ($nroZ === 0 && $nroX === 0) {
                continue;
            }
            $out[] = self::desdeGuardado($fila, elegido: false);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function desdeGuardado(TesoreriaCrownCierreFiscal $fila, bool $elegido = true): array
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
            'elegido' => $elegido,
            'fuente' => 'erp',
        ];
    }
}
