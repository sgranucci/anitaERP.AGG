<?php

namespace App\Services\Caja;

use App\Mail\Caja\IngresoEgresoImputacionDiaria;
use App\Models\Caja\Caja_Movimiento;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use App\Support\Caja\IngresoEgresoImputacionDiariaAnitaReader;
use App\Support\Caja\IngresoEgresoImputacionDiariaSupport as Ie;
use App\Support\Compras\PagoproveedorAnitaAuditoriaCompareSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Informe diario I/E: caja/cheques ERP ↔ tesmov Anita; asiento ERP ↔ ctamov Anita.
 */
final class IngresoEgresoImputacionDiariaService
{
    public function __construct(
        private readonly IngresoEgresoImputacionDiariaAnitaReader $anita,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $enviarMail = true,
    ): array {
        $config = config('caja.ingresoegreso_imputacion_diaria', []);
        $ventana = max(1, (int) ($config['ventana_dias'] ?? 7));
        $desde = $fechaDesde ?: Carbon::today()->subDays($ventana - 1)->toDateString();
        $hasta = $fechaHasta ?: Carbon::today()->toDateString();
        $tolerancia = (float) ($config['tolerancia'] ?? Ie::TOLERANCIA);
        $maxFilasMail = max(10, (int) ($config['max_filas_mail'] ?? 80));

        $empresaIds = array_values(array_filter(array_map(
            'intval',
            (array) ($config['empresas_ids'] ?? [])
        ), static fn (int $id) => $id > 0));
        if ($empresaIds === []) {
            $empresaIds = Empresa::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $movimientos = Caja_Movimiento::query()
            ->with([
                'tipotransaccioncajas:id,abreviatura,nombre,signo',
                'empresas:id,codigo,nombre',
                'proveedores:id,codigo,nombre',
                'caja_movimiento_cuentacajas.cuentacajas:id,codigo,nombre,cuentacontable_id',
                'cheques.cuentacajas:id,codigo,nombre',
                'asientos.asiento_movimientos',
                'pagoproveedores.asientos.asiento_movimientos',
                'movimientoOrigen.tipotransaccioncajas:id,abreviatura',
            ])
            ->whereIn('empresa_id', $empresaIds)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereNull('caja_movimiento_revertido_por_id')
            ->where(function ($q) {
                $q->whereHas('tipotransaccioncajas', function ($t) {
                    $t->whereIn(\DB::raw('UPPER(TRIM(abreviatura))'), Ie::TIPOS_IE);
                })->orWhere(function ($q2) {
                    $q2->whereHas('tipotransaccioncajas', function ($t) {
                        $t->whereIn(\DB::raw('UPPER(TRIM(abreviatura))'), Ie::TIPOS_OPP_IE);
                    })->whereDoesntHave('pagoproveedores.proveedor_cuentacorrientes');
                });
            })
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $filas = $this->armarFilas($movimientos, $tolerancia);
        $desvios = array_values(array_filter($filas, static fn (array $f) => empty($f['ok'])));
        $ok = count($filas) - count($desvios);

        $totales = [
            'total_filas' => count($filas),
            'ok' => $ok,
            'con_desvio' => count($desvios),
            'sin_asiento' => 0,
            'sin_tesmov' => 0,
            'sin_ctamov' => 0,
            'sin_pago' => 0,
            'caja_ars' => 0.0,
            'cheques_ars' => 0.0,
            'asiento_ars' => 0.0,
            'tesmov_ars' => 0.0,
        ];
        foreach ($desvios as $fila) {
            $alertas = $fila['alertas'] ?? [];
            if (in_array('Sin asiento', $alertas, true)) {
                $totales['sin_asiento']++;
            }
            if (in_array('Sin tesmov Anita', $alertas, true)) {
                $totales['sin_tesmov']++;
            }
            if (in_array('Sin ctamov Anita', $alertas, true)) {
                $totales['sin_ctamov']++;
            }
            if (in_array('Sin pago Anita', $alertas, true)) {
                $totales['sin_pago']++;
            }
        }
        foreach ($filas as $fila) {
            $totales['caja_ars'] += (float) ($fila['caja_ars'] ?? 0);
            $totales['cheques_ars'] += (float) ($fila['cheques_ars'] ?? 0);
            $totales['asiento_ars'] += (float) ($fila['asiento_ars'] ?? 0);
            $totales['tesmov_ars'] += (float) ($fila['tesmov_ars'] ?? 0);
        }
        foreach (['caja_ars', 'cheques_ars', 'asiento_ars', 'tesmov_ars'] as $k) {
            $totales[$k] = round((float) $totales[$k], 2);
        }

        $errores = [];
        if ($totales['total_filas'] === 0) {
            $errores[] = 'Sin I/E en el período: no hay control para marcar OK.';
        }

        $informe = [
            'fecha_calendario' => $desde.' → '.$hasta,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'empresa_ids' => $empresaIds,
            'tolerancia' => $tolerancia,
            'totales' => $totales,
            'desvios' => $desvios,
            'desvios_mail' => array_slice($desvios, 0, $maxFilasMail),
            'desvios_omitidos' => max(0, count($desvios) - $maxFilasMail),
            'errores' => $errores,
            'requiere_alerta' => $errores !== [] || $totales['con_desvio'] > 0,
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
            'notas' => [
                'Incluye ING / EGR / TRA y OPP/OPA de tesorería (sin CC de proveedor).',
                'No incluye cobranzas ni las OP de proveedores con facturas/anticipo (esas van al control AP).',
                'Caja + cheques ERP se cruza con tesmov Anita. El asiento ERP se cruza con ctamov.',
                'En ING/EGR/TRA además se exige que caja+cheques cuadre con el total del asiento.',
                'OPP/OPA de I/E no exigen caja = asiento (el asiento incluye AP/retenciones).',
                'Compensatorios OPP/OPA se cruzan en Anita como AOP del nro original (no del nro ERP del reverso).',
            ],
        ];

        if ($enviarMail) {
            $this->enviarMailSiCorresponde($informe, $config);
        }

        return $informe;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Caja_Movimiento>  $movimientos
     * @return list<array<string, mixed>>
     */
    private function armarFilas($movimientos, float $tolerancia): array
    {
        if ($movimientos->isEmpty()) {
            return [];
        }

        $clavesComp = [];
        $clavesCtamov = [];
        $chequesClaves = [];
        foreach ($movimientos as $mov) {
            $tipo = Ie::tipoDesdeAbreviatura((string) ($mov->tipotransaccioncajas?->abreviatura ?? ''));
            $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $mov->empresa_id);
            $refAnita = IngresoEgresoAnitaTesmovSupport::referenciaAnitaParaControl($mov);
            $nroAnita = (int) ($refAnita['numero'] ?? 0);
            if ($empresaAnita > 0 && $nroAnita > 0) {
                $clavesComp[] = [
                    'empresa_anita' => $empresaAnita,
                    'tipo' => (string) ($refAnita['tipo'] ?? $tipo),
                    'numero' => $nroAnita,
                ];
            }
            $nroAsiento = (int) ($this->asientoDeMovimiento($mov)?->numeroasiento ?? 0);
            if ($empresaAnita > 0 && $nroAsiento > 0) {
                $clavesCtamov[] = [
                    'empresa_anita' => $empresaAnita,
                    'numeroasiento' => $nroAsiento,
                ];
            }
            foreach ($mov->cheques ?? [] as $cheque) {
                if (strtoupper((string) ($cheque->origen ?? '')) !== 'E') {
                    continue;
                }
                $nroCh = (int) preg_replace('/\D/', '', (string) $cheque->numerocheque);
                $cuenta = trim((string) ($cheque->cuentacajas?->codigo ?? ''));
                if ($nroCh > 0 && $cuenta !== '') {
                    $chequesClaves[] = [
                        'cuenta' => $cuenta,
                        'nro_cheque' => $nroCh,
                        'empresa_anita' => $empresaAnita,
                    ];
                }
            }
        }

        $tesmov = $this->anita->tesmovPorComprobante($clavesComp);
        $pagosAnita = $this->anita->pagoExistePorComprobante($clavesComp);
        $ctamov = $this->anita->ctamovPorAsiento($clavesCtamov);
        $chequesAnita = $chequesClaves === [] ? [] : $this->anita->chequesPropios($chequesClaves);

        $out = [];
        foreach ($movimientos as $mov) {
            $tipo = Ie::tipoDesdeAbreviatura((string) ($mov->tipotransaccioncajas?->abreviatura ?? ''));
            $fecha = substr((string) $mov->fecha, 0, 10);
            $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $mov->empresa_id);
            $nro = (int) $mov->numerotransaccion;

            $cajaDebe = 0.0;
            $cajaHaber = 0.0;
            foreach ($mov->caja_movimiento_cuentacajas ?? [] as $linea) {
                $ars = Ie::aPesos(
                    (float) ($linea->monto ?? 0),
                    (int) ($linea->moneda_id ?: 1),
                    $linea->cotizacion ?? 1
                );
                if ($ars >= 0) {
                    $cajaDebe += $ars;
                } else {
                    $cajaHaber += abs($ars);
                }
            }
            $cajaDebe = round($cajaDebe, 2);
            $cajaHaber = round($cajaHaber, 2);
            $cajaArs = round($cajaDebe + $cajaHaber, 2);

            $chequesArs = 0.0;
            $chequesEmitidos = 0;
            $sinCpromae = 0;
            $sinTesmovChp = 0;
            foreach ($mov->cheques ?? [] as $cheque) {
                $origen = strtoupper((string) ($cheque->origen ?? ''));
                $montoCh = abs(Ie::aPesos(
                    (float) ($cheque->monto ?? 0),
                    (int) ($cheque->moneda_id ?: 1),
                    $cheque->cotizacion ?? 1
                ));
                if ($origen === 'E') {
                    $chequesArs += $montoCh;
                    $chequesEmitidos++;
                    $nroCh = (int) preg_replace('/\D/', '', (string) $cheque->numerocheque);
                    $cuenta = trim((string) ($cheque->cuentacajas?->codigo ?? ''));
                    $info = $chequesAnita[IngresoEgresoImputacionDiariaAnitaReader::claveCheque($cuenta, $nroCh)]
                        ?? ['cpromae' => false, 'tesmov_chp' => false];
                    if (empty($info['cpromae'])) {
                        $sinCpromae++;
                    }
                    if (empty($info['tesmov_chp'])) {
                        $sinTesmovChp++;
                    }
                } elseif ($origen === 'R') {
                    $chequesArs += $montoCh;
                }
            }
            $chequesArs = round($chequesArs, 2);

            $asiento = $this->asientoDeMovimiento($mov);
            $tieneAsiento = $asiento !== null && (int) ($asiento->id ?? 0) > 0;
            $balance = $tieneAsiento
                ? PagoproveedorAnitaAuditoriaCompareSupport::balanceDesdeAsientoMovimientos($asiento->asiento_movimientos ?? [])
                : ['total_debe' => 0.0, 'total_haber' => 0.0, 'lineas_con_importe' => 0, 'balanceado' => true];
            $asientoArs = round(max((float) $balance['total_debe'], (float) $balance['total_haber']), 2);

            $refAnita = IngresoEgresoAnitaTesmovSupport::referenciaAnitaParaControl($mov);
            $tipoAnita = (string) ($refAnita['tipo'] ?? $tipo);
            $nroAnita = (int) ($refAnita['numero'] ?? $nro);
            $keyComp = IngresoEgresoImputacionDiariaAnitaReader::claveComprobante(
                $empresaAnita,
                $tipoAnita,
                $nroAnita
            );
            $tes = $tesmov[$keyComp] ?? ['ars' => 0.0, 'lineas' => 0, 'encontrado' => false];
            $tesmovArs = round((float) ($tes['ars'] ?? 0), 2);
            $tieneTesmov = ! empty($tes['encontrado']);
            $tienePagoAnita = ! empty($pagosAnita[$keyComp]);

            $nroAsiento = (int) ($asiento?->numeroasiento ?? 0);
            $cta = $ctamov[$empresaAnita.':'.$nroAsiento]
                ?? ['total_debe' => 0.0, 'total_haber' => 0.0, 'lineas_con_importe' => 0, 'balanceado' => true, 'encontrado' => false];
            $tieneCtamov = ! empty($cta['encontrado']);

            $tesoreriaVsAsiento = Ie::esTransferencia($tipo)
                ? round(max($cajaDebe, $cajaHaber) + $chequesArs, 2)
                : round($cajaArs + $chequesArs, 2);

            $eval = Ie::evaluar(
                $cajaArs,
                $chequesArs,
                $asientoArs,
                $tesoreriaVsAsiento,
                $tesmovArs,
                (float) ($cta['total_debe'] ?? 0),
                (float) ($cta['total_haber'] ?? 0),
                (float) ($balance['total_debe'] ?? 0),
                (float) ($balance['total_haber'] ?? 0),
                $tieneAsiento,
                (bool) ($balance['balanceado'] ?? true),
                $tieneTesmov,
                $tieneCtamov,
                $tienePagoAnita,
                $tipo,
                $chequesEmitidos,
                $sinCpromae,
                $sinTesmovChp,
                $tolerancia
            );

            $etiqueta = trim($tipo.' '.$nro);
            if ($tipoAnita !== $tipo || $nroAnita !== $nro) {
                $etiqueta .= ' → Anita '.$tipoAnita.' '.$nroAnita;
            }

            $out[] = [
                'id' => (int) $mov->id,
                'tipo' => $tipo,
                'fecha' => $fecha,
                'empresa_id' => (int) $mov->empresa_id,
                'nombreempresa' => (string) ($mov->empresas?->nombre ?? ''),
                'proveedor_id' => (int) ($mov->proveedor_id ?? 0),
                'nombre_proveedor' => (string) ($mov->proveedores?->nombre ?? ''),
                'numerotransaccion' => $nro,
                'etiqueta' => $etiqueta,
                'anita_tipo' => $tipoAnita,
                'anita_numero' => $nroAnita,
                'detalle' => (string) ($mov->detalle ?? ''),
                'solicitudpago_id' => (int) ($mov->solicitudpago_id ?? 0),
                'asiento_id' => (int) ($asiento?->id ?? 0),
                'numeroasiento' => (string) ($asiento?->numeroasiento ?? ''),
                'caja_ars' => $cajaArs,
                'cheques_ars' => $chequesArs,
                'tesoreria_ars' => round($cajaArs + $chequesArs, 2),
                'asiento_ars' => $asientoArs,
                'tesmov_ars' => $tesmovArs,
                'ctamov_debe' => round((float) ($cta['total_debe'] ?? 0), 2),
                'ctamov_haber' => round((float) ($cta['total_haber'] ?? 0), 2),
                'ok' => $eval['ok'],
                'alertas' => $eval['alertas'],
                'alertas_texto' => implode(' · ', $eval['alertas']),
            ];
        }

        return $out;
    }

    private function asientoDeMovimiento(Caja_Movimiento $mov): ?Asiento
    {
        $asiento = $mov->asientos;
        if ($asiento !== null && (int) ($asiento->id ?? 0) > 0) {
            return $asiento;
        }

        $asientoPago = $mov->pagoproveedores?->asientos;
        if ($asientoPago !== null && (int) ($asientoPago->id ?? 0) > 0) {
            return $asientoPago;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     */
    private function enviarMailSiCorresponde(array &$informe, array $config): void
    {
        $destino = trim((string) ($config['email'] ?? ''));
        if ($destino === '') {
            return;
        }

        $debe = $informe['requiere_alerta']
            || filter_var($config['mail_siempre'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if (! $debe) {
            return;
        }

        try {
            Mail::to($destino)->send(new IngresoEgresoImputacionDiaria($informe));
            $informe['mail_enviado'] = true;
            $informe['mail_destino'] = $destino;
        } catch (Throwable $e) {
            $informe['mail_error'] = $e->getMessage();
            Log::error('IngresoEgresoImputacionDiaria: mail falló', ['error' => $e->getMessage()]);
        }
    }
}
