<?php

namespace App\Support\Ventas\Gastronomia;

use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;

/**
 * Preview de asientos contables del cierre de jornada (sin persistir).
 */
final class CierreJornadaProcesoAsientosPreviewSupport
{
    private const TASA_IVA_DEFAULT = 21.0;

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, mixed>  $configContable
     * @return array{
     *   asientos: list<array<string, mixed>>,
     *   advertencias: list<string>,
     *   resumen_debe: float,
     *   resumen_haber: float
     * }
     */
    public static function generar(array $movimientos, int $empresaId, array $configContable): array
    {
        $advertencias = [];
        $faltantes = CierreJornadaProcesoConfigSupport::faltantes($configContable);
        if ($faltantes !== []) {
            $advertencias[] = 'Faltan cuentas contables: '.implode(', ', $faltantes).'.';
        }

        $cuentaVentas = (int) ($configContable['cuenta_ventas_id'] ?? 0);
        $cuentaIva = (int) ($configContable['cuenta_iva_id'] ?? 0);
        $cuentaImpInt = (int) ($configContable['cuenta_impuesto_interno_id'] ?? 0);
        $cuentaFondoFijo = (int) ($configContable['cuenta_fondo_fijo_maquinas_id'] ?? 0);

        $asientos = [];
        $n = 0;

        foreach ($movimientos as $mov) {
            $grupo = (string) ($mov['grupo'] ?? '');
            if ($grupo === CierreJornadaProcesoClasificacionSupport::GRUPO_HUECO_AUDITORIA) {
                continue;
            }
            if ($grupo === CierreJornadaProcesoClasificacionSupport::GRUPO_WAITRY_CASH_NO_FACTURAR) {
                continue;
            }

            $total = round((float) ($mov['total'] ?? 0), 2);
            if ($total <= 0.0001) {
                continue;
            }

            $impuestoInterno = round((float) ($mov['impuesto_interno'] ?? 0), 2);
            $base = self::desglosarBaseIva($total, $impuestoInterno);

            if ($grupo === CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL) {
                $medios = self::mediosPlanificadosFacturado($mov, $empresaId);
                $n++;
                $asientos[] = self::asientoFacturadoMedioReal(
                    $n,
                    $mov,
                    $medios,
                    $base,
                    $cuentaVentas,
                    $cuentaIva,
                    $cuentaImpInt,
                    $impuestoInterno,
                );
            } elseif ($grupo === CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM) {
                $claveReal = (string) ($mov['medio_waitry_clave'] ?? CierreJornadaProcesoMedioSupport::CLAVE_OTRO);
                $n++;
                $asientos[] = self::asientoTotemPrincipal(
                    $n,
                    $mov,
                    $base,
                    $cuentaVentas,
                    $cuentaIva,
                    $cuentaImpInt,
                    $impuestoInterno,
                    $empresaId,
                );
                $n++;
                $asientos[] = self::asientoTotemPuente(
                    $n,
                    $mov,
                    $total,
                    $claveReal,
                    $empresaId,
                );
            } elseif ($grupo === CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR) {
                if (! self::debeFacturarSinFacturar($mov)) {
                    continue;
                }
                $medios = self::mediosPlanificadosSinFacturar($mov, $empresaId);
                $n++;
                $asientos[] = self::asientoPendienteFacturacion(
                    $n,
                    $mov,
                    $medios,
                    $base,
                    $cuentaVentas,
                    $cuentaIva,
                    $cuentaImpInt,
                    $impuestoInterno,
                    $cuentaFondoFijo,
                );
            }
        }

        $debe = 0.;
        $haber = 0.;
        foreach ($asientos as $a) {
            foreach ($a['lineas'] ?? [] as $ln) {
                $debe += (float) ($ln['debe'] ?? 0);
                $haber += (float) ($ln['haber'] ?? 0);
            }
        }

        return [
            'asientos' => $asientos,
            'advertencias' => $advertencias,
            'resumen_debe' => round($debe, 2),
            'resumen_haber' => round($haber, 2),
        ];
    }

    /**
     * @return array{gravado:float,iva:float,neto_venta:float}
     */
    private static function desglosarBaseIva(float $total, float $impuestoInterno): array
    {
        $netoVentas = round(max(0., $total - $impuestoInterno), 2);
        $gravado = round($netoVentas / (1. + self::TASA_IVA_DEFAULT / 100.), 2);
        $iva = round($netoVentas - $gravado, 2);

        return [
            'gravado' => $gravado,
            'iva' => $iva,
            'neto_venta' => $netoVentas,
        ];
    }

