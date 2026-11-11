<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownCotizacion;
use App\Models\Caja\TesoreriaCrownTipoValor;

/**
 * Cierre de caja con la cuenta de p-cierrecajero.c (imprime_variables) y de
 * rescajero.fc (procesa_un_cajero), que es el importe que Anita guarda.
 *
 * La habilitación es solo el efectivo (pesos y moneda extranjera). En el listado,
 * la rendición se abre como Anita: valores varios (efectivo, crédito, débito,
 * QR y cuenta corriente, con el recargo sumado) y una línea propia de recargo
 * en negativo. Las fichas van aparte.
 *
 * En venta, kiosco y cashless la diferencia es lo rendido menos lo habilitado:
 * efectivo, fichas, cashless (pagos del Z − ventas), vouchers (pagos − ventas),
 * online (neto) y apuestas (pagos − ventas). El listado impreso y el
 * importe guardado coinciden en este caso. El término de tokens queda en cero:
 * este módulo no mueve tokens. En gastronomía y hotel usa el X (ya neto de notas
 * de crédito) y el fallo de mozo, y suma la transferencia y la cuenta corriente.
 * En empleados la diferencia queda en cero.
 */
final class TesoreriaCrownCierreCalculo
{
    /**
     * @return array{
     *     habilitado: float,
     *     rendido: float,
     *     resultado: float,
     *     diferencia: float,
     *     signo: string,
     *     tolerancia: float,
     *     supera: bool,
     *     puede: bool,
     *     motivo: string,
     *     formula: string,
     *     componentes: array{formula: string, filas: list<array{concepto: string, importe: float, entra: bool}>}
     * }
     *
     * Con `$conPiezas = false` no arma el detalle por comprobante (`piezas`): los totales
     * son los mismos y el Anexo I lo pide así por cada caja del año.
     */
    public static function de(TesoreriaCrownAsignacion $asignacion, bool $conPiezas = true): array
    {
        $tolerancia = TesoreriaCrownCierreSupport::tolerancia();
        self::cargarRelaciones($asignacion);
        $docs = $asignacion->comprobantes;
        $pendiente = $docs->first(fn (TesoreriaCrownComprobante $doc) => in_array($doc->estado, [
            TesoreriaCrownComprobanteSupport::BORRADOR,
            TesoreriaCrownComprobanteSupport::PENDIENTE,
        ], true));
        $sinResolver = $docs->first(fn (TesoreriaCrownComprobante $doc) => in_array($doc->estado, TesoreriaCrownComprobanteSupport::SIN_RESOLVER, true));
        $conforme = $docs->filter(fn (TesoreriaCrownComprobante $doc) => $doc->estado === TesoreriaCrownComprobanteSupport::CONFORME);
        $hayRendicion = $conforme->contains(fn (TesoreriaCrownComprobante $doc) => $doc->tipo === TesoreriaCrownComprobanteSupport::RENDICION);

        $tarea = (string) $asignacion->tipo_tarea;
        $formula = self::formula($tarea);
        $fecha = $asignacion->jornada->fecha->format('Y-m-d');
        $salaId = (int) $asignacion->sala_id;
        $sinCotizacion = [];
        $hab = 0.0;
        $rendido = 0.0;
        $fichasHab = 0.0;
        $fichasRend = 0.0;
        $cashless = 0.0;
        $online = 0.0;
        $vouchers = 0.0;
        $apuestas = 0.0;
        $aRendir = 0.0;
        $hotel = 0.0;
        $ventaZ = 0.0;
        $recargo = 0.0;
        $piezas = [];

        foreach ($conforme as $doc) {
            $esRendicion = $doc->tipo === TesoreriaCrownComprobanteSupport::RENDICION;
            $partes = self::partes($doc, $salaId, $fecha, $sinCotizacion, $esRendicion);
            if ($esRendicion) {
                $rendido += $partes['efectivo'];
                if ($formula !== 'gastro') {
                    $rendido += (float) $doc->ctacte_hotel;
                }
                $fichasRend += $partes['fichas'];
                $cashless += (float) $doc->pago_cash - (float) $doc->venta_cash;
                $online += self::onlineNeto($doc);
                $vouchers += (float) $doc->pago_voucher - (float) $doc->venta_voucher;
                $apuestas += (float) $doc->pago_apuesta - (float) $doc->vta_apuesta;
                $recargo += (float) $doc->recargo_fin;
                $aRendir += self::aRendirDoc($doc);
                $hotel += (float) $doc->transferencia_hotel + (float) $doc->ctacte_hotel;
                $ventaZ += (float) $doc->total_z;
            } else {
                $hab += $partes['efectivo'];
                $fichasHab += $partes['fichas'];
            }
            if (! $conPiezas) {
                continue;
            }
            $piezas[] = self::pieza($doc, $partes, $esRendicion, $formula, $salaId, $fecha, $asignacion->cierre === null && $asignacion->jornada->estaAbierta());
        }

        $hab = round($hab, 2);
        $rendido = round($rendido, 2);
        $fichas = round($fichasRend - $fichasHab, 2);
        $cashless = round($cashless, 2);
        $online = round($online, 2);
        $vouchers = round($vouchers, 2);
        $apuestas = round($apuestas, 2);
        $aRendir = round($aRendir, 2);
        $hotel = round($hotel, 2);
        $ventaZ = round($ventaZ, 2);
        $recargo = round($recargo, 2);
        $valores = round($rendido + $recargo, 2);

        $resultado = match ($formula) {
            'gastro' => $aRendir,
            default => 0.0,
        };
        $diferencia = match ($formula) {
            'empleados' => 0.0,
            'gastro' => round($rendido + $hotel - ($hab + $aRendir - $fichas), 2),
            default => round($rendido + $fichas + $cashless + $vouchers + $online + $apuestas - $hab, 2),
        };
        $valoresRendidos = match ($formula) {
            'gastro' => round($rendido + $fichas + $hotel, 2),
            default => round($rendido + $fichas + $cashless + $vouchers + $online + $apuestas, 2),
        };

        $filas = self::filas($formula, $hab, $valores, $recargo, $fichas, $cashless, $online, $vouchers, $apuestas, $aRendir, $hotel, $ventaZ);
        $motivo = '';
        $puede = true;
        if ($pendiente !== null) {
            $puede = false;
            $motivo = 'Hay comprobantes en borrador o pendientes de firma.';
        } elseif ($sinResolver !== null) {
            $puede = false;
            $motivo = 'Hay comprobantes no conformes sin resolver: auditoría los corrige o los anula.';
        } elseif (! $hayRendicion) {
            $puede = false;
            $motivo = 'El cajero no tiene una rendición conforme.';
        } elseif ($sinCotizacion !== []) {
            $puede = false;
            $motivo = 'Falta cotización de '.implode(', ', array_unique($sinCotizacion)).' para expresar el cierre en pesos.';
        }

        return [
            'habilitado' => $hab,
            'rendido' => $valoresRendidos,
            'resultado' => $resultado,
            'diferencia' => $diferencia,
            'signo' => TesoreriaCrownCierreSupport::signo($diferencia),
            'tolerancia' => $tolerancia,
            'supera' => abs($diferencia) > $tolerancia + 0.001,
            'puede' => $puede,
            'motivo' => $motivo,
            'formula' => $formula,
            'componentes' => [
                'formula' => $formula,
                'filas' => $filas,
            ],
            'piezas' => $piezas,
        ];
    }

