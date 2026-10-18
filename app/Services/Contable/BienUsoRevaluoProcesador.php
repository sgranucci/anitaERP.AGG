<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\Models\Contable\BienUso;
use App\Models\Contable\BienUsoAmortizacion;
use App\Models\Contable\BienUsoIndice;
use App\Models\Contable\BienUsoIndiceCoeficiente;
use App\Models\Contable\BienUsoRevaluo;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Contable\BienUsoAmortizacionUnidad;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Port fiel de un_mov() de Anita l-biemae.c (~línea 1298+).
 *
 * Calcula vida útil transcurrida, valor residual, coeficiente de ajuste,
 * valor actual, amortizaciones del ejercicio; persiste bien_uso_revaluo y
 * bien_uso_amortizacion del año de revalúo; devuelve columnas del informe.
 *
 * Módulo estándar ERP (todos los clientes). Sin gates de entorno.
 */
final class BienUsoRevaluoProcesador
{
    /**
     * @return array<string, mixed>|null null si el bien se filtra (baja / sin datos)
     */
    public function procesar(
        BienUso $bien,
        CarbonInterface $fechaRevaluo,
        ?int $indiceId,
        string $periodoBase,
        ?int $monedaExpresionId,
        bool $flMonOrigen,
        bool $filtrarBajas = false,
        bool $persistir = true,
    ): ?array {
        // l-biemae.c:1317 — Filtra bienes con valor_baja > 0
        if ($filtrarBajas && (float) ($bien->valor_baja ?? 0) > 0) {
            return null;
        }

        $anioActual = (int) $fechaRevaluo->format('Y');
        $mesActual = (int) $fechaRevaluo->format('m');
        $fechaRevaluoYmd = $fechaRevaluo->format('Y-m-d');

        $indice = $indiceId ? BienUsoIndice::query()->find($indiceId) : null;
        $tipoValor = $indice?->tipo_valor ?? 'C';
        $coefActualBase = $indice
            ? $this->coeficiente($indice->id, $periodoBase)
            : 1.0;

        // l-biemae.c:1360-1367 — cotización vigente en fecha de compra
        $fechaCompra = $bien->fecha_compra ?? $bien->fecha_alta;
        $fechaCompraYmd = $fechaCompra
            ? Carbon::parse($fechaCompra)->format('Y-m-d')
            : $fechaRevaluoYmd;
        $monedaBienId = (int) ($bien->moneda_id ?? CotizacionVigenteSupport::MONEDA_LOCAL_ID);
        $cotizacionCompra = CotizacionVigenteSupport::ventaValorOUno($fechaCompraYmd, $monedaBienId);

        // Coef. de expresión monetaria (calcula_coef Anita). Si flMonOrigen o misma moneda → 1.
        $monedaDestino = (int) ($monedaExpresionId ?? CotizacionVigenteSupport::MONEDA_LOCAL_ID);
        $coefMoneda = $this->coeficienteMoneda(
            $monedaBienId,
            $monedaDestino,
            $cotizacionCompra,
            $flMonOrigen
        );

        $fechaAlta = $bien->fecha_alta ? Carbon::parse($bien->fecha_alta) : null;
        $anioAlta = $fechaAlta ? (int) $fechaAlta->format('Y') : 0;
        $mesAlta = $fechaAlta ? (int) $fechaAlta->format('m') : 0;

        $anioCompra = $fechaCompra ? (int) Carbon::parse($fechaCompra)->format('Y') : $anioAlta;
        $mesCompra = $fechaCompra ? (int) Carbon::parse($fechaCompra)->format('m') : $mesAlta;

        $flAmort = (string) ($bien->fl_amort_unid ?? BienUsoAmortizacionUnidad::MES);

        // l-biemae.c:1387-1391 — REAL_MENSUAL usa período de alta como compra
        if ($flAmort === BienUsoAmortizacionUnidad::REAL_MENSUAL) {
            $mesCompra = $mesAlta;
            $anioCompra = $anioAlta;
        }

        // l-biemae.c:1393-1394 — baja futura respecto de fecha revalúo se ignora
        $fechaBaja = $bien->fecha_baja ? Carbon::parse($bien->fecha_baja) : null;
        if ($fechaBaja && $fechaBaja->gt($fechaRevaluo)) {
            $fechaBaja = null;
        }
        $anioBaja = $fechaBaja ? (int) $fechaBaja->format('Y') : 0;
        $mesBaja = $fechaBaja ? (int) $fechaBaja->format('m') : 0;

        $fechaFac = $bien->fecha_factura ? Carbon::parse($bien->fecha_factura) : $fechaCompra;
        $periodoFac = $fechaFac ? $fechaFac->format('Ym') : $periodoBase;

        // l-biemae.c:1406-1431 — coeficiente de ajuste según tipo índice
        $coefAct = 1.0;
        if ($indiceId) {
            $coefPeriodoFac = $this->coeficiente((int) $indiceId, $periodoFac);
            $coefAct = match ($tipoValor) {
                BienUsoIndice::TIPO_INDICES => $coefPeriodoFac > 0
                    ? $coefActualBase / $coefPeriodoFac
                    : 1.0,
                BienUsoIndice::TIPO_COEFICIENTE,
                BienUsoIndice::TIPO_DIRECTO => $coefPeriodoFac,
                default => 1.0,
            };
        }

        $vidaUtil = (int) ($bien->vida_util ?? 0);
        $valorOrigen = (float) ($bien->valor_origen ?? 0);
        $montoFac = (float) ($bien->monto_factura ?? 0);
        if ($montoFac <= 0 && $valorOrigen > 0) {
            $montoFac = $valorOrigen;
        }

        // l-biemae.c:1433-1474 — vida útil transcurrida
        $flAmortizado = false;
        $vidaUtilTrans = $this->calcularVidaUtilTranscurrida(
            $flAmort,
            $anioCompra,
            $mesCompra,
            $anioActual,
            $mesActual,
            $vidaUtil,
            $flAmortizado
        );

        // l-biemae.c:1476-1496 — valor residual
        if ($flAmort === BienUsoAmortizacionUnidad::MES) {
            $valorResidual = $valorOrigen;
        } elseif ($vidaUtil - $vidaUtilTrans < 0) {
            $valorResidual = 1.0;
            $coefAct = 1.0;
        } elseif ($vidaUtil > 0) {
            // Vida transcurrida es al inicio del ejercicio → resta uno
            $valorResidual = $valorOrigen * ($vidaUtil - ($vidaUtilTrans - 1)) / $vidaUtil;
        } else {
            $valorResidual = 0.0;
        }

        // l-biemae.c:1498-1499
        $valorActual = $valorResidual * $coefAct;

        // l-biemae.c:1510-1536 — revalúo ejercicio anterior
        $revAnterior = BienUsoRevaluo::query()
            ->where('bien_uso_id', $bien->id)
            ->where('periodo_anio', $anioActual - 1)
            ->first();
        $amorAnterior = null;
        if ($flAmort !== BienUsoAmortizacionUnidad::SIN_AMORT) {
            $amorAnterior = BienUsoAmortizacion::query()
                ->where('bien_uso_id', $bien->id)
                ->where('periodo_anio', $anioActual - 1)
                ->first();
        }

        if ($revAnterior) {
            if ($vidaUtilTrans === 1) {
                $valorComienzo = $valorResidual;
            } else {
                $valorComienzo = (float) $revAnterior->valor_actual
                    - (float) ($amorAnterior?->monto_ejercicio ?? 0);
            }
            $incrRevaluo = $valorActual - $valorComienzo;
            $incrRevaluoAnterior = $incrRevaluo + (float) $revAnterior->revaluo_acumulado;
        } else {
            $valorComienzo = $valorResidual;
            $incrRevaluo = $valorActual - $valorComienzo;
            $incrRevaluoAnterior = $incrRevaluo;
        }

        if ($persistir) {
            // l-biemae.c:1538-1540 / graba_revaluo
            BienUsoRevaluo::query()->updateOrCreate(
                [
                    'bien_uso_id' => $bien->id,
                    'periodo_anio' => $anioActual,
                ],
                [
                    'empresa_id' => (int) $bien->empresa_id,
                    'valor_actual' => round($valorActual, 2),
                    'valor_residual' => round($valorResidual, 2),
                    'valor_comienzo' => round($valorComienzo, 2),
                    'revaluo_acumulado' => round($incrRevaluoAnterior, 2),
                ]
            );
        }

        // l-biemae.c:1561-1654 — vida ej. anterior / actual
        [$vidaEjAnterior, $vidaEjActual] = $this->calcularVidasEjercicio(
            $flAmort,
            $flAmortizado,
            $vidaUtilTrans,
            $vidaUtil,
            $anioActual,
            $mesActual,
            $anioAlta,
            $mesAlta,
            $anioBaja
        );

        $vidaResidualContableUnidades = 0;
        if (! ($vidaEjAnterior === 0 && $vidaEjActual === 0)) {
            $vidaResidualContableUnidades = max(0, $vidaUtil - $vidaUtilTrans);
        }

        $alta = 0.0;
        $baja = 0.0;
        if ($anioBaja > 0 && $anioBaja <= $anioActual) {
            $baja = $montoFac;
        }

        // l-biemae.c:1678-1680 — segundo filtro bajas
        if ($filtrarBajas && $baja > 0) {
            return null;
        }

        $valorOrigenCierre = $montoFac + $alta - $baja;

        // l-biemae.c:1688-1699 — amort. acum. inicio
        $amortAcumInicio = 0.0;
        if ($flAmort !== BienUsoAmortizacionUnidad::SIN_AMORT && $vidaUtil !== 0) {
            if ($flAmort === BienUsoAmortizacionUnidad::MES && $anioCompra === $anioActual - 1) {
                $amortAcumInicio = $montoFac / $vidaUtil * 12;
            } else {
                $amortAcumInicio = $montoFac / $vidaUtil * $vidaEjAnterior;
            }
        }

        $amortAcumBaja = 0.0;
        if ($flAmort !== BienUsoAmortizacionUnidad::SIN_AMORT && $vidaUtil !== 0) {
            $amortAcumBaja = $baja / $vidaUtil * $vidaEjAnterior;
        }

        // l-biemae.c:1710-1745 — amortización del ejercicio
        $amortEjercicio = 0.0;
        if ($vidaUtil >= $vidaUtilTrans && $flAmort !== BienUsoAmortizacionUnidad::SIN_AMORT) {
            $base = $montoFac + $alta - $baja;
            $amortEjercicio = match ($flAmort) {
                BienUsoAmortizacionUnidad::REAL_MENSUAL => $base / $vidaUtil * $vidaEjActual,
                BienUsoAmortizacionUnidad::MES => $base / $vidaUtil * $mesActual,
                default => $base / $vidaUtil * $vidaEjActual,
            };
        } elseif ($vidaUtil < $vidaUtilTrans && $flAmort === BienUsoAmortizacionUnidad::REAL_MENSUAL) {
            $amortEjercicio = ($montoFac + $alta - $baja) / $vidaUtil * $vidaEjActual;
        }

        $fechaBajaReempl = $bien->fecha_baja_reemplazo
            ? Carbon::parse($bien->fecha_baja_reemplazo)
            : null;
        if ($fechaBajaReempl && $fechaBajaReempl->lt($fechaRevaluo)) {
            $amortEjercicio = 0.0;
        }

        // l-biemae.c:1749-1781 — amortización mensual
        $mesCompraAmort = $anioCompra !== $anioActual ? 1 : $mesCompra;
        $amortMensual = 0.0;
        if ($vidaUtil >= $vidaUtilTrans
            && $flAmort !== BienUsoAmortizacionUnidad::SIN_AMORT
            && $baja == 0.0
        ) {
            $amortMensual = match ($flAmort) {
                BienUsoAmortizacionUnidad::REAL_MENSUAL => $flAmortizado
                    ? 0.0
                    : $montoFac / $vidaUtil,
                BienUsoAmortizacionUnidad::ANIO => $montoFac / $vidaUtil,
                default => ($montoFac / $vidaUtil) * (12.0 / (12.0 - (float) $mesCompraAmort + 1)),
            };
        }
        if ($fechaBajaReempl && $fechaBajaReempl->lt($fechaRevaluo)) {
            $amortMensual = 0.0;
        }

        $amortCierre = $amortAcumInicio - $amortAcumBaja + $amortEjercicio;

        if ($persistir && $flAmort !== BienUsoAmortizacionUnidad::SIN_AMORT) {
            // l-biemae.c:1788-1791 / graba_amortizacion
            // Nota Anita: acumula monto_anterior + baja + ejercicio (signo baja en acum)
            BienUsoAmortizacion::query()->updateOrCreate(
                [
                    'bien_uso_id' => $bien->id,
                    'periodo_anio' => $anioActual,
                ],
                [
                    'empresa_id' => (int) $bien->empresa_id,
                    'monto_ejercicio' => round($amortEjercicio, 2),
                    'monto_anterior' => round($amortAcumInicio, 2),
                    'monto_acumulado' => round($amortAcumInicio + $amortAcumBaja + $amortEjercicio, 2),
                    'vida_util_transcurrida' => $vidaUtilTrans,
                    'monto_mensual' => round($amortMensual, 2),
                ]
            );
        }

        // l-biemae.c:1793-1797
        $valorResidualContable = $montoFac - $baja - $amortCierre;
        if ($fechaBajaReempl && $fechaBajaReempl->lt($fechaRevaluo)) {
            $valorResidualContable = 0.0;
        }

        $indicePeriodoFac = $indiceId ? $this->coeficiente((int) $indiceId, $periodoFac) : 1.0;

        // l-biemae.c:1807-1831 — rdn (redondeo a 2)
        $amortAcumInicioAct = $amortAcumInicio * $coefAct >= 0
            ? $this->rdn($amortAcumInicio * $coefAct)
            : 0.0;
        $amortEjercicioAct = $amortEjercicio * $coefAct >= 0
            ? $this->rdn($amortEjercicio * $coefAct)
            : 0.0;
        $valorOrigenAct = $valorOrigenCierre * $coefAct >= 0
            ? $this->rdn($valorOrigenCierre * $coefAct)
            : 0.0;
        $valorResidualContableAct = $valorResidualContable * $coefAct >= 0
            ? $this->rdn($valorResidualContable * $coefAct)
            : 0.0;

        $proporcionDias = ($valorResidualContable * $coefAct) - $valorResidualContableAct;
        $proporcionDiasTxt = $proporcionDias < 1
            ? round($proporcionDias, 2)
            : 'ERROR';

        $aplicarCoefMoneda = static fn (float $v): float => round($v * $coefMoneda, 2);

        return [
            'bien_uso_id' => $bien->id,
            'empresa_id' => (int) $bien->empresa_id,
            'codigo_bien' => $bien->codigo_bien,
            'descripcion' => $bien->descripcion ?? $bien->hostname,
            'marca' => $bien->marca,
            'numero_serie' => $bien->numero_serie,
            'proveedor_id' => $bien->proveedor_id,
            'proveedor_nombre' => $bien->proveedor?->nombre ?? '',
            'tipo_factura' => $bien->tipo_factura,
            'letra_factura' => $bien->letra_factura,
            'sucursal_factura' => $bien->sucursal_factura,
            'nro_factura' => $bien->nro_factura,
            'factura_nro' => sprintf(
                '%s %s%04d-%08d',
                (string) ($bien->tipo_factura ?? ''),
                (string) ($bien->letra_factura ?? ''),
                (int) ($bien->sucursal_factura ?? 0),
                (int) ($bien->nro_factura ?? 0)
            ),
            'monto_factura' => $aplicarCoefMoneda($montoFac),
            'fecha_factura' => $fechaFac?->format('Y-m-d'),
            'vida_util' => $vidaUtil,
            'periodo_alta' => $fechaAlta?->format('Y-m'),
            'anio_alta' => $anioAlta,
            'mes_alta' => $mesAlta,
            'centrocosto_id' => $bien->centrocosto_id,
            'bien_uso_rubro_id' => $bien->bien_uso_rubro_id,
            'bien_uso_subrubro_id' => $bien->bien_uso_subrubro_id,
            'cuentacontable_origen_id' => $bien->cuentacontable_origen_id,
            'cuentacontable_amort_id' => $bien->cuentacontable_amort_id,
            'fl_amort_unid' => $flAmort,
            'vida_util_transcurrida' => $vidaUtilTrans,
            'vida_util_ej_anterior' => $vidaEjAnterior,
            'vida_util_ej_actual' => $vidaEjActual,
            'vida_residual_unidades' => $vidaResidualContableUnidades,
            'valor_origen_inicio' => $aplicarCoefMoneda($montoFac),
            'aumentos' => $aplicarCoefMoneda($alta),
            'bajas' => $aplicarCoefMoneda($baja),
            'valor_origen_cierre' => $aplicarCoefMoneda($valorOrigenCierre),
            'amort_acum_inicio' => $aplicarCoefMoneda($amortAcumInicio),
            'amort_acum_baja' => $aplicarCoefMoneda($amortAcumBaja),
            'amort_ejercicio' => $aplicarCoefMoneda($amortEjercicio),
            'amort_mensual' => $aplicarCoefMoneda($amortMensual),
            'amort_cierre' => $aplicarCoefMoneda($amortCierre),
            'valor_residual_contable' => $aplicarCoefMoneda($valorResidualContable),
            'indice_periodo_fac' => $tipoValor !== BienUsoIndice::TIPO_DIRECTO
                ? round($indicePeriodoFac, 3)
                : null,
            'coef_act' => round($coefAct, 3),
            'amort_acum_inicio_act' => $aplicarCoefMoneda($amortAcumInicioAct),
            'amort_ejercicio_act' => $aplicarCoefMoneda($amortEjercicioAct),
            'valor_origen_act' => $aplicarCoefMoneda($valorOrigenAct),
            'valor_residual_contable_act' => $aplicarCoefMoneda($valorResidualContableAct),
            'proporcion_dias' => $proporcionDiasTxt,
            'amort_ejercicio_30' => $aplicarCoefMoneda($amortEjercicio / 30 * 30),
            // Revalúo (valores de ajuste, no de informe contable fiscal)
            'valor_residual' => round($valorResidual, 2),
            'valor_actual' => round($valorActual, 2),
            'valor_comienzo' => round($valorComienzo, 2),
            'incr_revaluo' => round($incrRevaluo, 2),
            'revaluo_acumulado' => round($incrRevaluoAnterior, 2),
            'cotizacion_compra' => $cotizacionCompra,
            'coef_moneda' => $coefMoneda,
            'periodo_anio' => $anioActual,
            'periodo_base' => $periodoBase,
            'indice_id' => $indiceId,
            'indice_tipo_valor' => $tipoValor,
        ];
    }