    /**
     * Solo se factura lo pendiente con medio QR (totalcoin), tras redistribución.
     *
     * @param  array<string, mixed>  $mov
     */
    private static function debeFacturarSinFacturar(array $mov): bool
    {
        $plan = $mov['medios_pago_planificados'] ?? null;
        if (is_array($plan)) {
            foreach ($plan as $p) {
                if (($p['clave'] ?? '') === CierreJornadaProcesoMedioSupport::CLAVE_QR
                    && (float) ($p['monto'] ?? 0) > 0.0001) {
                    return true;
                }
            }

            return false;
        }

        $clave = (string) ($mov['medio_pago_planificado'] ?? $mov['medio_waitry_clave'] ?? '');

        return $clave === CierreJornadaProcesoMedioSupport::CLAVE_QR
            || $clave === ''
            || $clave === CierreJornadaProcesoMedioSupport::CLAVE_OTRO;
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>
     */
    private static function mediosPlanificadosFacturado(array $mov, int $empresaId): array
    {
        $plan = $mov['medios_pago_planificados'] ?? null;
        if (is_array($plan) && $plan !== []) {
            return self::resolverMediosDesdePlan($plan, $empresaId);
        }

        $clave = (string) ($mov['medio_pago_planificado'] ?? $mov['medio_anita_clave'] ?? '');
        $total = round((float) ($mov['total'] ?? 0), 2);

        return self::unMedio($clave, $total, $empresaId);
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>
     */
    private static function mediosPlanificadosSinFacturar(array $mov, int $empresaId): array
    {
        $plan = $mov['medios_pago_planificados'] ?? null;
        if (is_array($plan) && $plan !== []) {
            $filtrados = array_values(array_filter(
                $plan,
                fn (array $p) => ($p['clave'] ?? '') === CierreJornadaProcesoMedioSupport::CLAVE_QR
                    && (float) ($p['monto'] ?? 0) > 0.0001,
            ));
            if ($filtrados !== []) {
                return self::resolverMediosDesdePlan($filtrados, $empresaId);
            }
        }

        $total = round((float) ($mov['total'] ?? 0), 2);

        return self::unMedio(CierreJornadaProcesoMedioSupport::CLAVE_QR, $total, $empresaId);
    }

    /**
     * @param  list<array{clave:string,monto:float}>  $plan
     * @return list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>
     */
    private static function resolverMediosDesdePlan(array $plan, int $empresaId): array
    {
        $out = [];
        foreach ($plan as $p) {
            $clave = (string) ($p['clave'] ?? '');
            $monto = round((float) ($p['monto'] ?? 0), 2);
            if ($monto <= 0.0001) {
                continue;
            }
            foreach (self::unMedio($clave, $monto, $empresaId) as $m) {
                $out[] = $m;
            }
        }

        return $out;
    }

    /**
     * @return list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>
     */
    private static function unMedio(string $clave, float $monto, int $empresaId): array
    {
        $cuenta = self::cuentacajaPorClave($clave, $empresaId);

        return [[
            'clave' => $clave,
            'cuentacaja_id' => (int) ($cuenta['id'] ?? 0),
            'label' => (string) ($cuenta['label'] ?? CierreJornadaProcesoMedioSupport::etiquetaClave($clave)),
            'monto' => round($monto, 2),
        ]];
    }

    /**
     * @return array{id:int,label:string}
     */
    private static function cuentacajaPorClave(string $clave, int $empresaId): array
    {
        if ($clave === CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO) {
            $id = GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId);

            return ['id' => $id ?? 0, 'label' => 'Efectivo'];
        }
        if ($clave === CierreJornadaProcesoMedioSupport::CLAVE_QR) {
            $id = WaitryMedioPagoCuentacajaSupport::cuentacajaIdPorTipo(
                WaitryMedioPagoCuentacajaSupport::TIPO_TOTALCOIN,
                $empresaId,
            );

            return ['id' => $id ?? 0, 'label' => 'QR (Totalcoin)'];
        }
        if ($clave === CierreJornadaProcesoMedioSupport::CLAVE_MP) {
            $id = WaitryMedioPagoCuentacajaSupport::cuentacajaIdPorTipo(
                WaitryMedioPagoCuentacajaSupport::TIPO_MERCADOPAGO,
                $empresaId,
            );

            return ['id' => $id ?? 0, 'label' => 'Mercado Pago'];
        }

        return ['id' => 0, 'label' => CierreJornadaProcesoMedioSupport::etiquetaClave($clave)];
    }

    /**
     * @param  list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>  $medios
     * @param  array{gravado:float,iva:float,neto_venta:float}  $base
     * @return array<string, mixed>
     */
    private static function asientoFacturadoMedioReal(
        int $numero,
        array $mov,
        array $medios,
        array $base,
        int $cuentaVentas,
        int $cuentaIva,
        int $cuentaImpInt,
        float $impuestoInterno,
    ): array {
        $lineas = [];
        foreach ($medios as $m) {
            $lineas[] = self::lineaDebe(
                'Medio de cobro — '.$m['label'],
                $m['cuentacaja_id'],
                $m['monto'],
            );
        }
        $lineas = array_merge($lineas, self::lineasHaberVentas($base, $cuentaVentas, $cuentaIva, $cuentaImpInt, $impuestoInterno));

        return self::armarAsiento(
            $numero,
            'Facturado — medio real',
            $mov,
            $lineas,
        );
    }

    /**
     * @param  array{gravado:float,iva:float,neto_venta:float}  $base
     * @return array<string, mixed>
     */
    private static function asientoTotemPrincipal(
        int $numero,
        array $mov,
        array $base,
        int $cuentaVentas,
        int $cuentaIva,
        int $cuentaImpInt,
        float $impuestoInterno,
        int $empresaId,
    ): array {
        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);
        $total = round((float) ($mov['total'] ?? 0), 2);
        $lineas = [
            self::lineaDebe('TOTEM (puente cobro tótem)', (int) ($totem['id'] ?? 0), $total),
        ];
        $lineas = array_merge($lineas, self::lineasHaberVentas($base, $cuentaVentas, $cuentaIva, $cuentaImpInt, $impuestoInterno));

        return self::armarAsiento($numero, 'Facturado — asiento TOTEM → ventas/IVA', $mov, $lineas);
    }