    /**
     * Neto on-line del cajero: efe_venta_online trae el neto; la carga vieja separaba pagos.
     * Anita (a-transfefe.c al aprobar, l-anexo1 lee_online) usa venta − pago.
     */
    private static function onlineNeto(TesoreriaCrownComprobante $doc): float
    {
        return (float) $doc->venta_online - (float) $doc->pago_online;
    }

    /**
     * a-transfefe.c descuenta las NC del X antes de grabar efe_total_x: rendg_tot_nc es informativo.
     */
    private static function aRendirDoc(TesoreriaCrownComprobante $doc): float
    {
        return (float) $doc->total_x - (float) $doc->fallo_mozo;
    }

    public static function formula(string $tarea): string
    {
        if (TesoreriaCrownRendicionCampos::esCajaGastroHotel($tarea)) {
            return 'gastro';
        }
        if ($tarea === 'E') {
            return 'empleados';
        }

        return 'sala';
    }

    /**
     * `loadMissing` recorre todo el árbol aunque ya esté cargado: con las relaciones
     * eager-loaded (Anexo I del año) se evita ese recorrido por caja.
     *
     * @param  list<string>  $extra
     */
    public static function cargarRelaciones(TesoreriaCrownAsignacion $asignacion, array $extra = []): void
    {
        $cargado = $asignacion->relationLoaded('jornada')
            && $asignacion->relationLoaded('cierre')
            && $asignacion->relationLoaded('comprobantes');
        foreach ($extra as $relacion) {
            $cargado = $cargado && $asignacion->relationLoaded($relacion);
        }
        if ($cargado) {
            foreach ($asignacion->comprobantes as $doc) {
                if (! $doc->relationLoaded('lineas') || ! $doc->relationLoaded('reversa')) {
                    $cargado = false;
                    break;
                }
                $linea = $doc->lineas->first();
                if ($linea !== null && (! $linea->relationLoaded('valor') || ($linea->valor !== null && ! $linea->valor->relationLoaded('tipoValor')))) {
                    $cargado = false;
                    break;
                }
            }
        }
        if ($cargado) {
            return;
        }
        $asignacion->loadMissing(array_merge(['jornada', 'cierre', 'comprobantes.lineas.valor.tipoValor', 'comprobantes.reversa'], $extra));
    }

