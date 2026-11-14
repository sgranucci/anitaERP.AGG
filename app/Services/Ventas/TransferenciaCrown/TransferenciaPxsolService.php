<?php

declare(strict_types=1);

namespace App\Services\Ventas\TransferenciaCrown;

use App\Models\Ventas\Puntoventa;
use App\Support\Ventas\TransferenciaCrown\PxsolArcaArchivoParser;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownArcaTipos;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownAsientoBuilder;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownComprobante;
use Carbon\Carbon;
use RuntimeException;

/**
 * Preview y alta de las facturas que bajan de ARCA (p-tr-pxsol.c).
 */
final class TransferenciaPxsolService
{
    public function __construct(
        private readonly PxsolArcaArchivoParser $parser,
        private readonly TransferenciaCrownAsientoBuilder $asientos,
        private readonly TransferenciaCrownFacturaWriter $writer,
        private readonly TransferenciaCrownAnitaReplicaDesdeErpSupport $replicaAnita,
    ) {
    }

    /**
     * @param  array<int, string>  $mapeo  código AFIP => abreviatura elegida
     * @return array{filas: list<array<string, mixed>>, resumen: array<string, mixed>, advertencias: list<string>}
     */
    public function previsualizar(string $path, array $mapeo = []): array
    {
        $crudas = $this->parser->leer($path);
        $max = (int) config('ventas_transferencia_crown.max_filas', 50000);
        if ($max > 0 && count($crudas) > $max) {
            throw new RuntimeException('El archivo tiene '.count($crudas).' comprobantes. El máximo de un preview es '.$max.'.');
        }

        $filas = [];
        foreach ($crudas as $cruda) {
            $filas[] = $this->normalizar($cruda, $mapeo);
        }
        $filas = $this->writer->marcarExistentes($filas);

        return [
            'filas' => $filas,
            'resumen' => $this->resumen($filas),
            'advertencias' => [
                'Se graba en el ERP y se replica la cabecera a Anita (venta + vencae). El asiento queda solo en el ERP.',
                'Cada fila entra con el tipo que elegiste. Havanna y PXSOL usan la misma imputación: la del punto de venta del archivo (venta, efectivo y tarjetas), cargada en Cuentas automáticas.',
                'Si la factura ya está en el ERP y no tiene asiento, se lo crea y engancha. El asiento manda el total a efectivo, igual que el C.',
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{grabados: int, enganchados: int, asientos: int, total_grabado: float, total_enganchado: float, errores: list<string>}
     */
    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{
     *   grabados: int,
     *   enganchados: int,
     *   asientos: int,
     *   replicadas_anita: int,
     *   total_grabado: float,
     *   total_enganchado: float,
     *   errores: list<string>
     * }
     */
    public function confirmar(array $filas, int $usuarioId): array
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '900');

        $grabados = 0;
        $enganchados = 0;
        $replicadasAnita = 0;
        $totalGrabado = 0.0;
        $totalEnganchado = 0.0;
        $errores = [];
        foreach ($filas as $fila) {
            $estado = (string) ($fila['estado'] ?? '');
            if ($estado !== 'nuevo' && $estado !== 'enganchar') {
                continue;
            }
            $etiqueta = TransferenciaCrownComprobante::codigoVisible($fila);
            $signo = TransferenciaCrownComprobante::esCredito($fila) ? -1 : 1;
            $importe = round($signo * abs((float) ($fila['total'] ?? 0)), 2);
            try {
                $fila = $this->asegurarVtoCae($fila);
                if ($estado === 'enganchar') {
                    $this->writer->enganchar($fila, $usuarioId, 'Importación ARCA '.$etiqueta);
                    $enganchados++;
                    $totalEnganchado = round($totalEnganchado + $importe, 2);
                    $ventaId = (int) ($fila['venta_id'] ?? 0);
                } else {
                    $grabado = $this->writer->grabar($fila, $usuarioId, 'Importación ARCA '.$etiqueta);
                    $grabados++;
                    $totalGrabado = round($totalGrabado + $importe, 2);
                    $ventaId = (int) ($grabado['venta_id'] ?? 0);
                }
                if ($ventaId > 0) {
                    $rep = $this->replicaAnita->replicarVentaId($ventaId);
                    if ($rep['ok']) {
                        $replicadasAnita++;
                    } else {
                        $errores[] = $etiqueta.': ERP OK; Anita: '.$rep['mensaje'];
                    }
                }
            } catch (\Throwable $e) {
                $errores[] = $etiqueta.': '.$e->getMessage();
            }
        }

        return [
            'grabados' => $grabados,
            'enganchados' => $enganchados,
            'asientos' => $grabados + $enganchados,
            'replicadas_anita' => $replicadasAnita,
            'total_grabado' => $totalGrabado,
            'total_enganchado' => $totalEnganchado,
            'errores' => $errores,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function asegurarVtoCae(array $fila): array
    {
        if (trim((string) ($fila['fechavencimientocae'] ?? '')) !== '') {
            return $fila;
        }
        if (trim((string) ($fila['cae'] ?? '')) === '') {
            return $fila;
        }
        $fecha = trim((string) ($fila['fecha'] ?? ''));
        if ($fecha === '') {
            return $fila;
        }
        try {
            $fila['fechavencimientocae'] = Carbon::parse($fecha)->addDays(10)->format('Y-m-d');
        } catch (\Throwable) {
            // sin vto: la replica Anita puede omitir vencae
        }

        return $fila;
    }

    /**
     * @param  array<string, mixed>  $cruda
     * @param  array<int, string>  $mapeo
     * @return array<string, mixed>
     */
    private function normalizar(array $cruda, array $mapeo): array
    {
        $tipoAnita = strtoupper((string) $cruda['tipo']);
        $letra = strtoupper((string) $cruda['letra']);
        $tipoAfip = (int) ($cruda['tipo_afip'] ?? 0);
        $tipo = $mapeo[$tipoAfip] ?? TransferenciaCrownArcaTipos::defecto($tipoAfip);
        if (! isset(TransferenciaCrownArcaTipos::OPCIONES[$tipo])) {
            $tipo = TransferenciaCrownComprobante::abreviaturaErp($tipoAnita);
        }
        $fila = $cruda;
        $fila['tipo'] = $tipo;
        $fila['tipo_anita'] = $tipoAnita;
        $fila['letra'] = $letra;
        $fila['abreviatura_erp'] = $tipo;
        $fila['codigo_afip'] = $tipoAfip > 0
            ? $tipoAfip
            : TransferenciaCrownComprobante::codigoAfip($tipo, $letra);
        $fila['estado'] = 'nuevo';
        $fila['mensaje'] = '';
        $fila['asiento_origen'] = 'generado';
        $fila['nro_operacion'] = 0;
        $fila['lineas_asiento'] = [];

        if ((int) $fila['sucursal'] <= 0 || (int) $fila['numero'] <= 0) {
            $fila['estado'] = 'error';
            $fila['mensaje'] = 'Falta punto de venta o número.';

            return $fila;
        }
        if ((float) $fila['total'] <= 0 && (float) $fila['gravado21'] <= 0) {
            $fila['total'] = 0.01;
            $fila['gravado21'] = 0.01;
        }

        $pv = Puntoventa::query()
            ->where('codigo', str_pad((string) (int) $fila['sucursal'], 5, '0', STR_PAD_LEFT))
            ->orderBy('id')
            ->first();
        if ($pv === null) {
            $fila['estado'] = 'error';
            $fila['mensaje'] = 'No existe el punto de venta '.$fila['sucursal'].'.';

            return $fila;
        }

        try {
            $asiento = $this->asientos->generar((int) $pv->empresa_id, $fila, 'pxsol');
            $fila['lineas_asiento'] = $asiento['lineas'];
            $fila['asiento_origen'] = 'generado';
            $desbalance = TransferenciaCrownComprobante::desbalance($asiento['lineas']);
            if (abs($desbalance) > 0.05) {
                $fila['estado'] = 'error';
                $fila['mensaje'] = 'El asiento no balancea ('.$desbalance.').';
            }
        } catch (\Throwable $e) {
            $fila['estado'] = 'error';
            $fila['mensaje'] = $e->getMessage();
        }

        return $fila;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float|int>
     */
    private function resumen(array $filas): array
    {
        $resumen = ['leidos' => count($filas), 'nuevos' => 0, 'enganchar' => 0, 'existen' => 0, 'errores' => 0, 'total_nuevos' => 0.0];
        foreach ($filas as $fila) {
            if ($fila['estado'] === 'nuevo') {
                $resumen['nuevos']++;
                $signo = TransferenciaCrownComprobante::esCredito($fila) ? -1 : 1;
                $resumen['total_nuevos'] += $signo * (float) $fila['total'];
            } elseif ($fila['estado'] === 'enganchar') {
                $resumen['enganchar']++;
            } elseif ($fila['estado'] === 'existe') {
                $resumen['existen']++;
            } else {
                $resumen['errores']++;
            }
        }
        $resumen['total_nuevos'] = round($resumen['total_nuevos'], 2);

        return $resumen;
    }
}