    /**
     * Lee coeficiente de bien_uso_indice_coeficiente para período Ym (Anita coeficiente()).
     */
    public function coeficiente(int $indiceId, string $periodoYm): float
    {
        $periodoYm = preg_replace('/\D/', '', $periodoYm) ?? '';
        if (strlen($periodoYm) < 6) {
            return 1.0;
        }
        $anio = (int) substr($periodoYm, 0, 4);
        $mes = (int) substr($periodoYm, 4, 2);
        if ($anio < 1900 || $mes < 1 || $mes > 12) {
            return 1.0;
        }

        $valor = BienUsoIndiceCoeficiente::query()
            ->where('bien_uso_indice_id', $indiceId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->value('coeficiente');

        $f = (float) ($valor ?? 0);
        return $f > 0 ? $f : 1.0;
    }

    /**
     * rdn Anita: redondeo a 2 decimales.
     */
    private function rdn(float $valor): float
    {
        return round($valor, 2);
    }

    private function coeficienteMoneda(
        int $monedaBienId,
        int $monedaDestinoId,
        float $cotizacionBien,
        bool $flMonOrigen
    ): float {
        if ($flMonOrigen || $monedaBienId === $monedaDestinoId) {
            return 1.0;
        }
        $local = CotizacionVigenteSupport::MONEDA_LOCAL_ID;
        // Bien en ME → expresión local: × cotización; local → ME: ÷ cotización destino
        if ($monedaDestinoId === $local && $monedaBienId !== $local) {
            return $cotizacionBien > 0 ? $cotizacionBien : 1.0;
        }
        if ($monedaBienId === $local && $monedaDestinoId !== $local) {
            $cotDest = CotizacionVigenteSupport::ventaValorOUno(now()->format('Y-m-d'), $monedaDestinoId);

            return $cotDest > 0 ? (1.0 / $cotDest) : 1.0;
        }

        return 1.0;
    }

    private function calcularVidaUtilTranscurrida(
        string $flAmort,
        int $anioCompra,
        int $mesCompra,
        int $anioActual,
        int $mesActual,
        int $vidaUtil,
        bool &$flAmortizado
    ): int {
        $flAmortizado = false;

        return match ($flAmort) {
            // BIEM_AMORT_UNID
            BienUsoAmortizacionUnidad::UNIDAD => ($anioActual - $anioCompra + 1) * $vidaUtil,
            // BIEM_AMORT_ANIO
            BienUsoAmortizacionUnidad::ANIO => $this->vidaUtilPorAnio(
                $anioCompra,
                $mesCompra,
                $anioActual,
                $mesActual
            ),
            // BIEM_AMORT_MES
            BienUsoAmortizacionUnidad::MES => $anioCompra !== $anioActual
                ? (($anioActual - $anioCompra) * 12) + $mesActual
                : $mesActual - $mesCompra + 1,
            // BIEM_REAL_MENSUAL
            BienUsoAmortizacionUnidad::REAL_MENSUAL => $this->vidaUtilRealMensual(
                $anioCompra,
                $mesCompra,
                $anioActual,
                $mesActual,
                $vidaUtil,
                $flAmortizado
            ),
            default => 0,
        };
    }

    private function vidaUtilPorAnio(
        int $anioCompra,
        int $mesCompra,
        int $anioActual,
        int $mesActual
    ): int {
        if ($anioCompra === $anioActual) {
            return 1;
        }
        $vida = $anioActual - $anioCompra;
        if (12 - $mesCompra + $mesActual >= 12) {
            $vida++;
        }

        return $vida;
    }

    private function vidaUtilRealMensual(
        int $anioCompra,
        int $mesCompra,
        int $anioActual,
        int $mesActual,
        int $vidaUtil,
        bool &$flAmortizado
    ): int {
        if ($anioCompra !== $anioActual) {
            // l-biemae.c:1461-1463
            $vida = (($anioActual - $anioCompra - 1) * 12)
                + ((12 - $mesCompra) + 1) + $mesActual;
        } else {
            $vida = $mesActual - $mesCompra + 1;
        }
        if ($vida > $vidaUtil) {
            $flAmortizado = true;

            return $vidaUtil;
        }

        return $vida;
    }

    /**
     * @return array{0: int, 1: int} [vida_ej_anterior, vida_ej_actual]
     */
    private function calcularVidasEjercicio(
        string $flAmort,
        bool $flAmortizado,
        int $vidaUtilTrans,
        int $vidaUtil,
        int $anioActual,
        int $mesActual,
        int $anioAlta,
        int $mesAlta,
        int $anioBaja
    ): array {
        // l-biemae.c:1561-1593
        if ($vidaUtilTrans - $mesActual <= 0 && $anioActual === $anioAlta) {
            $vidaEjAnterior = 0;
        } elseif (
            $flAmort === BienUsoAmortizacionUnidad::REAL_MENSUAL
            || $flAmort === BienUsoAmortizacionUnidad::MES
        ) {
            if ($anioActual - 1 === $anioAlta) {
                $vidaEjAnterior = 12 - $mesAlta + 1;
            } elseif (! $flAmortizado) {
                $vidaEjAnterior = $vidaUtilTrans - $mesActual;
            } else {
                $vidaEjAnterior = $vidaUtilTrans;
            }
        } elseif ($anioActual !== $anioAlta) {
            $vidaEjAnterior = $anioActual - $anioAlta - 1;
        } else {
            $vidaEjAnterior = 0;
        }

        if ($vidaEjAnterior > $vidaUtil) {
            $vidaEjAnterior = $vidaUtil;
        }

        // l-biemae.c:1597-1653
        if ($anioBaja > 0 && $anioBaja < $anioActual) {
            $vidaEjActual = 0;
        } elseif ($flAmort === BienUsoAmortizacionUnidad::REAL_MENSUAL) {
            if ($flAmortizado) {
                $vidaEjActual = $vidaUtil - $vidaEjAnterior;
            } elseif ($anioActual === $anioAlta && $mesAlta <= $mesActual) {
                $vidaEjActual = $mesActual - $mesAlta + 1;
            } else {
                $vidaEjActual = $mesActual;
            }
            if ($vidaEjActual <= 0) {
                $vidaEjActual = 0;
            }
            if ($vidaEjActual + $vidaEjAnterior > $vidaUtil) {
                $vidaEjActual = $vidaUtil - $vidaUtilTrans;
            }
            if ($anioActual === $anioAlta && $vidaEjActual <= 0) {
                $vidaEjActual = $vidaUtil;
                if ($vidaEjActual > 12) {
                    $vidaEjActual = 12;
                }
            }
        } elseif ($vidaUtil >= $vidaUtilTrans) {
            $vidaEjActual = $vidaUtilTrans > $mesActual ? $mesActual : $vidaUtilTrans;
        } else {
            $vidaEjActual = 0;
        }

        return [(int) $vidaEjAnterior, (int) $vidaEjActual];
    }
}
