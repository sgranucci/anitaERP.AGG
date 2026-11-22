<?php

namespace App\Services\Stock;

use App\Mail\Stock\StkmovIntMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Stock\StkmovIntMailSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StkmovIntMailDistribucionService
{
    public function __construct(
        private readonly StkmovIntMailMergeService $mergeService,
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

        if (! (bool) config('stock.stkmov_int_mail.habilitado', true)) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Mail stkmov int deshabilitado (stock.stkmov_int_mail.habilitado).',
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
        $nombrePdf = 'stkmov_int_'.str_replace('-', '', (string) ($informe['fecha'] ?? date('Ymd'))).'.pdf';

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
                'motivo' => 'Sin destinatarios en Modulo Aviso (stock.'
                    .StkmovIntMailSupport::CODIGO_AVISO.') ni fallback.',
            ]);
        }

        try {
            $pending = Mail::to($destinos);
            if ($cc !== []) {
                $pending->cc($cc);
            }
            $pending->send(new StkmovIntMail(
                fecha: (string) ($informe['fecha'] ?? ''),
                totales: $informe['totales'] ?? [],
                pdfContenido: $pdfBinario,
                nombreArchivoPdf: $nombrePdf,
            ));
            $base['mail_enviado'] = true;
            $base['mail_destino'] = implode(', ', $destinos)
                .($cc !== [] ? ' (cc: '.implode(', ', $cc).')' : '');
        } catch (\Throwable $e) {
            Log::error('stock:distribuir-stkmov-int-mail falló', [
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
        $html = view('stock.stkmov_int_mail.listado', [
            'fecha' => $informe['fecha'] ?? '',
            'filas' => $informe['filas_merge'] ?? [],
            'totales' => $informe['totales'] ?? [],
        ])->render();

        $tmp = storage_path('pdf/listados/stkmov_int_'.uniqid('', true).'.pdf');
        @mkdir(dirname($tmp), 0775, true);
        DompdfListadoSupport::guardarLegalLandscape($html, $tmp, [
            'titulo_corto' => 'Stkmov int',
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
            ->where('modulo', StkmovIntMailSupport::MODULO)
            ->where('codigo', StkmovIntMailSupport::CODIGO_AVISO)
            ->where('activo', true)
            ->first();

        if ($tipo) {
            $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, []);
            $emails = array_values(array_unique(array_filter(array_map('strval', $emails))));
            if ($emails !== []) {
                return $this->normalizarEmails($emails);
            }
        }

        return $this->normalizarEmails(StkmovIntMailSupport::EMAILS_DEFAULT);
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