    /**
     * Líneas del comprobante en pesos: efectivo (pesos + moneda extranjera cotizada) y fichas.
     * Se recuerda por colección de líneas cargada: si la relación se recarga, se recalcula.
     *
     * @return array{efectivo: float, fichas: float, sin_cotizacion: list<string>}
     */
    public static function lineasEnPesos(TesoreriaCrownComprobante $doc, string $fecha): array
    {
        $coleccion = $doc->lineas;
        self::$lineasEnPesos ??= new \WeakMap();
        if (isset(self::$lineasEnPesos[$coleccion][$fecha])) {
            return self::$lineasEnPesos[$coleccion][$fecha];
        }

        $pesos = 0.0;
        $extranjera = 0.0;
        $fichas = 0.0;
        $sinCotizacion = [];
        foreach ($coleccion as $linea) {
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
                $pesos += $importe;

                continue;
            }
            $tasa = self::tasaDocumento($doc, $fecha, $codigo, (int) $tipo->id);
            if ($tasa <= 0) {
                $sinCotizacion[$codigo] = $codigo;

                continue;
            }
            $extranjera += $importe * $tasa;
        }

        $resultado = [
            'efectivo' => $pesos + $extranjera,
            'fichas' => $fichas,
            'sin_cotizacion' => array_values($sinCotizacion),
        ];
        $porFecha = self::$lineasEnPesos[$coleccion] ?? [];
        $porFecha[$fecha] = $resultado;
        self::$lineasEnPesos[$coleccion] = $porFecha;

