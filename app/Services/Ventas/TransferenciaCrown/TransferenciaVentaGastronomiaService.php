<?php

declare(strict_types=1);

namespace App\Services\Ventas\TransferenciaCrown;

use App\Models\Ventas\Puntoventa;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownAnitaConsulta;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownAsientoBuilder;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownComprobante;
use Carbon\Carbon;
use RuntimeException;

/**
 * Trae venta de una sala (p-tr-ven.c) en una sola lectura Anita y la deja como factura del ERP.
 */
final class TransferenciaVentaGastronomiaService
{
    private const TIPOS = ['FAC', 'FAH', 'NCH', 'TIC', 'NCD'];

    public function __construct(
        private readonly TransferenciaCrownAnitaConsulta $anita,
        private readonly TransferenciaCrownAsientoBuilder $asientos,
        private readonly TransferenciaCrownFacturaWriter $writer,
    ) {
    }

    /**
     * @param  array{codigo: string, nombre: string, ifx_server: string}  $sala
     * @return array{filas: list<array<string, mixed>>, resumen: array<string, mixed>, advertencias: list<string>}
     */
    public function previsualizar(array $sala, string $desde, string $hasta): array
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '600');

        $desdeYmd = (int) Carbon::parse($desde)->format('Ymd');
        $hastaYmd = (int) Carbon::parse($hasta)->format('Ymd');
        $dias = Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1;
        $maxDias = (int) config('ventas_transferencia_crown.max_dias', 31);
        if ($dias > $maxDias) {
            throw new RuntimeException('El rango máximo es de '.$maxDias.' días.');
        }

        $tipos = "'".implode("','", self::TIPOS)."'";
        // Una sola lectura Anita: tabla venta del servidor de la sala, rango completo.
        $ventas = $this->anita->listar(
            'ventas',
            'venta',
            'ven_tipo, ven_letra, ven_sucursal, ven_nro, ven_fecha, ven_fecha_vto, ven_exento, ven_gravado, ven_gravado_ot, ven_tasa_iva_ot, ven_impuesto1, ven_no_inscripto, ven_percepcion_iva, ven_monto, ven_cod_mon, ven_cotizacion, ven_cuit_cli, ven_nombre_cliente',
            ' WHERE ven_fecha_vto >= '.$desdeYmd.' AND ven_fecha_vto <= '.$hastaYmd.' AND ven_tipo IN ('.$tipos.')',
            $sala['ifx_server'],
        );

        $max = (int) config('ventas_transferencia_crown.max_filas', 50000);
        if ($max > 0 && count($ventas) > $max) {
            throw new RuntimeException(
                'La sala tiene '.count($ventas).' comprobantes en el rango (tope '.$max.').'
                .' Achicá las fechas o subí VENTAS_TRANSFERENCIA_CROWN_MAX_FILAS.'
            );
        }

        $advertencias = [];
        $porComprobante = [];
        if (filter_var(config('ventas_transferencia_crown.leer_subdiario', false), FILTER_VALIDATE_BOOLEAN)) {
            try {
                $subdiario = $this->anita->listar(
                    'contab',
                    'subdiario',
                    'subd_tipo, subd_letra, subd_sucursal, subd_nro, subd_fecha, subd_tipo_mov, subd_cuenta, subd_contrapartida, subd_importe, subd_nro_operacion, subd_cod_mon, subd_cotizacion, subd_desc_mov',
                    ' WHERE subd_sistema = \'V\' AND subd_fecha >= '.$desdeYmd.' AND subd_fecha <= '.$hastaYmd.' AND subd_tipo IN ('.$tipos.')',
                    $sala['ifx_server'],
                );
                foreach ($subdiario as $linea) {
                    $porComprobante[$this->claveSubdiario($linea)][] = $linea;
                }
            } catch (\Throwable $e) {
                $advertencias[] = 'No se pudo leer el subdiario de '.$sala['nombre'].' ('.$e->getMessage().'). El asiento se arma con las cuentas del punto de venta.';
            }
        }

        $puntosVenta = Puntoventa::query()
            ->orderBy('id')
            ->get(['id', 'codigo', 'empresa_id', 'nombre'])
            ->keyBy(static fn ($pv): string => str_pad((string) $pv->codigo, 5, '0', STR_PAD_LEFT));

        $filas = [];
        foreach ($ventas as $venta) {
            $filas[] = $this->normalizar($venta, $sala, $porComprobante, $puntosVenta);
        }
        $filas = $this->writer->marcarExistentes($filas);

        $advertencias[] = 'Solo se graba en el ERP. No escribe en Anita (ni venta ni asiento/ctamov).';
        $advertencias[] = 'La factura, la factura hotel y el ticket entran como factura (FAC). La nota de crédito entra como nota de crédito. Si la factura ya está y no tiene asiento, este proceso se lo engancha.';
        if ($porComprobante !== []) {
            $advertencias[] = 'Donde la sala ya tiene subdiario, el asiento copia esas cuentas. Donde no, usa las cuentas de gastronomía del punto de venta.';
        } else {
            $advertencias[] = 'Lectura única de venta en Anita. El asiento usa las cuentas de gastronomía del punto de venta (Cuentas automáticas).';
        }

        return [
            'filas' => $filas,
            'resumen' => $this->resumen($filas),
            'advertencias' => $advertencias,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{grabados: int, enganchados: int, asientos: int, total_grabado: float, total_enganchado: float, errores: list<string>}
     */
    public function confirmar(array $filas, int $usuarioId, string $salaNombre): array
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '900');

        $grabados = 0;
        $enganchados = 0;
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
                if ($estado === 'enganchar') {
                    $this->writer->enganchar($fila, $usuarioId, 'Transferencia gastronomía '.$salaNombre.' '.$etiqueta);
                    $enganchados++;
                    $totalEnganchado = round($totalEnganchado + $importe, 2);
                } else {
                    $this->writer->grabar($fila, $usuarioId, 'Transferencia gastronomía '.$salaNombre.' '.$etiqueta);
                    $grabados++;
                    $totalGrabado = round($totalGrabado + $importe, 2);
                }
            } catch (\Throwable $e) {
                $errores[] = $etiqueta.': '.$e->getMessage();
            }
        }

        return [
            'grabados' => $grabados,
            'enganchados' => $enganchados,
            'asientos' => $grabados + $enganchados,
            'total_grabado' => $totalGrabado,
            'total_enganchado' => $totalEnganchado,
            'errores' => $errores,
        ];
    }

    /**
     * @param  array{codigo: string, nombre: string, ifx_server: string}  $sala
     * @param  array<string, list<object>>  $porComprobante
     * @param  \Illuminate\Support\Collection<string, Puntoventa>  $puntosVenta
     * @return array<string, mixed>
     */
    private function normalizar(object $venta, array $sala, array $porComprobante, $puntosVenta): array
    {
        $tipo = strtoupper(trim((string) ($venta->ven_tipo ?? '')));
        $letra = strtoupper(trim((string) ($venta->ven_letra ?? '')));
        $sucursal = (int) ($venta->ven_sucursal ?? 0);
        $numero = (int) ($venta->ven_nro ?? 0);
        $gravadoOt = round(abs((float) ($venta->ven_gravado_ot ?? 0)), 2);
        $tasaOt = round(abs((float) ($venta->ven_tasa_iva_ot ?? 0)), 2);
        $iva = round(abs((float) ($venta->ven_impuesto1 ?? 0)), 2);
        $gravado21 = round(abs((float) ($venta->ven_gravado ?? 0)), 2);
        $gravado105 = 0.0;
        $gravado27 = 0.0;
        $iva105 = 0.0;
        $iva27 = 0.0;
        $iva21 = $iva;
        if ($gravadoOt > 0 && abs($tasaOt - 27) < 0.01) {
            $gravado27 = $gravadoOt;
            $iva27 = round($gravadoOt * 0.27, 2);
            $iva21 = round(max(0, $iva - $iva27), 2);
        } elseif ($gravadoOt > 0) {
            $gravado105 = $gravadoOt;
            $tasa = $tasaOt > 0 ? $tasaOt : 10.5;
            $iva105 = round($gravadoOt * $tasa / 100, 2);
            $iva21 = round(max(0, $iva - $iva105), 2);
        }

        $tipoErp = str_starts_with($tipo, 'NC') ? 'NCD' : 'FAC';
        $fechas = TransferenciaCrownComprobante::fechasEmisionYJornada(
            $venta->ven_fecha ?? null,
            $venta->ven_fecha_vto ?? null,
            Carbon::today()->format('Y-m-d'),
        );
        $fila = [
            'tipo' => $tipoErp,
            'tipo_anita' => $tipo,
            'letra' => $letra !== '' ? $letra : 'B',
            'sucursal' => $sucursal,
            'numero' => $numero,
            'fecha' => $fechas['fecha'],
            'fechajornada' => $fechas['fechajornada'],
            'nombre' => trim((string) ($venta->ven_nombre_cliente ?? '')),
            'cuit' => preg_replace('/\D+/', '', (string) ($venta->ven_cuit_cli ?? '')) ?? '',
            'domicilio' => '',
            'cae' => '',
            'moneda' => trim((string) ($venta->ven_cod_mon ?? '1')) !== '' ? trim((string) $venta->ven_cod_mon) : '1',
            'cotizacion' => abs((float) ($venta->ven_cotizacion ?? 0)),
            'gravado21' => $gravado21,
            'iva21' => $iva21,
            'gravado105' => $gravado105,
            'iva105' => $iva105,
            'gravado27' => $gravado27,
            'iva27' => $iva27,
            'exento' => round(abs((float) ($venta->ven_exento ?? 0)), 2),
            'noi' => round(abs((float) ($venta->ven_no_inscripto ?? 0)), 2),
            'percepcion_iva' => round(abs((float) ($venta->ven_percepcion_iva ?? 0)), 2),
            'otros' => 0.0,
            'total' => round(abs((float) ($venta->ven_monto ?? 0)), 2),
            'abreviatura_erp' => $tipoErp,
            'codigo_afip' => TransferenciaCrownComprobante::codigoAfip($tipoErp, $letra !== '' ? $letra : 'B'),
            'estado' => 'nuevo',
            'mensaje' => '',
            'asiento_origen' => 'generado',
            'nro_operacion' => 0,
            'lineas_asiento' => [],
            'sala' => $sala['nombre'],
        ];

        if ($sucursal <= 0 || $numero <= 0) {
            $fila['estado'] = 'error';
            $fila['mensaje'] = 'Comprobante sin sucursal o número.';

            return $fila;
        }

        $pvCodigo = str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT);
        $pv = $puntosVenta->get($pvCodigo);
        if ($pv === null) {
            $fila['estado'] = 'error';
            $fila['mensaje'] = 'No existe el punto de venta '.$sucursal.'.';

            return $fila;
        }

        $clave = $tipo.'|'.$fila['letra'].'|'.$sucursal.'|'.$numero;
        $lineasSub = $porComprobante[$clave] ?? [];
        try {
            if ($lineasSub !== []) {
                $asiento = $this->asientos->desdeSubdiario((int) $pv->empresa_id, $lineasSub, $fila);
            } else {
                $asiento = $this->asientos->generar((int) $pv->empresa_id, $fila, 'gastronomia');
            }
            $fila['lineas_asiento'] = $asiento['lineas'];
            $fila['asiento_origen'] = $asiento['origen'];
            $fila['nro_operacion'] = $asiento['nro_operacion'];
            if ($asiento['lineas'] === []) {
                $fila['estado'] = 'error';
                $fila['mensaje'] = 'El subdiario no tiene importes para armar el asiento.';
            }
            $desbalance = TransferenciaCrownComprobante::desbalance($asiento['lineas']);
            if ($fila['estado'] === 'nuevo' && abs($desbalance) > 0.05) {
                $fila['estado'] = 'error';
                $fila['mensaje'] = 'El asiento no balancea ('.$desbalance.').';
            }
        } catch (\Throwable $e) {
            $fila['estado'] = 'error';
            $fila['mensaje'] = $e->getMessage();
        }

        return $fila;
    }

    private function claveSubdiario(object $linea): string
    {
        return strtoupper(trim((string) ($linea->subd_tipo ?? '')))
            .'|'.strtoupper(trim((string) ($linea->subd_letra ?? '')))
            .'|'.(int) ($linea->subd_sucursal ?? 0)
            .'|'.(int) ($linea->subd_nro ?? 0);
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
