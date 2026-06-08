<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Caja\Cuentacaja;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use App\Support\Ventas\Waitry\WaitryOrdenEstadoSupport;
use Carbon\Carbon;

/**
 * Detalle de una celda del cuadro de cierre (fila × medio) para conciliar contra Waitry.
 */
final class CierreJornadaCuadroDetalleSupport
{
    public const FILA_ANITA_JORNADA = 'anita_jornada';

    public const FILA_ANITA_TOTEM = 'anita_totem';

    public const FILA_WAITRY_PAGO = 'waitry_pago';

    public const FILA_WAITRY_IMPAGO = 'waitry_impago';

    public const FILA_WAITRY_CASH = 'waitry_cash';

    /** @var list<string> */
    public const FILAS = [
        self::FILA_ANITA_JORNADA,
        self::FILA_ANITA_TOTEM,
        self::FILA_WAITRY_PAGO,
        self::FILA_WAITRY_IMPAGO,
        self::FILA_WAITRY_CASH,
    ];

    /** @var list<string> */
    public const MEDIOS = ['qr', 'mp', 'efectivo', 'otros', 'diferencia_caja'];

    /**
     * @param  list<array<string, mixed>>  $movimientos  Movimientos clasificados del proceso
     * @return array{
     *   ok: bool,
     *   fila: string,
     *   medio: string,
     *   etiqueta_fila: string,
     *   etiqueta_medio: string,
     *   total_importe: float,
     *   total_registros: int,
     *   pagina: int,
     *   por_pagina: int,
     *   total_paginas: int,
     *   totales_por_medio_waitry: list<array{clave:string,etiqueta:string,registros:int,importe:float}>,
     *   items: list<array<string, mixed>>
     * }
     */
    public static function consultar(
        int $empresaId,
        string $fechaJornada,
        string $fila,
        string $medio,
        array $movimientos,
        int $pagina = 1,
        int $porPagina = 100,
    ): array {
        self::validarFilaMedio($fila, $medio, $empresaId);

        $mediosConsulta = self::mediosParaConsulta($fila, $medio, $empresaId);

        $pagina = max(1, $pagina);
        $porPagina = max(10, min(500, $porPagina));

        $items = match ($fila) {
            self::FILA_ANITA_JORNADA, self::FILA_ANITA_TOTEM => self::itemsDesdeEmisionesAnita(
                $empresaId,
                $fechaJornada,
                $fila,
                $medio,
            ),
            default => self::itemsDesdeMovimientosWaitry($movimientos, $fila, $mediosConsulta, $empresaId),
        };

        usort($items, function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['fecha_hora_raw'] ?? ''), (string) ($b['fecha_hora_raw'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a['waitry_order_id'] ?? 0)) <=> ((int) ($b['waitry_order_id'] ?? 0));
        });

        $totalRegistros = count($items);
        $totalImporte = round(array_sum(array_map(
            fn (array $it) => (float) ($it['total'] ?? 0),
            $items,
        )), 2);
        $offset = ($pagina - 1) * $porPagina;
        $slice = array_slice($items, $offset, $porPagina);

        $totalesPorMedioWaitry = count($mediosConsulta) > 1
            ? self::totalesPorMedioWaitryDesdeItems($items, $mediosConsulta)
            : [];

        return [
            'ok' => true,
            'fila' => $fila,
            'medio' => $medio,
            'etiqueta_fila' => self::etiquetaFila($fila),
            'etiqueta_medio' => self::etiquetaMedio($medio, $empresaId),
            'fecha_jornada' => $fechaJornada,
            'empresa_id' => $empresaId,
            'total_importe' => $totalImporte,
            'total_registros' => $totalRegistros,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => (int) max(1, (int) ceil($totalRegistros / $porPagina)),
            'totales_por_medio_waitry' => $totalesPorMedioWaitry,
            'items' => $slice,
        ];
    }

    public static function etiquetaFila(string $fila): string
    {
        return match ($fila) {
            self::FILA_ANITA_JORNADA => 'Facturado Anita (jornada — cobranzas ERP)',
            self::FILA_ANITA_TOTEM => 'Facturado Anita — cobro TOTEM (medio real Waitry)',
            self::FILA_WAITRY_PAGO => 'Waitry pagado sin facturar (a facturar)',
            self::FILA_WAITRY_IMPAGO => 'Waitry impago (referencia)',
            self::FILA_WAITRY_CASH => 'Efectivo Waitry — no se factura',
            default => $fila,
        };
    }

    public static function etiquetaMedio(string $medio, int $empresaId = 0): string
    {
        $ccId = CierreJornadaCuadroColumnasSupport::cuentacajaIdDesdeMedio($medio);
        if ($ccId !== null && $empresaId > 0) {
            $cuenta = Cuentacaja::query()
                ->whereKey($ccId)
                ->paraEmpresa($empresaId)
                ->first(['codigo', 'nombre']);
            if ($cuenta !== null) {
                $codigo = trim((string) ($cuenta->codigo ?? ''));
                $nombre = trim((string) ($cuenta->nombre ?? ''));

                return $codigo !== '' && $nombre !== ''
                    ? $codigo.' — '.$nombre
                    : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$ccId));
            }
        }

        return match ($medio) {
            'qr' => 'QR (Totalcoin / tótem)',
            'mp' => 'Mercado Pago',
            'efectivo' => 'Efectivo',
            'otros' => 'Otros',
            'diferencia_caja' => 'Dif. caja / invitaciones',
            default => $medio,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function itemsDesdeEmisionesAnita(
        int $empresaId,
        string $fechaJornada,
        string $fila,
        string $medio,
    ): array {
        if ($empresaId <= 0 || $fechaJornada === '') {
            return [];
        }

        $emisiones = VentaGastronomiaEmision::query()
            ->with(['venta', 'cuenta', 'waitryComandaEnvio', 'configuracionPuntoventa'])
            ->whereHas('venta', function ($q) use ($empresaId, $fechaJornada) {
                $q->whereDate('fechajornada', $fechaJornada)
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->get();

        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);
        $esFilaTotem = $fila === self::FILA_ANITA_TOTEM;
        $ccIdFiltro = CierreJornadaCuadroColumnasSupport::cuentacajaIdDesdeMedio($medio);
        $medioAgregado = $ccIdFiltro === null ? $medio : null;
        $out = [];

        foreach ($emisiones as $emision) {
            $venta = $emision->venta;
            if ($venta === null) {
                continue;
            }

            $monto = round((float) ($venta->total ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }

            $esTotem = CierreJornadaFacturadoAnitaSupport::esFacturaCobroTotemPublico($emision, $empresaId, $totemId);
            if ($esTotem !== $esFilaTotem) {
                continue;
            }

            if ($medio === 'diferencia_caja') {
                $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
                $mediosCob = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
                $sumCob = 0.;
                foreach ($mediosCob as $lineas) {
                    foreach ($lineas as $medioCob) {
                        $sumCob = round($sumCob + (float) ($medioCob->monto ?? 0), 2);
                    }
                }
                if (! CierreJornadaFacturadoAnitaSupport::esInvitacionSinCobranzaPublico($monto, $sumCob)) {
                    continue;
                }
                $out[] = self::itemDesdeEmisionAnita($emision, $fila, $medio, $monto, 'Invitación / dif. caja');

                continue;
            }

            $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
            $mediosCob = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
            $sumCob = 0.;
            $lineasCob = [];
            foreach ($mediosCob as $lineas) {
                foreach ($lineas as $medioCob) {
                    $ccId = (int) ($medioCob->cuentacaja_id ?? 0);
                    $montoMedio = round((float) ($medioCob->monto ?? 0), 2);
                    if ($ccId <= 0 || abs($montoMedio) <= 0.0001) {
                        continue;
                    }
                    $sumCob = round($sumCob + $montoMedio, 2);
                    $clave = CierreJornadaProcesoMedioSupport::claveDesdeCuentacaja(
                        ['id' => $ccId, 'codigo' => (string) ($medioCob->codigo ?? '')],
                        $empresaId,
                    );
                    $col = CierreJornadaFacturadoAnitaSupport::columnaCuadroDesdeClaveMedio($clave);
                    $codigo = trim((string) ($medioCob->codigo ?? ''));
                    $nombre = trim((string) ($medioCob->nombre ?? ''));
                    $label = $codigo !== '' && $nombre !== ''
                        ? $codigo.' — '.$nombre
                        : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$ccId));
                    $lineasCob[] = ['col' => $col, 'monto' => $montoMedio, 'label' => $label, 'cc_id' => $ccId];
                }
            }

            if ($lineasCob === [] && abs($sumCob) <= 0.0001 && abs($monto) > 0.0001) {
                $colEmision = CierreJornadaFacturadoAnitaSupport::columnaMedioParaEmisionPublico(
                    $emision,
                    $empresaId,
                    $totemId,
                    $esTotem,
                );
                if ($colEmision === $medioAgregado) {
                    $out[] = self::itemDesdeEmisionAnita(
                        $emision,
                        $fila,
                        $medio,
                        $monto,
                        self::primerMedioCobranzaLabel($emision),
                    );
                }

                continue;
            }

            foreach ($lineasCob as $ln) {
                if ($ccIdFiltro !== null) {
                    if ((int) ($ln['cc_id'] ?? 0) !== $ccIdFiltro) {
                        continue;
                    }
                } elseif ($ln['col'] !== $medioAgregado) {
                    continue;
                }
                $out[] = self::itemDesdeEmisionAnita(
                    $emision,
                    $fila,
                    $medio,
                    $ln['monto'],
                    $ln['label'],
                );
            }
        }

        return $out;
    }

    private static function itemDesdeEmisionAnita(
        VentaGastronomiaEmision $emision,
        string $fila,
        string $medio,
        float $monto,
        string $medioAnitaLabel,
    ): array {
        $venta = $emision->venta;
        $waitryId = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
        $waitryTipo = $emision->cuenta?->waitry_tipo_pago;
        $fechaHora = $venta?->created_at;

        return self::formatearItem([
            'waitry_order_id' => $waitryId > 0 ? $waitryId : null,
            'display_id' => $waitryId > 0 ? '#'.$waitryId : (string) ($venta->codigo ?? ''),
            'fecha_hora' => $fechaHora,
            'placed_at_fmt' => self::formatearFechaHora($fechaHora),
            'total' => round($monto, 2),
            'venta_id' => (int) ($venta->id ?? 0),
            'venta_codigo' => (string) ($venta->codigo ?? ''),
            'waitry_tipo_pago' => $waitryTipo,
            'waitry_medio_label' => WaitryMedioPagoCuentacajaSupport::etiquetaTipo($waitryTipo),
            'medio_anita_label' => $medioAnitaLabel,
            'medio_cuadro' => $medio,
            'origen' => $fila,
            'facturada_erp' => true,
            'paid_waitry' => null,
            'fuente' => 'anita',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  list<string>  $medios
     * @return list<array<string, mixed>>
     */
    private static function itemsDesdeMovimientosWaitry(
        array $movimientos,
        string $fila,
        array $medios,
        int $empresaId = 0,
    ): array {
        if ($medios === []) {
            return [];
        }

        if (count($medios) === 1) {
            return self::itemsDesdeMovimientosWaitryPorMedio($movimientos, $fila, $medios[0], $empresaId);
        }

        $out = [];
        $vistos = [];
        foreach ($medios as $medio) {
            foreach (self::itemsDesdeMovimientosWaitryPorMedio($movimientos, $fila, $medio, $empresaId) as $item) {
                $orderId = (int) ($item['waitry_order_id'] ?? 0);
                if ($orderId > 0) {
                    if (isset($vistos[$orderId])) {
                        continue;
                    }
                    $vistos[$orderId] = true;
                }
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    private static function itemsDesdeMovimientosWaitryPorMedio(
        array $movimientos,
        string $fila,
        string $medio,
        int $empresaId = 0,
    ): array {
        $out = [];

        foreach ($movimientos as $mov) {
            $planificados = $mov['medios_pago_planificados'] ?? null;
            if (is_array($planificados) && $planificados !== [] && empty($mov['facturada_erp'])) {
                foreach ($planificados as $parte) {
                    $colParte = self::columnaDesdeClave((string) ($parte['clave'] ?? ''));
                    if ($colParte !== $medio || ! self::movimientoPerteneceCelda($mov, $fila, $medio, $colParte)) {
                        continue;
                    }
                    $out[] = self::itemDesdeMovimientoWaitry($mov, $fila, $medio, (float) ($parte['monto'] ?? 0));
                }
                continue;
            }

            if (! self::movimientoPerteneceCelda($mov, $fila, $medio)) {
                continue;
            }

            $out[] = self::itemDesdeMovimientoWaitry($mov, $fila, $medio, (float) ($mov['total'] ?? 0));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function itemDesdeMovimientoWaitry(array $mov, string $fila, string $medio, float $monto): array
    {
        $waitryTipo = $mov['waitry_tipo_pago'] ?? null;
        $fechaRaw = $mov['placed_at'] ?? null;
        $planLabel = self::etiquetaMedioPlanificado($mov);

        $ventaId = (int) ($mov['venta_id'] ?? 0);

        return self::formatearItem([
            'waitry_order_id' => (int) ($mov['waitry_order_id'] ?? 0) ?: null,
            'display_id' => (string) ($mov['display_id'] ?? ''),
            'fecha_hora' => $fechaRaw,
            'placed_at_fmt' => (string) ($mov['placed_at_fmt'] ?? self::formatearFechaHora($fechaRaw)),
            'total' => round($monto, 2),
            'venta_id' => $ventaId > 0 ? $ventaId : null,
            'venta_codigo' => (string) ($mov['venta_codigo'] ?? ''),
            'waitry_tipo_pago' => $waitryTipo,
            'waitry_medio_label' => (string) ($mov['waitry_medio_label'] ?? WaitryMedioPagoCuentacajaSupport::etiquetaTipo($waitryTipo)),
            'medio_waitry_clave' => (string) ($mov['medio_waitry_clave'] ?? ''),
            'medio_planificado_label' => $planLabel,
            'medio_anita_label' => (string) ($mov['anita_cuentacaja_label'] ?? ''),
            'medio_cuadro' => $medio,
            'origen' => $fila,
            'facturada_erp' => ! empty($mov['facturada_erp']),
            'paid_waitry' => $mov['paid_waitry'] ?? null,
            'fuente' => (string) ($mov['fuente_listado'] ?? 'waitry'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function etiquetaMedioPlanificado(array $mov): string
    {
        $plan = $mov['medios_pago_planificados'] ?? null;
        if (is_array($plan) && $plan !== []) {
            return implode(' · ', array_map(
                fn (array $p) => self::etiquetaMedio((string) ($p['clave'] ?? '')).' '.number_format((float) ($p['monto'] ?? 0), 2, ',', '.'),
                $plan,
            ));
        }
        $clave = (string) ($mov['medio_pago_planificado'] ?? '');
        if ($clave !== '' && $clave !== 'mixto') {
            return self::etiquetaMedio($clave);
        }

        return '—';
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function movimientoPerteneceCelda(array $mov, string $fila, string $medio, ?string $columnaForzada = null): bool
    {
        if (! empty($mov['discrepancia_gap'])) {
            return false;
        }

        $total = round((float) ($mov['total'] ?? 0), 2);
        if ($total <= 0.0001) {
            return false;
        }

        $col = $columnaForzada ?? self::columnaDesdeClave((string) ($mov['medio_waitry_clave'] ?? CierreJornadaProcesoMedioSupport::CLAVE_OTRO));

        if ($fila === self::FILA_WAITRY_CASH) {
            return $medio === 'efectivo'
                && CierreJornadaProcesoMedioSupport::esWaitryCash($mov['waitry_tipo_pago'] ?? null)
                && empty($mov['facturada_erp']);
        }

        if (CierreJornadaProcesoMedioSupport::esWaitryCash($mov['waitry_tipo_pago'] ?? null)) {
            return false;
        }

        if ($col !== $medio) {
            return false;
        }

        if (! empty($mov['facturada_erp'])) {
            return false;
        }

        $cobrada = self::cobradaEnWaitry($mov);

        return match ($fila) {
            self::FILA_WAITRY_PAGO => $cobrada,
            self::FILA_WAITRY_IMPAGO => ! $cobrada
                && ! WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotalLinea($mov),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function cobradaEnWaitry(array $mov): bool
    {
        if (! empty($mov['waitry_cobro_totem'])) {
            return true;
        }
        if (($mov['paid_waitry'] ?? null) === true) {
            return true;
        }

        return (float) ($mov['monto_cobro_waitry'] ?? 0) > 0.0001;
    }

    private static function columnaDesdeClave(string $clave): string
    {
        return match ($clave) {
            CierreJornadaProcesoMedioSupport::CLAVE_QR => 'qr',
            CierreJornadaProcesoMedioSupport::CLAVE_MP => 'mp',
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO => 'efectivo',
            default => 'otros',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function formatearItem(array $data): array
    {
        $fecha = $data['fecha_hora'] ?? null;
        $raw = '';
        if ($fecha instanceof \DateTimeInterface) {
            $raw = $fecha->format('Y-m-d H:i:s');
        } elseif ($fecha !== null && $fecha !== '') {
            try {
                $raw = Carbon::parse((string) $fecha)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $raw = (string) $fecha;
            }
        }

        $data['fecha_hora_raw'] = $raw;
        $data['fecha_hora_fmt'] = $data['placed_at_fmt'] ?? self::formatearFechaHora($fecha);

        return $data;
    }

    private static function formatearFechaHora(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '—';
        }

        try {
            if ($fecha instanceof \DateTimeInterface) {
                return $fecha->format('d/m/Y H:i:s');
            }

            return Carbon::parse((string) $fecha)->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return (string) $fecha;
        }
    }

    private static function primerMedioCobranzaLabel(VentaGastronomiaEmision $emision): string
    {
        $venta = $emision->venta;
        if ($venta === null) {
            return '';
        }

        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
        $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
        foreach ($medios as $lineas) {
            foreach ($lineas as $medio) {
                $ccId = (int) ($medio->cuentacaja_id ?? 0);
                if ($ccId <= 0) {
                    continue;
                }
                $codigo = trim((string) ($medio->codigo ?? ''));
                $nombre = trim((string) ($medio->nombre ?? ''));

                return $codigo !== '' && $nombre !== ''
                    ? $codigo.' — '.$nombre
                    : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$ccId));
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private static function mediosParaConsulta(string $fila, string $medio, int $empresaId): array
    {
        if ($fila === self::FILA_ANITA_JORNADA || $fila === self::FILA_ANITA_TOTEM) {
            return [$medio];
        }

        if (CierreJornadaCuadroColumnasSupport::cuentacajaIdDesdeMedio($medio) !== null) {
            return CierreJornadaCuadroColumnasSupport::columnasAgregadasDesdeMedio($medio, $empresaId);
        }

        return [$medio];
    }

    /**
     * Desglose QR/MP cuando varios medios Waitry comparten la misma cuenta de caja en el cuadro.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $mediosConsulta
     * @return list<array{clave:string,etiqueta:string,registros:int,importe:float}>
     */
    private static function totalesPorMedioWaitryDesdeItems(array $items, array $mediosConsulta): array
    {
        $acumulado = [];
        foreach ($mediosConsulta as $col) {
            $acumulado[$col] = ['registros' => 0, 'importe' => 0.0];
        }

        foreach ($items as $item) {
            $clave = (string) ($item['medio_waitry_clave'] ?? '');
            if ($clave === '') {
                $clave = CierreJornadaProcesoMedioSupport::claveDesdeWaitryTipo(
                    $item['waitry_tipo_pago'] ?? null,
                    $item['waitry_payment_gateway'] ?? null,
                );
            }
            $col = self::columnaDesdeClave($clave);
            if (! isset($acumulado[$col])) {
                continue;
            }
            $acumulado[$col]['registros']++;
            $acumulado[$col]['importe'] = round($acumulado[$col]['importe'] + (float) ($item['total'] ?? 0), 2);
        }

        $out = [];
        foreach ($mediosConsulta as $col) {
            if (! isset($acumulado[$col])) {
                continue;
            }
            $out[] = [
                'clave' => $col,
                'etiqueta' => self::etiquetaMedio($col),
                'registros' => (int) $acumulado[$col]['registros'],
                'importe' => (float) $acumulado[$col]['importe'],
            ];
        }

        return $out;
    }

    private static function validarFilaMedio(string $fila, string $medio, int $empresaId = 0): void
    {
        if (! in_array($fila, self::FILAS, true)) {
            throw new \InvalidArgumentException(
                'Fila inválida «'.$fila.'». Valores: '.implode(', ', self::FILAS).'.'
            );
        }

        if (! self::esMedioValido($medio)) {
            throw new \InvalidArgumentException(
                'Medio inválido «'.$medio.'». Use qr, mp, efectivo, otros, diferencia_caja o cc:{id}.'
            );
        }

        if ($fila === self::FILA_WAITRY_CASH) {
            $medioEfectivo = $medio === 'efectivo';
            if (! $medioEfectivo && $empresaId > 0) {
                $col = CierreJornadaCuadroColumnasSupport::columnaAgregadaDesdeMedio($medio, $empresaId);
                $medioEfectivo = $col === 'efectivo';
            }
            if (! $medioEfectivo) {
                throw new \InvalidArgumentException('La fila waitry_cash solo admite medio efectivo.');
            }
        }
    }

    private static function esMedioValido(string $medio): bool
    {
        if (in_array($medio, self::MEDIOS, true)) {
            return true;
        }

        return CierreJornadaCuadroColumnasSupport::cuentacajaIdDesdeMedio($medio) !== null;
    }
}
