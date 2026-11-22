<?php

namespace App\Services\Ventas;

use App\Mail\Ventas\KilosPedidosMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Ventas\KilosPedidosMailPerfiles;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class KilosPedidosMailDistribucionService
{
    public const MODULO = 'ventas';

    public function __construct(
        private readonly KilosPedidosMailMergeService $mergeService,
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        string $perfilClave,
        ?string $fecha = null,
        bool $enviarMail = true,
        bool $dryRun = false,
        ?Carbon $ahora = null,
    ): array {
        $base = [
            'omitido' => false,
            'motivo' => null,
            'perfil' => $perfilClave,
            'fecha' => null,
            'filtros' => [],
            'cuadran' => false,
            'diffs' => 0,
            'totales_erp' => [],
            'totales_anita' => [],
            'totales_merge' => [],
            'destinos' => [],
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

        if (! (bool) config('pedido.kilos_pedidos_mail.habilitado', true)) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Mail kilos pedidos deshabilitado (pedido.kilos_pedidos_mail.habilitado).',
            ]);
        }

        $perfil = KilosPedidosMailPerfiles::get($perfilClave);
        if ($perfil === null) {
            throw new RuntimeException('Perfil de kilos pedidos desconocido: '.$perfilClave);
        }

        $filtros = KilosPedidosMailPerfiles::filtrosPara($perfil, $ahora, $fecha);
        $informe = $this->mergeService->generar($filtros);

        $base['fecha'] = $filtros['fecha_desde'];
        $base['filtros'] = $filtros;
        $base['cuadran'] = (bool) ($informe['cuadran'] ?? false);
        $base['diffs'] = count($informe['diffs'] ?? []);
        $base['totales_erp'] = $informe['totales_erp'] ?? [];
        $base['totales_anita'] = $informe['totales_anita'] ?? [];
        $base['totales_merge'] = $informe['totales_merge'] ?? [];
        $base['anita_ok'] = (bool) ($informe['anita_ok'] ?? true);
        $base['anita_error'] = $informe['anita_error'] ?? null;

        $pdfBinario = $this->generarPdf($informe, $perfil);
        $base['pdf_generado'] = $pdfBinario !== '';
        $nombrePdf = sprintf(
            'kilos_pedidos_%s_%s.pdf',
            $perfilClave,
            str_replace('-', '', $filtros['fecha_desde'])
        );

        $destinos = $this->destinos($perfil['codigo_aviso'], $perfil['emails_default']);
        $base['destinos'] = $destinos;

        if ($dryRun || ! $enviarMail) {
            return $base;
        }

        if ($destinos === []) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Sin destinatarios en Modulo Aviso (ventas.'.$perfil['codigo_aviso'].') ni fallback.',
            ]);
        }

        try {
            Mail::to($destinos)->send(new KilosPedidosMail(
                perfilNombre: (string) $perfil['nombre'],
                fechaEntrega: (string) $filtros['fecha_desde'],
                repartoTexto: $this->textoReparto($filtros),
                totalesMerge: $informe['totales_merge'],
                pdfContenido: $pdfBinario,
                nombreArchivoPdf: $nombrePdf,
            ));
            $base['mail_enviado'] = true;
            $base['mail_destino'] = implode(', ', $destinos);
        } catch (\Throwable $e) {
            Log::error('ventas:distribuir-kilos-pedidos-mail falló', [
                'perfil' => $perfilClave,
                'fecha' => $filtros['fecha_desde'],
                'error' => $e->getMessage(),
            ]);
            $base['mail_error'] = $e->getMessage();
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $perfil
     */
    private function generarPdf(array $informe, array $perfil): string
    {
        $filtros = $informe['filtros'] ?? [];
        $html = view('ventas.kilos_pedidos_mail.listado', [
            'filas' => $informe['filas_merge'] ?? [],
            'filtros' => $filtros,
            'perfilNombre' => $perfil['nombre'] ?? 'Kilos pedidos',
        ])->render();

        $tmp = storage_path('pdf/listados/kilos_pedidos_mail_'.uniqid('', true).'.pdf');
        @mkdir(dirname($tmp), 0775, true);
        DompdfListadoSupport::guardarLegalLandscape($html, $tmp, [
            'titulo_corto' => 'Kilos pedidos',
        ]);
        $bin = is_file($tmp) ? (string) file_get_contents($tmp) : '';
        @unlink($tmp);

        return $bin;
    }

    /**
     * @param  list<string>  $fallback
     * @return list<string>
     */
    private function destinos(string $codigoAviso, array $fallback): array
    {
        $tipo = ModuloAvisoTipo::query()
            ->where('modulo', self::MODULO)
            ->where('codigo', $codigoAviso)
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
        foreach ($fallback as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function textoReparto(array $filtros): string
    {
        $desde = (string) ($filtros['reparto_desde'] ?? '');
        $hasta = (string) ($filtros['reparto_hasta'] ?? '');
        if ($desde === '' && $hasta === '') {
            return 'todos';
        }
        if ($hasta === '' || $hasta === '999999') {
            return 'desde '.($desde !== '' ? $desde : '1').' (abierto)';
        }

        return $desde.' → '.$hasta;
    }
}
