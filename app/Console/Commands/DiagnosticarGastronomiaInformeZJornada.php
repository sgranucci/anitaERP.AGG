<?php

namespace App\Console\Commands;

use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaProcesoService;
use App\Services\Ventas\Gastronomia\GastronomiaCierreTotemJornadaService;
use App\Support\Ventas\Gastronomia\CierreJornadaFacturadoAnitaSupport;
use App\Support\Ventas\Waitry\WaitryCierreJornadaVentanaSupport;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use Carbon\Carbon;
use App\Support\Ventas\Waitry\WaitryOrdenEstadoSupport;
use App\Support\Ventas\Waitry\WaitryTotemJornadaResumenSupport;
use Illuminate\Console\Command;
use Throwable;

/**
 * Compara lectura Waitry: proceso Caja vs preview Informe Z (Ventas → Jornada).
 */
class DiagnosticarGastronomiaInformeZJornada extends Command
{
    protected $signature = 'gastronomia:diagnostico-informe-z-jornada
                            {jornada_id? : ID jornada (default: última abierta)}
                            {--refrescar : Borrar snapshot proceso y reconsultar Waitry}';

    protected $description = 'Diagnóstico: por qué Informe Z muestra $0 vs comandas cobradas en Caja';

    public function handle(
        GastronomiaCierreTotemJornadaService $cierreTotem,
        GastronomiaCierreJornadaProcesoService $procesoCaja,
    ): int {
        $jornadaId = (int) ($this->argument('jornada_id') ?? 0);
        $jornada = $jornadaId > 0
            ? JornadaGastronomia::query()->find($jornadaId)
            : JornadaGastronomia::query()->where('estado', JornadaGastronomia::ESTADO_ABIERTA)->orderByDesc('id')->first();

        if ($jornada === null) {
            $this->error('No hay jornada.');

            return self::FAILURE;
        }

        $empresaId = (int) $jornada->empresa_id;
        $fecha = $jornada->fecha_jornada?->format('Y-m-d') ?? '';

        $this->info(sprintf(
            'Jornada #%d · empresa %d · fecha %s · %s',
            $jornada->id,
            $empresaId,
            $fecha,
            $jornada->estado,
        ));

        try {
            $clasificacion = $procesoCaja->analizarJornada((int) $jornada->id, (bool) $this->option('refrescar'));
        } catch (Throwable $e) {
            $this->error('Caja analizar: '.$e->getMessage());

            return self::FAILURE;
        }

        $movimientos = $clasificacion['movimientos'] ?? [];
        $sinFacturar = $clasificacion['grupos']['sin_facturar_qr'] ?? [];
        $sinFacturarOtro = $clasificacion['grupos']['sin_facturar_otro'] ?? [];
        $waitryPago = (float) ($clasificacion['grilla']['cobrado_waitry_sin_facturar'] ?? 0);
        $totalPendiente = (float) ($clasificacion['total_pendiente_facturar'] ?? 0);

        $this->newLine();
        $this->line('=== CAJA (proceso cierre) ===');
        $this->line('Movimientos en snapshot: '.count($movimientos));
        $this->line('Grupo sin_facturar_qr: '.count($sinFacturar));
        $this->line('Grupo sin_facturar_otro: '.count($sinFacturarOtro));
        $this->line('Total pendiente facturar (cuadro): $'.number_format($totalPendiente, 2, ',', '.'));
        $this->line('cobrado_waitry_sin_facturar: $'.number_format($waitryPago, 2, ',', '.'));

        $tiposCaja = [];
        foreach (array_merge($sinFacturar, $sinFacturarOtro) as $m) {
            $t = WaitryMedioPagoCuentacajaSupport::normalizarTipo($m['waitry_tipo_pago'] ?? null) ?? '(null)';
            $tiposCaja[$t] = ($tiposCaja[$t] ?? 0) + 1;
        }
        $this->line('Tipos pago Waitry en cobrado sin facturar: '.json_encode($tiposCaja));

        $cierreReg = \App\Models\Ventas\CierreTotemJornadaGastronomia::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->first();
        if ($cierreReg !== null) {
            $this->line(sprintf(
                'Cierre tótem #%d · tickets #%s — #%s (anterior #%s)',
                $cierreReg->id,
                $cierreReg->waitry_order_id_desde ?? '—',
                $cierreReg->waitry_order_id_hasta ?? '—',
                $cierreReg->waitry_order_id_anterior ?? '—',
            ));
        }

        if (! $cierreTotem->habilitado()) {
            $this->warn('Cierre tótem deshabilitado en config.');

            return self::SUCCESS;
        }

        try {
            $preview = $cierreTotem->previewParaJornadaAbierta($jornada);
        } catch (Throwable $e) {
            $this->error('Preview Informe Z: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($preview === null) {
            $this->warn('Preview null.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('=== VENTAS Informe Z (preview) ===');
        $this->line('total_ingreso_totem: $'.number_format((float) ($preview['total_ingreso_totem'] ?? 0), 2, ',', '.'));
        $this->line('cantidad_ingreso_totem: '.(int) ($preview['cantidad_ingreso_totem'] ?? 0));
        $this->line('canceladas_excluidas: '.(int) ($preview['cantidad_canceladas_excluidas'] ?? 0));

        $diag = $preview['diagnostico_waitry'] ?? [];
        if ($diag !== []) {
            $this->line('diagnostico: '.json_encode($diag, JSON_UNESCAPED_UNICODE));
        }

        foreach ($preview['por_totem'] ?? [] as $bloque) {
            $this->line(sprintf(
                '  Tótem %s (%s): ingreso $%s · %d órdenes',
                $bloque['totem_id'] ?? '—',
                $bloque['detalle'] ?? '',
                number_format((float) ($bloque['total_ingreso'] ?? 0), 2, ',', '.'),
                (int) ($bloque['cantidad_ordenes'] ?? 0),
            ));
            foreach ($bloque['por_medio_pago'] ?? [] as $medio) {
                $this->line(sprintf(
                    '    · %s [%s]: $%s (%d)',
                    $medio['etiqueta'] ?? '—',
                    $medio['tipo'] ?? '—',
                    number_format((float) ($medio['total'] ?? 0), 2, ',', '.'),
                    (int) ($medio['cantidad'] ?? 0),
                ));
            }
        }

        foreach ($preview['totems'] ?? [] as $bloque) {
            $this->line('  Plantilla tótem '.($bloque['totem_id'] ?? '—').' total_sistema: $'
                .number_format((float) ($bloque['total_ingreso_sistema'] ?? 0), 2, ',', '.'));
            foreach ($bloque['lineas'] ?? [] as $ln) {
                $this->line(sprintf(
                    '    línea %s cc=%s sistema=$%s',
                    $ln['tipo_waitry'] ?? '—',
                    $ln['cuentacaja_id'] ?? '—',
                    number_format((float) ($ln['monto_sistema'] ?? 0), 2, ',', '.'),
                ));
            }
        }

        $this->newLine();
        $this->line('=== Fechas: jornada Anita vs calendario Waitry ===');
        $this->explicarVentanaFechas($cierreTotem, $jornada);

        $this->newLine();
        $this->line('=== Comparación Caja vs Informe Z (mismo tramo Waitry) ===');
        $this->compararFacturadoVsSinFacturar($cierreTotem, $jornada, $waitryPago);

        $this->newLine();
        $this->line('=== Informe Z — Sistema (credit_card, tramo jornada) ===');
        $this->informeZSistemaTramoJornada($cierreTotem, $jornada);

        $this->newLine();
        $this->line('=== Muestreo líneas (reconsulta tramo) ===');
        $this->muestrearTramo($cierreTotem, $jornada);

        return self::SUCCESS;
    }

    /**
     * Waitry API por día calendario; filtro operativo por placed_at; Anita facturado por fechajornada.
     */
    private function explicarVentanaFechas(
        GastronomiaCierreTotemJornadaService $cierreTotem,
        JornadaGastronomia $jornada,
    ): void {
        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        $cierreHasta = WaitryCierreJornadaVentanaSupport::resolverCierreHasta($jornada);

        $resuelto = WaitryCierreJornadaVentanaSupport::resolverParaCierreJornada(
            $fechaJornada,
            $jornada->apertura_en,
            $cierreHasta,
        );

        $this->line('fecha_jornada (Anita fechajornada / cuadro facturado): '.$fechaJornada);
        $this->line('apertura_en: '.($jornada->apertura_en?->format('Y-m-d H:i:s') ?? '—'));
        $this->line('cierre_hasta consulta: '.$cierreHasta->format('Y-m-d H:i:s'));
        $this->line('Ventana operativa (placed_at): '.$resuelto['ventana']['etiqueta']);
        $this->line('API getordersdetails (días calendario): '.$resuelto['rango_calendario']['etiqueta']);

        $idAnterior = $cierreTotem->waitryOrderIdAnteriorParaJornada($jornada);
        $ref = new \ReflectionClass($cierreTotem);
        $listar = $ref->getMethod('listarOrdenesWaitryNuevas');
        $listar->setAccessible(true);
        $armar = $ref->getMethod('armarLineasConEstadoErp');
        $armar->setAccessible(true);
        $listado = $listar->invoke(
            $cierreTotem,
            $empresaId,
            $fechaJornada,
            $idAnterior,
            $jornada->apertura_en,
            $cierreHasta,
        );
        $aud = $listado['auditoria'] ?? [];
        $this->line('Órdenes API en rango calendario, descartadas fuera de ventana: '
            .(int) ($aud['cantidad_descartadas_fuera_ventana'] ?? 0));
        $this->line('Órdenes incluidas tras filtro placed_at: '.(int) ($aud['cantidad_incluidas'] ?? 0));

        $filtro = WaitryOrdenEstadoSupport::filtrarOrdenesActivas($listado['ordenes'] ?? []);
        $ventana = $listado['ventana'] ?? [];
        $lineas = $armar->invoke(
            $cierreTotem,
            $empresaId,
            $filtro['activas'],
            $ventana['desde'] ?? null,
            $ventana['hasta'] ?? null,
        );

        $porDiaCalendario = [];
        $madrugadaSinFact = 0.0;
        $cntMadrugadaSinFact = 0;
        $facturadaOtraFechajornada = 0;
        $montoFacturadaOtraFj = 0.0;

        foreach ($lineas as $ln) {
            $placed = $ln['placed_at'] ?? null;
            $diaCal = 'sin_placed_at';
            if ($placed !== null && $placed !== '') {
                try {
                    $diaCal = Carbon::parse((string) $placed)->format('Y-m-d');
                } catch (\Throwable) {
                    $diaCal = 'parse_error';
                }
            }
            $porDiaCalendario[$diaCal] = ($porDiaCalendario[$diaCal] ?? 0) + 1;

            if ($diaCal !== $fechaJornada && $diaCal !== 'sin_placed_at' && $diaCal !== 'parse_error') {
                if (! empty($ln['facturada_erp'])) {
                    // facturada con placed_at en día calendario distinto al de jornada
                } elseif (WaitryTotemJornadaResumenSupport::lineaCuentaParaIngresoTotem($ln)) {
                    $m = (float) ($ln['monto_cobro_waitry'] ?? 0) > 0.0001
                        ? (float) $ln['monto_cobro_waitry']
                        : (float) ($ln['total'] ?? 0);
                    $madrugadaSinFact += $m;
                    $cntMadrugadaSinFact++;
                }
            }

            if (! empty($ln['facturada_erp']) && WaitryTotemJornadaResumenSupport::lineaCuentaParaIngresoTotem($ln)) {
                $wid = (int) ($ln['waitry_order_id'] ?? 0);
                if ($wid <= 0) {
                    continue;
                }
                $emision = VentaGastronomiaEmision::query()
                    ->where('waitry_order_id', $wid)
                    ->with('venta:id,fechajornada')
                    ->first();
                $fj = $emision?->venta?->fechajornada;
                $fjStr = $fj instanceof \DateTimeInterface
                    ? $fj->format('Y-m-d')
                    : substr((string) $fj, 0, 10);
                if ($fjStr !== '' && $fjStr !== $fechaJornada) {
                    $facturadaOtraFechajornada++;
                    $montoFacturadaOtraFj += (float) ($ln['total'] ?? 0);
                }
            }
        }

        ksort($porDiaCalendario);
        $this->line('Comandas por día de placed_at (calendario Waitry):');
        foreach ($porDiaCalendario as $dia => $cnt) {
            $marca = $dia === $fechaJornada ? '' : ' ← distinto a fecha_jornada';
            $this->line('  '.$dia.': '.$cnt.$marca);
        }

        if ($cntMadrugadaSinFact > 0) {
            $this->line(sprintf(
                'Cobro tótem sin facturar con placed_at fuera de %s: %d órdenes · $%s',
                $fechaJornada,
                $cntMadrugadaSinFact,
                number_format(round($madrugadaSinFact, 2), 2, ',', '.'),
            ));
        }

        if ($facturadaOtraFechajornada > 0) {
            $this->warn(sprintf(
                'En ventana Waitry pero factura con otra fechajornada: %d · $%s',
                $facturadaOtraFechajornada,
                number_format(round($montoFacturadaOtraFj, 2), 2, ',', '.'),
            ));
        }

        if ($fechaJornada !== '') {
            $anita = CierreJornadaFacturadoAnitaSupport::totalesJornadaEmpresa($empresaId, $fechaJornada);
            $j = $anita['anita_jornada'] ?? [];
            $t = $anita['anita_totem'] ?? [];
            $this->line('Anita facturado (solo venta.fechajornada = '.$fechaJornada.'):');
            $this->line('  Efectivo $'.number_format((float) ($j['efectivo'] ?? 0), 2, ',', '.')
                .' · QR $'.number_format((float) ($j['qr'] ?? 0), 2, ',', '.')
                .' · MP $'.number_format((float) ($j['mp'] ?? 0), 2, ',', '.')
                .' · Otros $'.number_format((float) ($j['otros'] ?? 0), 2, ',', '.'));
            $this->line('  Fila TOTEM (puente, medio Waitry real): $'.number_format((float) ($t['total'] ?? 0), 2, ',', '.'));
            $this->line('El Informe Z no usa fechajornada: usa cobro Waitry en ventana (placed_at).');
            $this->line('Efectivo cobrado en el POS Waitry no entra al Informe Z (excluido por diseño).');
        }
    }

    /**
     * Informe Z (columna Sistema) suma todo cobro en tótem; Caja solo «Waitry pagado sin facturar».
     */
    private function compararFacturadoVsSinFacturar(
        GastronomiaCierreTotemJornadaService $cierreTotem,
        JornadaGastronomia $jornada,
        float $cajaCobradoSinFacturar,
    ): void {
        $empresaId = (int) $jornada->empresa_id;
        $fecha = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        $idAnterior = $cierreTotem->waitryOrderIdAnteriorParaJornada($jornada);
        $cierreHasta = WaitryCierreJornadaVentanaSupport::resolverCierreHasta($jornada);

        $lineas = $this->lineasTramoWaitry($cierreTotem, $jornada, $empresaId, $fecha, $idAnterior, $cierreHasta);

        $totFact = 0.0;
        $cntFact = 0;
        $totSin = 0.0;
        $cntSin = 0;

        foreach ($lineas as $ln) {
            if (! WaitryTotemJornadaResumenSupport::lineaEntraInformeZSistema($ln)) {
                continue;
            }
            $monto = (float) ($ln['monto_cobro_waitry'] ?? 0);
            if ($monto <= 0.0001) {
                $monto = (float) ($ln['total'] ?? 0);
            }
            $monto = round($monto, 2);
            if (! empty($ln['facturada_erp'])) {
                $totFact += $monto;
                $cntFact++;
            } else {
                $totSin += $monto;
                $cntSin++;
            }
        }

        $totFact = round($totFact, 2);
        $totSin = round($totSin, 2);
        $totZ = round($totFact + $totSin, 2);
        $diffCaja = round($totSin - $cajaCobradoSinFacturar, 2);

        $this->line('Informe Z — credit_card Posnet en tramo (Sistema):');
        $this->line('  Ya facturadas en Anita: '.$cntFact.' órdenes · $'.number_format($totFact, 2, ',', '.'));
        $this->line('  Sin facturar en Anita: '.$cntSin.' órdenes · $'.number_format($totSin, 2, ',', '.'));
        $this->line('  Total (facturadas + sin facturar): $'.number_format($totZ, 2, ',', '.'));
        $this->line('Caja — cobrado_waitry_sin_facturar: $'.number_format($cajaCobradoSinFacturar, 2, ',', '.'));
        if (abs($diffCaja) > 0.02) {
            $this->warn('Diferencia sin facturar (Informe Z vs Caja): $'.number_format($diffCaja, 2, ',', '.')
                .' — refresque proceso Caja (--refrescar) si el snapshot está viejo.');
        } else {
            $this->line('Sin facturar: Informe Z y Caja coinciden.');
        }
        $this->line('Nota: ~$1M por tótem es el reparto del total facturado (~$'
            .number_format($totFact, 0, ',', '.').'), no el pendiente de facturar.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lineasTramoWaitry(
        GastronomiaCierreTotemJornadaService $cierreTotem,
        JornadaGastronomia $jornada,
        int $empresaId,
        string $fecha,
        int $idAnterior,
        Carbon $cierreHasta,
    ): array {
        $ref = new \ReflectionClass($cierreTotem);
        $listar = $ref->getMethod('listarOrdenesWaitryNuevas');
        $listar->setAccessible(true);
        $armar = $ref->getMethod('armarLineasConEstadoErp');
        $armar->setAccessible(true);
        $listado = $listar->invoke($cierreTotem, $empresaId, $fecha, $idAnterior, $jornada->apertura_en, $cierreHasta);
        $filtro = WaitryOrdenEstadoSupport::filtrarOrdenesActivas($listado['ordenes'] ?? []);
        $activas = $this->filtrarOrdenesActivasConTopeInformeZ($cierreTotem, $jornada, $filtro['activas']);
        $ventana = $listado['ventana'] ?? [];

        return $armar->invoke(
            $cierreTotem,
            $empresaId,
            $activas,
            $ventana['desde'] ?? null,
            $ventana['hasta'] ?? null,
        );
    }

    /**
     * Misma regla que {@see GastronomiaCierreTotemJornadaService::consultarTramoInformeZ()}.
     *
     * @param  array<int|string, array<string, mixed>>  $ordenesActivas
     * @return array<int|string, array<string, mixed>>
     */
    private function filtrarOrdenesActivasConTopeInformeZ(
        GastronomiaCierreTotemJornadaService $cierreTotem,
        JornadaGastronomia $jornada,
        array $ordenesActivas,
    ): array {
        $ref = new \ReflectionClass($cierreTotem);
        $resolver = $ref->getMethod('resolverTopeWaitryOrderIdHasta');
        $resolver->setAccessible(true);
        $filtrar = $ref->getMethod('filtrarOrdenesActivasPorTopeHasta');
        $filtrar->setAccessible(true);
        $tope = (int) $resolver->invoke($cierreTotem, $jornada, $ordenesActivas);

        return $filtrar->invoke($cierreTotem, $ordenesActivas, $tope);
    }

    private function informeZSistemaTramoJornada(
        GastronomiaCierreTotemJornadaService $cierreTotem,
        JornadaGastronomia $jornada,
    ): void {
        try {
            $consulta = $cierreTotem->datosTramoInformeZ($jornada);
        } catch (Throwable $e) {
            $this->error('Tramo Informe Z: '.$e->getMessage());

            return;
        }

        $resumen = $consulta['resumen_informe_z'] ?? [];
        $total = (float) ($resumen['total_general']['total_ingreso'] ?? 0);
        $cant = (int) ($resumen['total_general']['cantidad_ordenes'] ?? 0);

        $this->line('Ventana: '.($consulta['ventana_operativa'] ?? '—'));
        $this->line('Ticket anterior (excl.): #'.(int) ($consulta['waitry_order_id_anterior'] ?? 0));
        $this->line('Último ticket tope: #'.(int) ($consulta['waitry_order_id_hasta'] ?? 0)
            .' ('.($consulta['waitry_order_id_hasta_origen'] ?? '—').')');
        $this->line('Rango: '.($consulta['rango_etiqueta'] ?? '—'));
        $this->line('Comandas credit_card cobradas activas: '.$cant);
        $this->line('Total Sistema Informe Z: $'.number_format($total, 2, ',', '.'));

        foreach ($resumen['por_totem'] ?? [] as $bloque) {
            $this->line(sprintf(
                '  %s (#%s): $%s · %d comandas',
                $bloque['ubicacion_nombre'] ?? 'Tótem',
                $bloque['totem_id'] ?? '—',
                number_format((float) ($bloque['total_ingreso'] ?? 0), 2, ',', '.'),
                (int) ($bloque['cantidad_ordenes'] ?? 0),
            ));
        }

        $cierreReg = \App\Models\Ventas\CierreTotemJornadaGastronomia::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->first();
        if ($cierreReg !== null && is_array($cierreReg->informe_z_json)) {
            $totZ = 0.0;
            foreach ($cierreReg->informe_z_json['totems'] ?? [] as $t) {
                foreach ($t['lineas'] ?? [] as $ln) {
                    $totZ += (float) ($ln['monto'] ?? $ln['monto_informe_z'] ?? 0);
                }
            }
            $this->line('Z ingresado al cerrar: $'.number_format($totZ, 2, ',', '.'));
        }
    }

    private function muestrearTramo(GastronomiaCierreTotemJornadaService $cierreTotem, JornadaGastronomia $jornada): void
    {
        $empresaId = (int) $jornada->empresa_id;
        $fecha = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        $idAnterior = $cierreTotem->waitryOrderIdAnteriorParaJornada($jornada);
        $cierreHasta = WaitryCierreJornadaVentanaSupport::resolverCierreHasta($jornada);

        $ref = new \ReflectionClass($cierreTotem);
        $listar = $ref->getMethod('listarOrdenesWaitryNuevas');
        $listar->setAccessible(true);
        $listado = $listar->invoke($cierreTotem, $empresaId, $fecha, $idAnterior, $jornada->apertura_en, $cierreHasta);

        $ordenes = $listado['ordenes'] ?? [];
        $filtro = WaitryOrdenEstadoSupport::filtrarOrdenesActivas($ordenes);
        $activasTope = $this->filtrarOrdenesActivasConTopeInformeZ($cierreTotem, $jornada, $filtro['activas']);
        $lineas = $this->lineasTramoWaitry($cierreTotem, $jornada, $empresaId, $fecha, $idAnterior, $cierreHasta);

        $this->line('Órdenes en tramo (raw): '.count($ordenes));
        $this->line('Excluidas canceladas Waitry: '.$filtro['cantidad_excluidas']);
        $this->line('Activas con tope último ticket cierre: '.count($activasTope));
        $this->line('Líneas en ventana ERP: '.count($lineas));

        $conIngreso = 0;
        $sinIngreso = 0;
        $motivos = ['cancelada' => 0, 'tipo_excluido' => 0, 'no_cobrada' => 0, 'ok' => 0];
        $tiposOk = [];

        foreach ($lineas as $ln) {
            if (WaitryOrdenEstadoSupport::esCanceladaLinea($ln)) {
                $motivos['cancelada']++;
                $sinIngreso++;
                continue;
            }
            $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($ln['waitry_tipo_pago'] ?? null);
            if ($tipo !== null && WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($tipo)) {
                $motivos['tipo_excluido']++;
                $sinIngreso++;
                continue;
            }
            if (! WaitryTotemJornadaResumenSupport::lineaEntraInformeZSistema($ln)) {
                if (! WaitryTotemJornadaResumenSupport::cobradaEnWaitryLinea($ln)) {
                    $motivos['no_cobrada']++;
                } else {
                    $motivos['tipo_excluido']++;
                }
                $sinIngreso++;
                continue;
            }
            $motivos['ok']++;
            $conIngreso++;
            $t = 'credit_card';
            $tiposOk[$t] = ($tiposOk[$t] ?? 0) + 1;
        }

        $this->line("Cuentan para Informe Z: {$conIngreso} · descartadas: {$sinIngreso}");
        $this->line('Motivos descarte: '.json_encode($motivos));
        $this->line('Tipos en ingreso Z: '.json_encode($tiposOk));

        $creditCard = 0;
        foreach ($filtro['activas'] as $orden) {
            $t = WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($orden);
            if ($t === 'creditcard' || $t === 'credit_card') {
                $creditCard++;
            }
        }
        $this->line('Órdenes activas con tipo credit_card en API: '.$creditCard);

        foreach (array_slice($lineas, 0, 5) as $ln) {
            $this->line(sprintf(
                '  #%s paid=%s cobro=%s total=%s tipo=%s table=%s ingreso=%s',
                $ln['waitry_order_id'] ?? '',
                json_encode($ln['paid_waitry'] ?? null),
                $ln['monto_cobro_waitry'] ?? 0,
                $ln['total'] ?? 0,
                $ln['waitry_tipo_pago'] ?? 'null',
                $ln['waitry_table_id'] ?? 'null',
                WaitryTotemJornadaResumenSupport::lineaEntraInformeZSistema($ln) ? 'si' : 'no',
            ));
        }
    }
}
