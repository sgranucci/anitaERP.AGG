<?php

namespace App\Services\Ventas;

use App\Exports\Ventas\ControlAfipPedidosExport;
use App\Mail\Ventas\ControlAfipPedidosResumenMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Ventas\ControlAfipPedidosMailSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class ControlAfipPedidosDistribucionService
{
    public function __construct(
        private readonly ControlAfipPedidosService $controlService,
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fecha = null,
        bool $enviarMail = true,
        bool $dryRun = false,
        bool $enviarVendedores = true,
    ): array {
        $base = [
            'omitido' => false,
            'motivo' => null,
            'dry_run' => $dryRun,
            'informe' => [],
            'destinos_resumen' => [],
            'mail_resumen_enviado' => false,
            'mail_resumen_destino' => null,
            'mail_resumen_error' => null,
            'mails_vendedor_enviados' => 0,
            'mails_vendedor_omitidos_sin_email' => 0,
            'mails_vendedor_errores' => 0,
            'pdf_generado' => false,
            'excel_generado' => false,
        ];

        if (! EntornoEmpresaSupport::esElBierzo()) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Solo aplica en entorno EL BIERZO (EMPRESA='.EntornoEmpresaSupport::codigo().').',
            ]);
        }

        if (! (bool) config('pedido.control_afip_pedidos_mail.habilitado', true)) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Mail control AFIP pedidos deshabilitado (pedido.control_afip_pedidos_mail.habilitado).',
            ]);
        }

        $informe = $this->controlService->generar($fecha);
        $base['informe'] = $informe;

        $pdf = $this->generarPdf($informe);
        $excel = $this->generarExcel($informe);
        $base['pdf_generado'] = $pdf !== '';
        $base['excel_generado'] = $excel !== '';

        $destinos = $this->destinosResumen();
        $base['destinos_resumen'] = $destinos;

        if ($dryRun || ! $enviarMail) {
            $sinEmail = 0;
            foreach ($informe['filas'] ?? [] as $fila) {
                if (trim((string) ($fila['vendedor_email'] ?? '')) === '') {
                    $sinEmail++;
                }
            }
            $base['mails_vendedor_omitidos_sin_email'] = $sinEmail;

            return $base;
        }

        $fechaStamp = str_replace('-', '', (string) ($informe['fecha_control'] ?? date('Ymd')));
        $nombrePdf = 'control_afip_pedidos_'.$fechaStamp.'.pdf';
        $nombreExcel = 'control_afip_pedidos_'.$fechaStamp.'.xlsx';

        if ($destinos === []) {
            $base['omitido'] = true;
            $base['motivo'] = 'Sin destinatarios en Modulo Aviso (ventas.'
                .ControlAfipPedidosMailSupport::CODIGO_AVISO_RESUMEN.') ni fallback.';
        } else {
            try {
                Mail::to($destinos)->send(new ControlAfipPedidosResumenMail(
                    $informe,
                    $pdf,
                    $nombrePdf,
                    $excel,
                    $nombreExcel,
                ));
                $base['mail_resumen_enviado'] = true;
                $base['mail_resumen_destino'] = implode(', ', $destinos);
            } catch (\Throwable $e) {
                Log::error('control_afip_pedidos: fallo mail resumen', ['error' => $e->getMessage()]);
                $base['mail_resumen_error'] = $e->getMessage();
            }
        }

        if ($enviarVendedores) {
            $avisoRetenido = app(ClienteRetenidoAfipAvisoService::class);
            foreach ($informe['filas'] ?? [] as $fila) {
                $clienteId = (int) ($fila['cliente_id'] ?? 0);
                $email = trim((string) ($fila['vendedor_email'] ?? ''));
                if ($clienteId <= 0) {
                    $base['mails_vendedor_errores']++;

                    continue;
                }
                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $base['mails_vendedor_omitidos_sin_email']++;

                    continue;
                }
                try {
                    $motivo = trim((string) ($fila['motivo'] ?? 'Problemas de AFIP / padrón ARCA'));
                    $detalles = $fila['detalles'] ?? [];
                    $detalle = is_array($detalles) ? implode(' ', array_map('strval', $detalles)) : (string) $detalles;
                    $avisoRetenido->despachar($clienteId, [
                        'motivo' => $motivo !== '' ? $motivo : 'Problemas de AFIP / padrón ARCA',
                        'detalle' => $detalle,
                    ]);
                    $base['mails_vendedor_enviados']++;
                } catch (\Throwable $e) {
                    $base['mails_vendedor_errores']++;
                    Log::error('control_afip_pedidos: fallo mail vendedor', [
                        'cliente' => $fila['codigo'] ?? null,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function generarPdf(array $informe): string
    {
        $html = view('ventas.control_afip_pedidos_mail.listado', [
            'informe' => $informe,
            'filas' => $informe['filas'] ?? [],
        ])->render();

        $tmp = storage_path('pdf/listados/control_afip_pedidos_'.uniqid('', true).'.pdf');
        @mkdir(dirname($tmp), 0775, true);
        DompdfListadoSupport::guardarLegalLandscape($html, $tmp, [
            'titulo_corto' => 'Control AFIP pedidos',
        ]);
        $bin = is_file($tmp) ? (string) file_get_contents($tmp) : '';
        @unlink($tmp);

        return $bin;
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function generarExcel(array $informe): string
    {
        try {
            return (string) Excel::raw(
                (new ControlAfipPedidosExport)->parametros($informe),
                \Maatwebsite\Excel\Excel::XLSX
            );
        } catch (\Throwable $e) {
            Log::error('control_afip_pedidos: fallo Excel', ['error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * @return list<string>
     */
    private function destinosResumen(): array
    {
        $tipo = ModuloAvisoTipo::query()
            ->where('modulo', ControlAfipPedidosMailSupport::MODULO)
            ->where('codigo', ControlAfipPedidosMailSupport::CODIGO_AVISO_RESUMEN)
            ->where('activo', true)
            ->first();

        if ($tipo) {
            $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, []);
            $emails = array_values(array_unique(array_filter(array_map('strval', $emails))));
            if ($emails !== []) {
                return $emails;
            }
        }

        $out = [];
        foreach (ControlAfipPedidosMailSupport::EMAILS_RESUMEN_DEFAULT as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }
}
