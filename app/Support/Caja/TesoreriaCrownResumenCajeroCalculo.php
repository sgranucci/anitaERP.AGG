<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMesa;

/**
 * Resumen de cajeros (l-rescajero.c) y base del anexo III (l-anexo3new.c).
 *
 * Columnas del listado Anita:
 * - Egresos = efectivo habilitado
 * - Ingresos = efectivo rendido (billetes + tarjetas − recargo + débito + QR), sin fichas ni cashless
 * - Fichas / Pago·Vta cashless / voucher / online = detalle de la rendición
 * - Resultado = (ingresos − egresos) + cashless neto + fichas + online neto + voucher neto + apuestas
 * - Dif. cajero = misma diferencia del cierre
 *
 * Orden: agrupa por tipo de caja (E → G → H → K → T), con subtítulo y subtotal por grupo.
 */
final class TesoreriaCrownResumenCajeroCalculo
{
    /** @var list<string> */
    public const ORDEN_TIPOS = ['E', 'G', 'H', 'K', 'L', 'T'];

    /** @var array<string, string> */
    public const TITULOS_TIPO = [
        'E' => 'Caja empl.',
        'G' => 'Gastronomia',
        'H' => 'Hotel',
        'K' => 'Kiosco',
        'L' => 'Cashless',
        'T' => 'Venta y Pago',
    ];