        return $resultado;
    }

    /**
     * @param  array<string, string>  $sinCotizacion
     * @return array{efectivo: float, fichas: float}
     */
    private static function partes(TesoreriaCrownComprobante $doc, int $salaId, string $fecha, array &$sinCotizacion, bool $conMedios): array
    {
        $lineas = self::lineasEnPesos($doc, $fecha);
        foreach ($lineas['sin_cotizacion'] as $codigo) {
            $sinCotizacion[$codigo] = $codigo;
        }
        $fichas = $lineas['fichas'];
        $efectivo = $lineas['efectivo'];
        if ($conMedios) {
            $efectivo += (float) $doc->tarjeta_credito - (float) $doc->recargo_fin
                + (float) $doc->tarjeta_debito + (float) $doc->total_qr;
        }

        return [
            'efectivo' => $efectivo,
            'fichas' => $fichas,
        ];
    }

    /**
     * Cotización del comprobante (efe_cotiz_* / sello al rendir) si está grabada; si no, la vigente del día.
     * Tras reabrir la caja, los conforme/pendientes con sello siguen con esa tasa.
     */
    public static function tasaDocumento(
        TesoreriaCrownComprobante $doc,
        string $fecha,
        string $codigoTipo,
        int $tipoValorId
    ): float {
        if ($codigoTipo === 'USD' && (float) ($doc->cotiz_dolar ?? 0) > 0) {
            return (float) $doc->cotiz_dolar;
        }
        if ($codigoTipo === 'EUR' && (float) ($doc->cotiz_euro ?? 0) > 0) {
            return (float) $doc->cotiz_euro;
        }

        return self::cotizacionVenta((int) $doc->sala_id, $fecha, $tipoValorId)['tasa'];
    }

    /**
     * @param  array{efectivo: float, fichas: float}  $partes
     * @return array{
     *     id: int,
     *     numero: string,
     *     tipo: string,
     *     aportes: array<string, float>,
     *     renglones: array<string, list<array{etiqueta: string, importe: float}>>
     * }
     */
    private static function pieza(
        TesoreriaCrownComprobante $doc,
        array $partes,
        bool $esRendicion,
        string $formula,
        int $salaId,
        string $fecha,
        bool $cajaAbierta,
    ): array {
        $recargo = (float) $doc->recargo_fin;
        $ctacte = (float) $doc->ctacte_hotel;
        $fichas = (float) $partes['fichas'];
        $cashless = (float) $doc->pago_cash - (float) $doc->venta_cash;
        $online = self::onlineNeto($doc);
        $vouchers = (float) $doc->pago_voucher - (float) $doc->venta_voucher;
        $apuestas = (float) $doc->pago_apuesta - (float) $doc->vta_apuesta;
        $hotel = (float) $doc->transferencia_hotel + $ctacte;
        $aRendir = self::aRendirDoc($doc);
        $valoresDoc = (float) $partes['efectivo'] + $recargo;
        if ($esRendicion && $formula !== 'gastro') {
            $valoresDoc += $ctacte;
        }

        $aportes = [];
        $renglones = [];
        $efectivoRenglones = self::renglonesValores($doc, $salaId, $fecha);
        if ($esRendicion) {
            $aportes['valores'] = round($valoresDoc, 2);
            $aportes['recargo'] = round(-1 * $recargo, 2);
            $renglones['valores'] = $efectivoRenglones;
            $renglones['valores'][] = ['etiqueta' => 'Tarjeta de crédito', 'importe' => (float) $doc->tarjeta_credito];
            $renglones['valores'][] = ['etiqueta' => 'Tarjeta de débito', 'importe' => (float) $doc->tarjeta_debito];
            $renglones['valores'][] = ['etiqueta' => 'QR', 'importe' => (float) $doc->total_qr];
            if ($formula !== 'gastro') {
                $renglones['valores'][] = ['etiqueta' => 'Cuenta corriente', 'importe' => $ctacte];
            }
            $renglones['recargo'] = [
                ['etiqueta' => 'Tarjeta de crédito', 'importe' => (float) $doc->tarjeta_credito],
                ['etiqueta' => 'Recargo', 'importe' => round(-1 * $recargo, 2)],
            ];
            if (abs($fichas) > 0.001) {
                $aportes['fichas'] = round($fichas, 2);
                $renglones['fichas'] = self::renglonesFichas($doc);
            }
            if ($formula === 'sala') {
                $aportes['cashless'] = round($cashless, 2);
                $aportes['online'] = round($online, 2);
                $aportes['voucher'] = round($vouchers, 2);
                $aportes['apuestas'] = round($apuestas, 2);
                $renglones['cashless'] = [
                    ['etiqueta' => 'Pagos Z', 'importe' => (float) $doc->pago_cash],
                    ['etiqueta' => 'Ventas cashless', 'importe' => (float) $doc->venta_cash],
                    ['etiqueta' => 'Pagos parcial', 'importe' => (float) $doc->pago_parcial],
                ];
                $renglones['online'] = [
                    ['etiqueta' => 'Pagos casino on-line', 'importe' => (float) $doc->pago_online],
                    ['etiqueta' => 'Neto casino on-line', 'importe' => (float) $doc->venta_online],
                ];
                $renglones['voucher'] = [
                    ['etiqueta' => 'Pago vouchers', 'importe' => (float) $doc->pago_voucher],
                    ['etiqueta' => 'Venta vouchers', 'importe' => (float) $doc->venta_voucher],
                ];
                $renglones['apuestas'] = [
                    ['etiqueta' => 'Apuestas deportivas', 'importe' => (float) $doc->vta_apuesta],
                ];
            }
            if ($formula === 'gastro') {
                $aportes['arendir'] = round($aRendir, 2);
                $aportes['hotel'] = round($hotel, 2);
                $aportes['ventaz'] = round((float) $doc->total_z, 2);
                $renglones['arendir'] = [
                    ['etiqueta' => 'Ventas / X', 'importe' => (float) $doc->total_x],
                    ['etiqueta' => 'Notas de crédito (ya descontadas del X)', 'importe' => (float) $doc->total_nc],
                    ['etiqueta' => 'Fallo de mozo', 'importe' => (float) $doc->fallo_mozo],
                ];
                $renglones['hotel'] = [
                    ['etiqueta' => 'Transferencia', 'importe' => (float) $doc->transferencia_hotel],
                    ['etiqueta' => 'Cuenta corriente', 'importe' => $ctacte],
                ];
                $renglones['ventaz'] = [
                    ['etiqueta' => 'Total Z', 'importe' => (float) $doc->total_z],
                ];
            }
            $columna = $valoresDoc - $recargo + $fichas;
            if ($formula === 'sala') {
                $columna += $cashless + $online + $vouchers + $apuestas;
            }
            if ($formula === 'gastro') {
                $columna += $hotel;
            }
            $aportes['valores_rendidos'] = round($columna, 2);
            $renglones['valores_rendidos'] = array_values(array_filter(
                array_merge(
                    $renglones['valores'] ?? [],
                    [['etiqueta' => 'Recargo', 'importe' => round(-1 * $recargo, 2)]],
                    $renglones['fichas'] ?? [],
                    $formula === 'sala' ? array_merge($renglones['cashless'] ?? [], $renglones['online'] ?? [], $renglones['voucher'] ?? [], $renglones['apuestas'] ?? []) : [],
                    $formula === 'gastro' ? ($renglones['hotel'] ?? []) : [],
                ),
                fn (array $fila) => abs($fila['importe']) > 0.001,
            ));
        } else {
            $aportes['habilitacion'] = round((float) $partes['efectivo'], 2);
            $aportes['efectivo_habilitado'] = round((float) $partes['efectivo'], 2);
            $renglones['habilitacion'] = $efectivoRenglones;
            $renglones['efectivo_habilitado'] = $efectivoRenglones;
            if (abs($fichas) > 0.001) {
                $aportes['fichas'] = round(-1 * $fichas, 2);
                $aportes['valores_rendidos'] = round(-1 * $fichas, 2);
                $renglones['fichas'] = self::renglonesFichas($doc);
                $renglones['valores_rendidos'] = $renglones['fichas'];
            }
        }

        $yaRevertido = $doc->reversa !== null;

        return [
            'id' => (int) $doc->id,
            'numero' => TesoreriaCrownNumeradorSupport::visible((int) $doc->numero),
            'tipo' => TesoreriaCrownComprobanteSupport::tipo((string) $doc->tipo),
            'estado' => TesoreriaCrownComprobanteSupport::estado((string) $doc->estado),
            'puede_revertir' => $cajaAbierta
                && $doc->estado === TesoreriaCrownComprobanteSupport::CONFORME
                && $doc->revierte_id === null
                && ! $yaRevertido,
            'puede_modificar' => $cajaAbierta && $doc->estado === TesoreriaCrownComprobanteSupport::BORRADOR,
            'puede_volver' => $cajaAbierta && $doc->estado === TesoreriaCrownComprobanteSupport::PENDIENTE && $doc->revierte_id === null,
            'reversa_id' => $yaRevertido ? (int) $doc->reversa->id : null,
            'reversa_numero' => $yaRevertido ? TesoreriaCrownNumeradorSupport::visible((int) $doc->reversa->numero) : '',
            'aportes' => $aportes,
            'renglones' => $renglones,
        ];
    }

    /**
     * @return list<array{etiqueta: string, importe: float}>
     */
    private static function renglonesValores(TesoreriaCrownComprobante $doc, int $salaId, string $fecha): array
    {
        $filas = [];
        foreach ($doc->lineas as $linea) {
            $valor = $linea->valor;
            $tipo = $valor?->tipoValor;
            if ($valor === null || $tipo === null) {
                continue;
            }
            $codigo = (string) $tipo->codigo;
            if (in_array($codigo, ['FVAL', 'FCOL'], true)) {
                continue;
            }
            $cantidad = (float) $linea->cantidad;
            $denominacion = (float) $valor->denominacion;
            $origen = $cantidad * $denominacion;
            if (abs($origen) < 0.0001) {
                continue;
            }
            $cantidadTxt = rtrim(rtrim(number_format($cantidad, 2, ',', '.'), '0'), ',');
            $denomTxt = number_format($denominacion, 2, ',', '.');
            if ($tipo->es_moneda_referencia || $tipo->cotizacion_fija) {
                $pesos = $origen;
                $etiqueta = $cantidadTxt.' × '.$denomTxt.' '.$codigo;
            } else {
                $tasa = self::tasaDocumento($doc, $fecha, $codigo, (int) $tipo->id);
                $pesos = $tasa > 0 ? $origen * $tasa : 0.0;
                $etiqueta = $cantidadTxt.' × '.$denomTxt.' '.$codigo.' × '.number_format($tasa, 2, ',', '.');
            }
            if (abs($pesos) > 0.001) {
                $filas[] = ['etiqueta' => $etiqueta, 'importe' => round($pesos, 2)];
            }
        }

        return $filas;
    }

    /**
     * @return list<array{etiqueta: string, importe: float}>
     */
    private static function renglonesFichas(TesoreriaCrownComprobante $doc): array
    {
        $filas = [];
        foreach ($doc->lineas as $linea) {
            $valor = $linea->valor;
            $tipo = $valor?->tipoValor;
            if ($valor === null || $tipo === null) {
                continue;
            }
            if (! in_array((string) $tipo->codigo, ['FVAL', 'FCOL'], true)) {
                continue;
            }
            $cantidad = (float) $linea->cantidad;
            $denominacion = (float) $valor->denominacion;
            $importe = $cantidad * $denominacion;
            if (abs($importe) < 0.001) {
                continue;
            }
            $cantidadTxt = rtrim(rtrim(number_format($cantidad, 2, ',', '.'), '0'), ',');
            $filas[] = [
                'etiqueta' => $cantidadTxt.' × '.number_format($denominacion, 2, ',', '.').' '.($tipo->codigo ?? ''),
                'importe' => round($importe, 2),
            ];
        }

        return $filas;
    }

    /**
     * @return list<array{concepto: string, importe: float, entra: bool, clave: string, columna: string}>
     */
    private static function filas(
        string $formula,
        float $hab,
        float $valores,
        float $recargo,
        float $fichas,
        float $cashless,
        float $online,
        float $vouchers,
        float $apuestas,
        float $aRendir,
        float $hotel,
        float $ventaZ,
    ): array {
        $sala = $formula === 'sala';
        $filas = [
            ['clave' => 'habilitacion', 'columna' => 'habilitacion', 'concepto' => 'Habilitación', 'importe' => $hab, 'entra' => true],
            ['clave' => 'valores', 'columna' => 'rendicion', 'concepto' => 'Rendición valores varios', 'importe' => $valores, 'entra' => true],
            ['clave' => 'recargo', 'columna' => 'rendicion', 'concepto' => 'Rendición recargo', 'importe' => round(-1 * $recargo, 2), 'entra' => true],
        ];
        if (abs($fichas) > 0.001) {
            $filas[] = ['clave' => 'fichas', 'columna' => 'rendicion', 'concepto' => 'Rendición fichas', 'importe' => $fichas, 'entra' => true];
        }
        if ($sala && abs($cashless) > 0.001) {
            $filas[] = ['clave' => 'cashless', 'columna' => 'rendicion', 'concepto' => 'Rendición cashless', 'importe' => $cashless, 'entra' => true];
        }
        if ($sala && abs($vouchers) > 0.001) {
            $filas[] = ['clave' => 'voucher', 'columna' => 'rendicion', 'concepto' => 'Rendición voucher', 'importe' => $vouchers, 'entra' => true];
        }
        if ($sala && abs($online) > 0.001) {
            $filas[] = ['clave' => 'online', 'columna' => 'rendicion', 'concepto' => 'Rendición on-line', 'importe' => $online, 'entra' => true];
        }
        if ($sala && abs($apuestas) > 0.001) {
            $filas[] = ['clave' => 'apuestas', 'columna' => 'rendicion', 'concepto' => 'Apuestas deportivas', 'importe' => $apuestas, 'entra' => true];
        }
        if ($formula === 'gastro') {
            $filas[] = ['clave' => 'arendir', 'columna' => '', 'concepto' => 'A rendir (X neto de notas de crédito − fallo de mozo)', 'importe' => $aRendir, 'entra' => true];
            $filas[] = ['clave' => 'hotel', 'columna' => 'rendicion', 'concepto' => 'Transferencia y cuenta corriente', 'importe' => $hotel, 'entra' => true];
            $filas[] = ['clave' => 'ventaz', 'columna' => '', 'concepto' => 'Total Z', 'importe' => $ventaZ, 'entra' => false];
        }
        if ($formula === 'empleados') {
            $filas[] = ['clave' => 'empleados', 'columna' => '', 'concepto' => 'En caja de empleados la diferencia queda en cero', 'importe' => 0.0, 'entra' => false];
        }

        return $filas;
    }

    /**
     * Cotización de venta vigente a la fecha de la jornada: la última mayor a cero
     * con fecha menor o igual. Una fila en cero no es cambio.
     *
     * @return array{tasa: float, fecha: ?string}
     */
    /** @var array<string, array{tasa: float, fecha: ?string}> */
    private static array $cotizacionCache = [];

    /** @var \WeakMap<\Illuminate\Support\Collection, array<string, array{efectivo: float, fichas: float, sin_cotizacion: list<string>}>>|null */
    private static ?\WeakMap $lineasEnPesos = null;

    public static function cotizacionVenta(int $salaId, string $fecha, int $tipoValorId): array
    {
        $clave = $salaId.'|'.$fecha.'|'.$tipoValorId;
        if (isset(self::$cotizacionCache[$clave])) {
            return self::$cotizacionCache[$clave];
        }

        $fila = TesoreriaCrownCotizacion::query()
            ->where('sala_id', $salaId)
            ->where('tipo_valor_id', $tipoValorId)
            ->whereDate('jornada', '<=', $fecha)
            ->where('venta', '>', 0)
            ->orderByDesc('jornada')
            ->first(['venta', 'jornada']);

        if ($fila === null) {
            return self::$cotizacionCache[$clave] = ['tasa' => 0.0, 'fecha' => null];
        }

        $fechaCot = $fila->jornada instanceof \DateTimeInterface
            ? $fila->jornada->format('Y-m-d')
            : substr((string) $fila->jornada, 0, 10);

        return self::$cotizacionCache[$clave] = ['tasa' => (float) $fila->venta, 'fecha' => $fechaCot];
    }

    /**
     * Efectivo de la rendición expresado en pesos. Las fichas no entran:
     * pesos al nominal, y cada moneda extranjera por la cotización del comprobante
     * si está sellada, o la de venta vigente de la jornada.
     *
     * @param  list<array{codigo: string, titulo: string, filas: \Illuminate\Support\Collection}>  $grupos
     * @param  iterable<int|string, mixed>  $cantidades
     * @return array{
     *     total: float,
     *     completo: bool,
     *     monedas: list<array{
     *         codigo: string,
     *         titulo: string,
     *         origen: float,
     *         tasa: float,
     *         fecha: ?string,
     *         pesos: float,
     *         extranjera: bool,
     *         falta: bool
     *     }>
     * }
     */
    public static function efectivoEnPesos(
        int $salaId,
        string $fecha,
        array $grupos,
        iterable $cantidades,
        ?TesoreriaCrownComprobante $comprobante = null
    ): array {
        $map = $cantidades instanceof \Illuminate\Support\Collection ? $cantidades->all() : (array) $cantidades;
        $monedas = [];
        $total = 0.0;
        $completo = true;

        foreach ($grupos as $grupo) {
            $codigo = (string) ($grupo['codigo'] ?? '');
            if (in_array($codigo, ['FVAL', 'FCOL'], true)) {
                continue;
            }
            $filas = $grupo['filas'] ?? null;
            $tipo = $filas?->first()?->tipoValor;
            if ($filas === null || $tipo === null) {
                continue;
            }
            $origen = 0.0;
            foreach ($filas as $fila) {
                $origen += (float) $fila->denominacion * (float) ($map[$fila->id] ?? 0);
            }
            $origen = round($origen, 2);
            $extranjera = ! ($tipo->es_moneda_referencia || $tipo->cotizacion_fija);
            if ($extranjera) {
                if ($comprobante !== null) {
                    $tasa = self::tasaDocumento($comprobante, $fecha, $codigo, (int) $tipo->id);
                    $delDoc = ($codigo === 'USD' && (float) ($comprobante->cotiz_dolar ?? 0) > 0)
                        || ($codigo === 'EUR' && (float) ($comprobante->cotiz_euro ?? 0) > 0);
                    $fechaCot = $delDoc ? $fecha : self::cotizacionVenta($salaId, $fecha, (int) $tipo->id)['fecha'];
                } else {
                    $cot = self::cotizacionVenta($salaId, $fecha, (int) $tipo->id);
                    $tasa = $cot['tasa'];
                    $fechaCot = $cot['fecha'];
                }
            } else {
                $tasa = 1.0;
                $fechaCot = $fecha;
            }
            $falta = $extranjera && $tasa <= 0;
            if ($falta && abs($origen) > 0.0001) {
                $completo = false;
            }
            $pesos = $falta ? 0.0 : round($origen * $tasa, 2);
            if (! $falta) {
                $total += $pesos;
            }
            $monedas[] = [
                'codigo' => $codigo,
                'titulo' => (string) ($grupo['titulo'] ?? $codigo),
                'origen' => $origen,
                'tasa' => $tasa,
                'fecha' => $fechaCot,
                'pesos' => $pesos,
                'extranjera' => $extranjera,
                'falta' => $falta,
            ];
        }

        return [
            'total' => round($total, 2),
            'completo' => $completo,
            'monedas' => $monedas,
        ];
    }

    /**
     * Total final $ de a-transfefe.c, calcula(): el efectivo en pesos
     * más la tarjeta de crédito y la de débito, menos el recargo, más el QR.
     * Las fichas no entran.
     */
    public static function totalFinal(
        float $efectivoEnPesos,
        float $tarjetaCredito,
        float $tarjetaDebito,
        float $recargo,
        float $qr,
    ): float {
        return round($efectivoEnPesos + $tarjetaCredito + $tarjetaDebito - $recargo + $qr, 2);
    }

    /**
     * Recargo de a-transfefe.c, calcula_recargo(). En sala lo pisa al cargar
     * el crédito: hasta el 7/11/2024 es el crédito menos crédito/1,10; desde
     * el 8/11/2024, crédito menos crédito/1,05. Gastronomía y hotel no lo calculan.
     */
    public static function recargoFinanciacion(float $credito, string $fecha): float
    {
        if (abs($credito) < 0.00001) {
            return 0.0;
        }

        return round($credito - ($credito / self::divisorRecargo($fecha)), 2);
    }

    public static function divisorRecargo(string $fecha): float
    {
        return $fecha < '2024-11-08' ? 1.10 : 1.05;
    }

    /**
     * Dólar y euro de la sala, con la venta vigente a la fecha de la jornada.
     *
     * @return list<array{codigo: string, tasa: float, fecha: ?string, exacta: bool, del_comprobante: bool}>
     */
    public static function cambiosDelDia(int $salaId, string $fecha): array
    {
        $tipos = TesoreriaCrownTipoValor::query()
            ->where('sala_id', $salaId)
            ->whereIn('codigo', ['USD', 'EUR'])
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
        $cambios = [];
        foreach ($tipos as $tipo) {
            $cot = self::cotizacionVenta($salaId, $fecha, (int) $tipo->id);
            $cambios[] = [
                'codigo' => (string) $tipo->codigo,
                'tasa' => $cot['tasa'],
                'fecha' => $cot['fecha'],
                'exacta' => $cot['fecha'] === $fecha,
                'del_comprobante' => false,
            ];
        }

        return $cambios;
    }

    /**
     * Cambios a mostrar en el comprobante: si ya tiene cotización sellada, esa;
     * si no, la vigente del día (borrador / pendiente de firma).
     *
     * @return list<array{codigo: string, tasa: float, fecha: ?string, exacta: bool, del_comprobante: bool}>
     */
    public static function cambiosParaComprobante(TesoreriaCrownComprobante $comprobante): array
    {
        $comprobante->loadMissing('jornada');
        $fecha = $comprobante->jornada?->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');
        $cambios = self::cambiosDelDia((int) $comprobante->sala_id, $fecha);
        foreach ($cambios as &$cambio) {
            $codigo = (string) $cambio['codigo'];
            $tasaDoc = 0.0;
            if ($codigo === 'USD') {
                $tasaDoc = (float) ($comprobante->cotiz_dolar ?? 0);
            } elseif ($codigo === 'EUR') {
                $tasaDoc = (float) ($comprobante->cotiz_euro ?? 0);
            }
            if ($tasaDoc > 0) {
                $cambio['tasa'] = $tasaDoc;
                $cambio['fecha'] = $fecha;
                $cambio['exacta'] = true;
                $cambio['del_comprobante'] = true;
            }
        }
        unset($cambio);

        return $cambios;
    }

    private static function tasa(int $salaId, string $fecha, int $tipoValorId): float
    {
        return self::cotizacionVenta($salaId, $fecha, $tipoValorId)['tasa'];
    }
}