  /**
     * @return array<string, mixed>
     */
    private static function asientoTotemPuente(
        int $numero,
        array $mov,
        float $total,
        string $claveReal,
        int $empresaId,
    ): array {
        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);
        $real = self::cuentacajaPorClave($claveReal, $empresaId);
        $lineas = [
            self::lineaDebe('Medio Waitry real — '.$real['label'], (int) $real['id'], $total),
            self::lineaHaber('Contra TOTEM', (int) ($totem['id'] ?? 0), $total),
        ];

        return self::armarAsiento(
            $numero,
            'Facturado — medio Waitry vs TOTEM',
            $mov,
            $lineas,
        );
    }

    /**
     * @param  list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>  $medios
     * @param  array{gravado:float,iva:float,neto_venta:float}  $base
     * @return array<string, mixed>
     */
    private static function asientoPendienteFacturacion(
        int $numero,
        array $mov,
        array $medios,
        array $base,
        int $cuentaVentas,
        int $cuentaIva,
        int $cuentaImpInt,
        float $impuestoInterno,
        int $cuentaFondoFijo,
    ): array {
        $lineas = [];
        foreach ($medios as $m) {
            $lineas[] = self::lineaDebe('A facturar — '.$m['label'], $m['cuentacaja_id'], $m['monto']);
        }
        $lineas = array_merge($lineas, self::lineasHaberVentas($base, $cuentaVentas, $cuentaIva, $cuentaImpInt, $impuestoInterno));
        if ($cuentaFondoFijo > 0) {
            $lineas[] = [
                'tipo' => 'info',
                'concepto' => 'Fondo fijo máquinas (referencia)',
                'cuenta_id' => $cuentaFondoFijo,
                'debe' => 0.,
                'haber' => 0.,
            ];
        }

        return self::armarAsiento(
            $numero,
            'Pendiente facturación QR',
            $mov,
            $lineas,
            true,
        );
    }

    /**
     * @param  array{gravado:float,iva:float,neto_venta:float}  $base
     * @return list<array<string, mixed>>
     */
    private static function lineasHaberVentas(
        array $base,
        int $cuentaVentas,
        int $cuentaIva,
        int $cuentaImpInt,
        float $impuestoInterno,
    ): array {
        $lineas = [];
        if ($impuestoInterno > 0.0001 && $cuentaImpInt > 0) {
            $gravadoSinIi = round(max(0., $base['gravado'] - $impuestoInterno), 2);
            if ($gravadoSinIi > 0.0001) {
                $lineas[] = self::lineaHaber('Ventas gravadas', $cuentaVentas, $gravadoSinIi);
            }
            $lineas[] = self::lineaHaber('Impuesto interno', $cuentaImpInt, $impuestoInterno);
        } else {
            $lineas[] = self::lineaHaber('Ventas gravadas', $cuentaVentas, $base['gravado']);
        }
        if ($base['iva'] > 0.0001) {
            $lineas[] = self::lineaHaber('IVA débito fiscal', $cuentaIva, $base['iva']);
        }

        return $lineas;
    }

    /**
     * @return array<string, mixed>
     */
    private static function lineaDebe(string $concepto, int $cuentaId, float $monto): array
    {
        return [
            'concepto' => $concepto,
            'cuenta_id' => $cuentaId,
            'debe' => round($monto, 2),
            'haber' => 0.,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function lineaHaber(string $concepto, int $cuentaId, float $monto): array
    {
        return [
            'concepto' => $concepto,
            'cuenta_id' => $cuentaId,
            'debe' => 0.,
            'haber' => round($monto, 2),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, mixed>
     */
    private static function armarAsiento(
        int $numero,
        string $titulo,
        array $mov,
        array $lineas,
        bool $pendiente = false,
    ): array {
        return [
            'numero' => $numero,
            'titulo' => $titulo,
            'pendiente_ejecucion' => $pendiente,
            'waitry_order_id' => $mov['waitry_order_id'] ?? null,
            'venta_codigo' => $mov['venta_codigo'] ?? '',
            'referencia' => trim((string) ($mov['display_id'] ?? '')),
            'total' => round((float) ($mov['total'] ?? 0), 2),
            'lineas' => $lineas,
        ];
    }
}
