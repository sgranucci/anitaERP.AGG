<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Stock\RecepcionProveedorAsientoAuditoriaDiariaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecepcionProveedorAuditoriaAsientosComCommand extends Command
{
    protected $signature = 'recepcion-proveedor:auditoria-asientos-com
                            {--fecha= : Fecha calendario Y-m-d (default: ayer si no hay rango ni --todas)}
                            {--desde= : Desde Y-m-d inclusive}
                            {--hasta= : Hasta Y-m-d inclusive}
                            {--todas : Auditar todas las COM confirmadas (sin filtro de fecha)}
                            {--empresa= : Filtrar por empresa_id}
                            {--export= : Exportar detalle CSV (ruta absoluta o relativa a storage/app)}
                            {--sin-mail : No envía correo aunque haya discrepancias}';

    protected $description = 'Audita recepción ↔ recepmae Anita ↔ asiento ERP ↔ ctamov Anita';

    public function handle(RecepcionProveedorAsientoAuditoriaDiariaService $service): int
    {
        if (! config('recepcion_proveedor.auditoria_asientos_com_diaria.habilitada', true)) {
            $this->warn('Auditoría deshabilitada (recepcion_proveedor.auditoria_asientos_com_diaria.habilitada).');

            return self::SUCCESS;
        }

        $todas = (bool) $this->option('todas');
        $fechaOpt = trim((string) ($this->option('fecha') ?? ''));
        $desde = trim((string) ($this->option('desde') ?? ''));
        $hasta = trim((string) ($this->option('hasta') ?? ''));
        $empresaOverride = $this->option('empresa') !== null ? (int) $this->option('empresa') : null;
        $enviarMail = ! (bool) $this->option('sin-mail');

        if ($todas && ($fechaOpt !== '' || $desde !== '' || $hasta !== '')) {
            $this->error('Use --todas o filtro de fecha, no ambos.');

            return self::FAILURE;
        }

        $fechaCalendario = null;
        if (! $todas && $desde === '' && $hasta === '') {
            $fechaCalendario = $fechaOpt !== '' ? $fechaOpt : Carbon::yesterday()->toDateString();
        } elseif ($fechaOpt !== '') {
            $fechaCalendario = $fechaOpt;
            $desde = '';
            $hasta = '';
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $alcance = $todas
            ? 'todas las COM confirmadas'
            : ($desde !== '' || $hasta !== ''
                ? 'fecha '.($desde ?: '…').' → '.($hasta ?: '…')
                : 'fecha '.$fechaCalendario);
        $this->line(sprintf(
            'Auditoría recepción ↔ recepmae ↔ ERP ↔ ctamov · %s%s%s',
            $alcance,
            $empresaOverride ? ' · empresa '.$empresaOverride : '',
            $enviarMail ? '' : ' · sin mail',
        ));

        try {
            $informe = $service->ejecutar(
                $fechaCalendario,
                $enviarMail,
                $empresaOverride,
                $desde !== '' ? $desde : null,
                $hasta !== '' ? $hasta : null,
                $todas,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['COM en alcance', (string) ($informe['total_com'] ?? 0)],
                ['OK (recepción = ERP = Anita)', (string) ($informe['ok'] ?? 0)],
                ['Omitidas (sin contabilidad / importe 0)', (string) ($informe['omitidas'] ?? 0)],
                ['Con discrepancia', (string) count($informe['discrepancias'] ?? [])],
                ['Errores de lectura', (string) count($informe['errores_lectura'] ?? [])],
            ],
        );

        $pendientes = array_values(array_filter(
            $informe['filas'] ?? [],
            static fn (array $fila): bool => ($fila['estado'] ?? '') === 'discrepancia',
        ));

        if ($pendientes !== []) {
            $this->newLine();
            $this->warn('Discrepancias (recepción vs ERP vs ctamov):');
            $this->table(
                ['COM', 'Recepción', 'ERP debe', 'Anita debe', 'Problemas'],
                array_map(static function (array $fila): array {
                    $problemas = $fila['problemas'] ?? [];
                    $resumen = is_array($problemas) ? implode('; ', array_slice($problemas, 0, 2)) : '';

                    return [
                        (string) ($fila['com'] ?? ''),
                        isset($fila['total_recepcion']) ? number_format((float) $fila['total_recepcion'], 2, '.', '') : (isset($fila['debe_esperado']) ? number_format((float) $fila['debe_esperado'], 2, '.', '') : '—'),
                        isset($fila['debe_erp']) ? number_format((float) $fila['debe_erp'], 2, '.', '') : '—',
                        isset($fila['debe_anita']) ? number_format((float) $fila['debe_anita'], 2, '.', '') : '—',
                        $resumen !== '' ? $resumen : '—',
                    ];
                }, $pendientes),
            );

            foreach ($pendientes as $fila) {
                if (count($fila['problemas'] ?? []) <= 2) {
                    continue;
                }
                $this->line(sprintf(
                    'COM %s — detalle:',
                    (string) ($fila['com'] ?? ''),
                ));
                foreach ($fila['problemas'] as $problema) {
                    $this->line('  · '.$problema);
                }
            }
        }

        foreach ($informe['errores_lectura'] ?? [] as $error) {
            $this->newLine();
            $this->error(sprintf(
                'COM %d (id %d): %s',
                (int) ($error['com'] ?? 0),
                (int) ($error['recepcion_id'] ?? 0),
                (string) ($error['mensaje'] ?? ''),
            ));
        }

        $exportPath = trim((string) ($this->option('export') ?? ''));
        if ($exportPath !== '') {
            $ruta = self::resolverRutaExport($exportPath);
            self::exportarCsv($ruta, $informe['filas'] ?? []);
            $this->info('Detalle exportado: '.$ruta);
        }

        if (! empty($informe['mail_enviado'])) {
            $this->info('Correo enviado a '.$informe['mail_destino']);
        } elseif (! empty($informe['mail_error'])) {
            $this->error('Fallo al enviar correo: '.$informe['mail_error']);
        }

        return ($informe['requiere_alerta'] ?? false) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private static function exportarCsv(string $ruta, array $filas): void
    {
        $dir = dirname($ruta);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($ruta, 'w');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear el archivo CSV: '.$ruta);
        }

        fputcsv($handle, [
            'com',
            'recepcion_id',
            'estado',
            'total_recepcion',
            'debe_esperado',
            'debe_erp',
            'haber_erp',
            'debe_anita',
            'haber_anita',
            'numero_asiento',
            'problemas',
        ], ';');

        foreach ($filas as $fila) {
            fputcsv($handle, [
                $fila['com'] ?? '',
                $fila['recepcion_id'] ?? '',
                $fila['estado'] ?? '',
                $fila['total_recepcion'] ?? ($fila['debe_esperado'] ?? ''),
                $fila['debe_esperado'] ?? '',
                $fila['debe_erp'] ?? '',
                $fila['haber_erp'] ?? '',
                $fila['debe_anita'] ?? '',
                $fila['haber_anita'] ?? '',
                $fila['numero_asiento'] ?? '',
                implode(' | ', $fila['problemas'] ?? []),
            ], ';');
        }

        fclose($handle);
    }

    private static function resolverRutaExport(string $path): string
    {
        if ($path[0] === '/') {
            return $path;
        }

        return storage_path('app/'.$path);
    }
}
