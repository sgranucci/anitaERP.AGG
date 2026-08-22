<?php

namespace App\Services\Compras;

use App\Mail\Compras\ComprobanteProveedorMayorCcConciliacionDiaria;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Configuracion\Empresa;
use App\Support\Compras\ProveedorCuentaContableMonedaSupport;
use App\Support\Contable\Anita\AnitaMayorAnaliticoSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Informe diario: mayor Anita proveedores MN/ME (subdiario+ctamov) vs CC ERP (solo facturas).
 */
final class ComprobanteProveedorMayorCcConciliacionDiariaService
{
    public function __construct(
        private readonly AnitaMayorAnaliticoSupport $mayorAnita,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $enviarMail = true,
    ): array {
        $config = config('comprobante_proveedor_anita.conciliacion_mayor_cc', []);
        $ventana = max(1, (int) ($config['ventana_dias'] ?? 30));
        $desde = $fechaDesde ?: Carbon::today()->subDays($ventana - 1)->toDateString();
        $hasta = $fechaHasta ?: Carbon::today()->toDateString();
        $tolerancia = (float) ($config['tolerancia'] ?? 1.0);

        $cuentaMn = (int) ($config['cuenta_mn'] ?? 211010001);
        $cuentaMe = (int) ($config['cuenta_me'] ?? 211010011);
        $tiposFactura = array_values(array_filter(array_map(
            static fn ($t) => strtoupper(trim((string) $t)),
            (array) ($config['tipos_factura_subdiario'] ?? [])
        )));

        $empresaId = (int) ($config['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            $empresaId = (int) (Empresa::query()->orderBy('id')->value('id') ?? 0);
        }
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);

        $informe = [
            'fecha_calendario' => $desde.' → '.$hasta,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'empresa_id' => $empresaId,
            'empresa_anita' => $empresaAnita,
            'cuenta_mn' => $cuentaMn,
            'cuenta_me' => $cuentaMe,
            'tolerancia' => $tolerancia,
            'mayor_mn' => 0.0,
            'mayor_me' => 0.0,
            'cc_mn' => 0.0,
            'cc_me' => 0.0,
            'cc_me_moneda_origen' => 0.0,
            'diferencia_mn' => 0.0,
            'diferencia_me' => 0.0,
            'movimientos_mayor_mn' => 0,
            'movimientos_mayor_me' => 0,
            'facturas_cc_mn' => 0,
            'facturas_cc_me' => 0,
            'requiere_alerta' => false,
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
            'notas' => [
                'Mayor Anita = subdiario + ctamov (neto Haber−Debe) en cuentas AP MN/ME.',
                'Cuenta corriente = solo facturas contabilizadas en ERP (importe en $; ME × cotización).',
                'Por ahora el alcance es facturas; no incluye pagos/OP ni otros comprobantes de CC.',
            ],
            'errores' => [],
        ];

        if ($empresaAnita <= 0) {
            $informe['errores'][] = 'No se pudo resolver código empresa Anita.';
            $informe['requiere_alerta'] = true;
            if ($enviarMail) {
                $this->enviarMailSiCorresponde($informe, $config, true);
            }

            return $informe;
        }

        try {
            $ymdDesde = (int) str_replace('-', '', $desde);
            $ymdHasta = (int) str_replace('-', '', $hasta);
            $movs = $this->mayorAnita->listarMovimientosPeriodo(
                $empresaAnita,
                $ymdDesde,
                $ymdHasta,
                [$cuentaMn, $cuentaMe],
            );

            foreach ($movs as $mov) {
                $cuenta = (int) ($mov['cuenta_codigo'] ?? 0);
                $neto = (float) ($mov['neto_haber'] ?? 0);
                $origen = (string) ($mov['origen'] ?? '');
                $tipoSub = strtoupper(trim((string) ($mov['subd_tipo'] ?? '')));

                // Si hay lista de tipos: filtrar solo subdiario de esos tipos; ctamov se incluye siempre.
                if ($tiposFactura !== [] && $origen === 'anita_subdiario') {
                    if ($tipoSub === '' || ! in_array($tipoSub, $tiposFactura, true)) {
                        continue;
                    }
                }

                if ($cuenta === $cuentaMn) {
                    $informe['mayor_mn'] += $neto;
                    $informe['movimientos_mayor_mn']++;
                } elseif ($cuenta === $cuentaMe) {
                    $informe['mayor_me'] += $neto;
                    $informe['movimientos_mayor_me']++;
                }
            }
        } catch (Throwable $e) {
            $informe['errores'][] = 'Mayor Anita: '.$e->getMessage();
            Log::error('ComprobanteProveedorMayorCc: mayor Anita falló', ['error' => $e->getMessage()]);
        }

        try {
            $query = Proveedor_Cuentacorriente::query()
                ->whereNotNull('comprobante_proveedor_id')
                ->whereDate('fecha', '>=', $desde)
                ->whereDate('fecha', '<=', $hasta);

            if ($empresaId > 0) {
                $query->where('empresa_id', $empresaId);
            }

            $filasCc = $query->get(['id', 'total', 'moneda_id', 'cotizacion', 'comprobante_proveedor_id']);

            foreach ($filasCc as $fila) {
                $total = (float) ($fila->total ?? 0);
                $monedaId = (int) ($fila->moneda_id ?? 1);
                $cotizacion = (float) ($fila->cotizacion ?? 0);
                if ($cotizacion <= 0) {
                    $cotizacion = 1.0;
                }

                if (! ProveedorCuentaContableMonedaSupport::esMonedaExtranjera($monedaId)) {
                    $informe['cc_mn'] += $total;
                    $informe['facturas_cc_mn']++;
                } else {
                    $informe['cc_me_moneda_origen'] += $total;
                    $informe['cc_me'] += $total * $cotizacion;
                    $informe['facturas_cc_me']++;
                }
            }
        } catch (Throwable $e) {
            $informe['errores'][] = 'Cuenta corriente: '.$e->getMessage();
            Log::error('ComprobanteProveedorMayorCc: CC falló', ['error' => $e->getMessage()]);
        }

        $informe['mayor_mn'] = round((float) $informe['mayor_mn'], 2);
        $informe['mayor_me'] = round((float) $informe['mayor_me'], 2);
        $informe['cc_mn'] = round((float) $informe['cc_mn'], 2);
        $informe['cc_me'] = round((float) $informe['cc_me'], 2);
        $informe['cc_me_moneda_origen'] = round((float) $informe['cc_me_moneda_origen'], 2);
        $informe['diferencia_mn'] = round($informe['mayor_mn'] - $informe['cc_mn'], 2);
        $informe['diferencia_me'] = round($informe['mayor_me'] - $informe['cc_me'], 2);

        $informe['requiere_alerta'] = $informe['errores'] !== []
            || abs($informe['diferencia_mn']) > $tolerancia
            || abs($informe['diferencia_me']) > $tolerancia;

        if ($enviarMail) {
            $this->enviarMailSiCorresponde($informe, $config, false);
        }

        return $informe;
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     */
    private function enviarMailSiCorresponde(array &$informe, array $config, bool $forzar): void
    {
        $destino = trim((string) ($config['email'] ?? ''));
        if ($destino === '') {
            return;
        }

        $debe = $forzar
            || $informe['requiere_alerta']
            || filter_var($config['mail_siempre'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (! $debe) {
            return;
        }

        try {
            Mail::to($destino)->send(new ComprobanteProveedorMayorCcConciliacionDiaria($informe));
            $informe['mail_enviado'] = true;
            $informe['mail_destino'] = $destino;
        } catch (Throwable $e) {
            $informe['mail_error'] = $e->getMessage();
            Log::error('ComprobanteProveedorMayorCc: mail falló', ['error' => $e->getMessage()]);
        }
    }
}