    /**
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     grupos: list<array{tipo: string, titulo: string, filas: list<array<string, mixed>>, totales: array<string, float>}>,
     *     totales: array<string, float>,
     *     avisos: list<string>
     * }
     */
    public static function deJornada(TesoreriaCrownJornada $jornada): array
    {
        $asignaciones = $jornada->asignaciones()
            ->with([
                'deposito',
                'cajero',
                'cierre',
                'comprobantes.lineas.valor.tipoValor',
                'comprobantes.reversa',
                'jornada',
            ])
            ->orderBy('deposito_id')
            ->get();

        $porTipo = [];
        foreach ($asignaciones as $asignacion) {
            $fila = self::deAsignacion($asignacion);
            if ($fila === null) {
                continue;
            }
            $tipo = (string) $fila['tarea'];
            $porTipo[$tipo][] = $fila;
        }

        $grupos = [];
        $filas = [];
        $totales = self::ceros();
        foreach (self::ORDEN_TIPOS as $tipo) {
            if (empty($porTipo[$tipo])) {
                continue;
            }
            $filasTipo = $porTipo[$tipo];
            usort($filasTipo, function (array $a, array $b): int {
                return strnatcasecmp((string) $a['caja'], (string) $b['caja']);
            });
            if ($tipo === 'E') {
                $propina = self::filaPropina($jornada, $filasTipo);
                if ($propina !== null) {
                    $filasTipo[] = $propina;
                }
            }
            $sub = self::ceros();
            foreach ($filasTipo as $fila) {
                foreach (array_keys($sub) as $clave) {
                    $sub[$clave] = round($sub[$clave] + (float) ($fila[$clave] ?? 0), 2);
                    $totales[$clave] = round($totales[$clave] + (float) ($fila[$clave] ?? 0), 2);
                }
                $filas[] = $fila;
            }
            $grupos[] = [
                'tipo' => $tipo,
                'titulo' => self::TITULOS_TIPO[$tipo] ?? (TesoreriaCrownAsignacionCatalogo::TAREAS[$tipo] ?? $tipo),
                'filas' => $filasTipo,
                'totales' => $sub,
            ];
            unset($porTipo[$tipo]);
        }
        foreach ($porTipo as $tipo => $filasTipo) {
            usort($filasTipo, fn (array $a, array $b) => strnatcasecmp((string) $a['caja'], (string) $b['caja']));
            $sub = self::ceros();
            foreach ($filasTipo as $fila) {
                foreach (array_keys($sub) as $clave) {
                    $sub[$clave] = round($sub[$clave] + (float) ($fila[$clave] ?? 0), 2);
                    $totales[$clave] = round($totales[$clave] + (float) ($fila[$clave] ?? 0), 2);
                }
                $filas[] = $fila;
            }
            $grupos[] = [
                'tipo' => $tipo,
                'titulo' => TesoreriaCrownAsignacionCatalogo::TAREAS[$tipo] ?? $tipo,
                'filas' => $filasTipo,
                'totales' => $sub,
            ];
        }

        return [
            'filas' => $filas,
            'grupos' => $grupos,
            'totales' => $totales,
            'avisos' => $filas === []
                ? ['No hay cajas con rendición conforme en esta jornada.']
                : [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function deAsignacion(TesoreriaCrownAsignacion $asignacion): ?array
    {
        $asignacion->loadMissing(['deposito', 'cajero', 'cierre', 'comprobantes.lineas.valor.tipoValor', 'comprobantes.reversa', 'jornada']);
        $conforme = $asignacion->comprobantes->filter(
            fn (TesoreriaCrownComprobante $doc) => $doc->estado === TesoreriaCrownComprobanteSupport::CONFORME
        );
        $hayRendicion = $conforme->contains(
            fn (TesoreriaCrownComprobante $doc) => $doc->tipo === TesoreriaCrownComprobanteSupport::RENDICION
        );
        if (! $hayRendicion && $asignacion->cierre === null) {
            return null;
        }

        $calc = TesoreriaCrownCierreCalculo::de($asignacion);
        $detalle = self::detalleRendiciones($conforme);

        $egresos = (float) $calc['habilitado'];
        $tarea = (string) $asignacion->tipo_tarea;
        // Anita: ingresos = efectivo rendido (con medios). En gastro/hotel también
        // suma fallo de mozo + transferencia/cta cte (rescajero.fc).
        $ingresos = round(
            $detalle['efectivo']
            + $detalle['tarjeta_credito']
            - $detalle['recargo_fin']
            + $detalle['tarjeta_debito']
            + $detalle['total_qr'],
            2
        );
        if (in_array($tarea, ['G', 'H'], true)) {
            $ingresos = round($ingresos + $detalle['fallo_mozo'] + $detalle['ctacte'], 2);
        }
        $fichas = 0.0;
        foreach ($calc['componentes']['filas'] ?? [] as $linea) {
            if (($linea['clave'] ?? '') === 'fichas') {
                $fichas = (float) $linea['importe'];
            }
        }
        if (abs($fichas) < 0.001) {
            $fichas = round($detalle['rend_fichas'] - $detalle['hab_fichas'], 2);
        }
        $cashlessNeto = round($detalle['pago_cashless'] - $detalle['vta_cashless'], 2);
        // En el listado Anita la columna Online suma pago + venta (ambos entran al resultado).
        // El cierre de caja sigue usando pago − venta.
        $onlineListado = round($detalle['pago_online'] + $detalle['venta_online'], 2);
        $voucherNeto = round($detalle['pago_voucher'] - $detalle['venta_voucher'], 2);
        $apuestasNeto = round($detalle['pago_apuesta'] - $detalle['vta_apuesta'], 2);

        // Anita: resultado listado = (ingresos − egresos) + extras (cashless, fichas, online, voucher, apuestas).
        $resultado = round(
            $ingresos - $egresos + $cashlessNeto + $fichas + $onlineListado + $voucherNeto + $apuestasNeto,
            2
        );
        $diferencia = in_array($tarea, ['G', 'H'], true)
            ? (float) $calc['diferencia']
            : ($tarea === 'E' ? 0.0 : $resultado);
        $deposito = $asignacion->deposito;
        $cajaEtiqueta = trim((string) ($deposito->nombre ?? ''));
        if ($cajaEtiqueta === '') {
            $cajaEtiqueta = (string) ($deposito->codigo ?? '');
        }

        return [
            'caja' => $cajaEtiqueta,
            'caja_codigo' => (string) ($deposito->codigo ?? ''),
            'cajero' => (string) ($asignacion->cajero->nombre ?? ''),
            'legajo' => (int) ($asignacion->cajero->legajo ?? 0),
            'turno' => (string) $asignacion->turno,
            'tarea' => (string) $asignacion->tipo_tarea,
            'cierre_x' => $detalle['cierre_x'],
            'egresos' => $egresos,
            'efectivo' => $detalle['efectivo'],
            'tarjeta_credito' => $detalle['tarjeta_credito'],
            'tarjeta_debito' => $detalle['tarjeta_debito'],
            'total_qr' => $detalle['total_qr'],
            'recargo_fin' => $detalle['recargo_fin'],
            'ctacte' => $detalle['ctacte'],
            'ingresos' => $ingresos,
            'hab_fichas' => $detalle['hab_fichas'],
            'rend_fichas' => $detalle['rend_fichas'],
            'fichas_casino' => $fichas,
            'pago_cashless' => $detalle['pago_cashless'],
            'vta_cashless' => $detalle['vta_cashless'],
            'pago_online' => $detalle['pago_online'],
            'venta_online' => $detalle['venta_online'],
            'online' => $onlineListado,
            'pago_voucher' => $detalle['pago_voucher'],
            'venta_voucher' => $detalle['venta_voucher'],
            'apuestas' => $apuestasNeto,
            'vta_apuesta' => $detalle['vta_apuesta'],
            'pago_apuesta' => $detalle['pago_apuesta'],
            'resultado' => $resultado,
            'diferencia' => $diferencia,
            'fallo_mozo' => $detalle['fallo_mozo'],
            'seccion' => false,
            'total' => false,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TesoreriaCrownComprobante>  $conforme
     * @return array<string, float>
     */
    private static function detalleRendiciones($conforme): array
    {
        $out = [
            'cierre_x' => 0.0,
            'efectivo' => 0.0,
            'tarjeta_credito' => 0.0,
            'tarjeta_debito' => 0.0,
            'total_qr' => 0.0,
            'recargo_fin' => 0.0,
            'ctacte' => 0.0,
            'hab_fichas' => 0.0,
            'rend_fichas' => 0.0,
            'pago_cashless' => 0.0,
            'vta_cashless' => 0.0,
            'pago_online' => 0.0,
            'venta_online' => 0.0,
            'pago_voucher' => 0.0,
            'venta_voucher' => 0.0,
            'vta_apuesta' => 0.0,
            'pago_apuesta' => 0.0,
            'fallo_mozo' => 0.0,
        ];
        foreach ($conforme as $doc) {
            $partes = self::partesFichasYEfectivo($doc);
            if ($doc->tipo === TesoreriaCrownComprobanteSupport::HABILITACION) {
                $out['hab_fichas'] = round($out['hab_fichas'] + $partes['fichas'], 2);

                continue;
            }
            if ($doc->tipo !== TesoreriaCrownComprobanteSupport::RENDICION) {
                continue;
            }
            $out['cierre_x'] = round($out['cierre_x'] + (float) $doc->total_x, 2);
            $out['efectivo'] = round($out['efectivo'] + $partes['efectivo'], 2);
            $out['tarjeta_credito'] = round($out['tarjeta_credito'] + (float) $doc->tarjeta_credito, 2);
            $out['tarjeta_debito'] = round($out['tarjeta_debito'] + (float) $doc->tarjeta_debito, 2);
            $out['total_qr'] = round($out['total_qr'] + (float) $doc->total_qr, 2);
            $out['recargo_fin'] = round($out['recargo_fin'] + (float) $doc->recargo_fin, 2);
            $out['ctacte'] = round($out['ctacte'] + (float) $doc->ctacte_hotel + (float) $doc->transferencia_hotel, 2);
            $out['rend_fichas'] = round($out['rend_fichas'] + $partes['fichas'], 2);
            $out['pago_cashless'] = round($out['pago_cashless'] + (float) $doc->pago_cash, 2);
            $out['vta_cashless'] = round($out['vta_cashless'] + (float) $doc->venta_cash, 2);
            $out['pago_online'] = round($out['pago_online'] + (float) $doc->pago_online, 2);
            $out['venta_online'] = round($out['venta_online'] + (float) $doc->venta_online, 2);
            $out['pago_voucher'] = round($out['pago_voucher'] + (float) $doc->pago_voucher, 2);
            $out['venta_voucher'] = round($out['venta_voucher'] + (float) $doc->venta_voucher, 2);
            $out['vta_apuesta'] = round($out['vta_apuesta'] + (float) $doc->vta_apuesta, 2);
            $out['pago_apuesta'] = round($out['pago_apuesta'] + (float) $doc->pago_apuesta, 2);
            $out['fallo_mozo'] = round($out['fallo_mozo'] + (float) $doc->fallo_mozo, 2);
        }

        return $out;
    }

    /**
     * @return array{efectivo: float, fichas: float}
     */
    private static function partesFichasYEfectivo(TesoreriaCrownComprobante $doc): array
    {
        $efectivo = 0.0;
        $fichas = 0.0;
        $fecha = $doc->jornada?->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');
        foreach ($doc->lineas as $linea) {
            $valor = $linea->valor;
            $tipo = $valor?->tipoValor;
            if ($valor === null || $tipo === null) {
                continue;
            }
            $importe = (float) $linea->cantidad * (float) $valor->denominacion;
            $codigo = (string) $tipo->codigo;
            if (in_array($codigo, ['FVAL', 'FCOL'], true)) {
                $fichas += $importe;

                continue;
            }
            if ($tipo->es_moneda_referencia || $tipo->cotizacion_fija) {
                $efectivo += $importe;

                continue;
            }
            $tasa = TesoreriaCrownCierreCalculo::tasaDocumento($doc, $fecha, $codigo, (int) $tipo->id);
            if ($tasa > 0) {
                $efectivo += $importe * $tasa;
            }
        }

        return [
            'efectivo' => round($efectivo, 2),
            'fichas' => round($fichas, 2),
        ];
    }

    /**
     * Fila sintética de l-rescajero.c: al cerrar el grupo de empleados suma
     * fichmm_propina de los estados finales vigentes y la imprime con la
     * caja y el cajero de la última asignación de ese grupo.
     * Egresos = resultado = total; ingresos y diferencia quedan en cero.
     *
     * @param  list<array<string, mixed>>  $filasEmpleados
     * @return array<string, mixed>|null
     */
    private static function filaPropina(TesoreriaCrownJornada $jornada, array $filasEmpleados): ?array
    {
        if ($filasEmpleados === []) {
            return null;
        }
        $total = 0.0;
        $cierres = TesoreriaCrownMesa::query()
            ->where('jornada_id', $jornada->id)
            ->where('tipo', 'F')
            ->where('estado', TesoreriaCrownMesa::CONFIRMADO)
            ->whereNull('revierte_id')
            ->whereDoesntHave('reversa')
            ->get(['propina']);
        foreach ($cierres as $cierre) {
            $total += (float) $cierre->propina;
        }
        $total = round($total, 2);
        if (abs($total) < 0.001) {
            return null;
        }
        $ultima = $filasEmpleados[array_key_last($filasEmpleados)];
        $fila = self::filaVacia();
        $fila['caja'] = (string) $ultima['caja'];
        $fila['caja_codigo'] = (string) $ultima['caja_codigo'];
        $fila['cajero'] = (string) $ultima['cajero'];
        $fila['legajo'] = (int) $ultima['legajo'];
        $fila['tarea'] = 'E';
        $fila['egresos'] = $total;
        $fila['resultado'] = $total;
        $fila['propina'] = true;

        return $fila;
    }

    /** @return array<string, mixed> */
    private static function filaVacia(): array
    {
        $fila = [
            'caja' => '',
            'caja_codigo' => '',
            'cajero' => '',
            'legajo' => 0,
            'turno' => '',
            'tarea' => '',
            'seccion' => false,
            'total' => false,
            'propina' => false,
        ];
        foreach (array_keys(self::ceros()) as $clave) {
            $fila[$clave] = 0.0;
        }

        return $fila;
    }

    /** @return array<string, float> */
    private static function ceros(): array
    {
        return [
            'cierre_x' => 0.0,
            'egresos' => 0.0,
            'efectivo' => 0.0,
            'tarjeta_credito' => 0.0,
            'tarjeta_debito' => 0.0,
            'total_qr' => 0.0,
            'recargo_fin' => 0.0,
            'ctacte' => 0.0,
            'ingresos' => 0.0,
            'hab_fichas' => 0.0,
            'rend_fichas' => 0.0,
            'fichas_casino' => 0.0,
            'pago_cashless' => 0.0,
            'vta_cashless' => 0.0,
            'pago_online' => 0.0,
            'venta_online' => 0.0,
            'online' => 0.0,
            'pago_voucher' => 0.0,
            'venta_voucher' => 0.0,
            'apuestas' => 0.0,
            'resultado' => 0.0,
            'diferencia' => 0.0,
            'fallo_mozo' => 0.0,
        ];
    }
}
