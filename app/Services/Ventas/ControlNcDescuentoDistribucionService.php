<?php

namespace App\Services\Ventas;

use App\Mail\Ventas\ControlNcDescuentoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Ventas\ControlNcDescuentoMailSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ControlNcDescuentoDistribucionService
{
    public function __construct(
        private readonly ControlNcDescuentoMergeService $mergeService,
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    /**
     * @param  list<string>  $ccEmails
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fecha = null,
        bool $enviarMail = true,
        bool $dryRun = false,
        array $ccEmails = [],
    ): array {
        $base = [
            'omitido' => false,
            'motivo' => null,
            'fecha' => null,
            'cuadran' => false,
            'diffs' => 0,
            'totales' => [],
            'destinos' => [],
            'cc' => [],
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
            'pdf_generado' => false,
            'anita_ok' => true,
            'anita_error' => null,
            'dry_run' => $dryRun,
        ];

        if (! EntornoEmpresaSupport::esElBierzo()) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Solo aplica en entorno EL BIERZO (EMPRESA='.EntornoEmpresaSupport::codigo().').',
            ]);
        }

        if (! (bool) config('pedido.control_nc_descuento_mail.habilitado', true)) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Mail control NC descuento deshabilitado (pedido.control_nc_descuento_mail.habilitado).',
            ]);
        }

        $informe = $this->mergeService->generar($fecha);
        $base['fecha'] = $informe['fecha'] ?? $fecha;
        $base['cuadran'] = (bool) ($informe['cuadran'] ?? false);
        $base['diffs'] = count($informe['diffs'] ?? []);
        $base['totales'] = $informe['totales'] ?? [];
        $base['anita_ok'] = (bool) ($informe['anita_ok'] ?? true);
        $base['anita_error'] = $informe['anita_error'] ?? null;

        $pdfBinario = $this->generarPdf($informe);
        $base['pdf_generado'] = $pdfBinario !== '';
        $nombrePdf = 'control_nc_descuento_'.str_replace('-', '', (string) ($informe['fecha'] ?? date('Ymd'))).'.pdf';

        $destinos = $this->destinos();
        $base['destinos'] = $destinos;
        $cc = $this->normalizarEmails($ccEmails);
        $base['cc'] = $cc;

        if ($dryRun || ! $enviarMail) {
            return $base;
        }

        if ($destinos === []) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Sin destinatarios en Modulo Aviso (ventas.'
                    .ControlNcDescuentoMailSupport::CODIGO_AVISO.') ni fallback.',
            ]);
        }

        try {
            $pending = Mail::to($destinos);
            if ($cc !== []) {
                $pending->cc($cc);
            }
            $pending->send(new ControlNcDescuentoMail(
                fecha: (string) ($informe['fecha'] ?? ''),
                totales: $informe['totales'] ?? [],
                pdfContenido: $pdfBinario,
                nombreArchivoPdf: $nombrePdf,
            ));
            $base['mail_enviado'] = true;
            $base['mail_destino'] = implode(', ', $destinos)
                .($cc !== [] ? ' (cc: '.implode(', ', $cc).')' : '');
        } catch (\Throwable $e) {
            Log::error('ventas:distribuir-control-nc-descuento-mail falló', [
                'fecha' => $informe['fecha'] ?? null,
                'error' => $e->getMessage(),
            ]);
            $base['mail_error'] = $e->getMessage();
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function generarPdf(array $informe): string
    {
        $html = view('ventas.control_nc_descuento_mail.listado', [
            'fecha' => $informe['fecha'] ?? '',
            'seccionLista' => $informe['seccion_lista'] ?? [],
            'seccionGeneral' => $informe['seccion_general'] ?? [],
            'totales' => $informe['totales'] ?? [],
        ])->render();

        $tmp = storage_path('pdf/listados/control_nc_descuento_'.uniqid('', true).'.pdf');
        @mkdir(dirname($tmp), 0775, true);
        DompdfListadoSupport::guardarLegalLandscape($html, $tmp, [
            'titulo_corto' => 'Control NC dto',
        ]);
        $bin = is_file($tmp) ? (string) file_get_contents($tmp) : '';
        @unlink($tmp);

        return $bin;
    }

    /**
     * @return list<string>
     */
    private function destinos(): array
    {
        $tipo = ModuloAvisoTipo::query()
            ->where('modulo', ControlNcDescuentoMailSupport::MODULO)
            ->where('codigo', ControlNcDescuentoMailSupport::CODIGO_AVISO)
            ->where('activo', true)
            ->first();

        if ($tipo) {
            $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, []);
            $emails = array_values(array_unique(array_filter(array_map('strval', $emails))));
            if ($emails !== []) {
                return $emails;
            }
        }

        return $this->normalizarEmails(ControlNcDescuentoMailSupport::EMAILS_DEFAULT);
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    private function normalizarEmails(array $emails): array
    {
        $out = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }
}
