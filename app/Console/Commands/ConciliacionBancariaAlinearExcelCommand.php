<?php

namespace App\Console\Commands;

use App\Services\Contable\ConciliacionBancariaAlinearExcelService;
use Illuminate\Console\Command;

class ConciliacionBancariaAlinearExcelCommand extends Command
{
    protected $signature = 'contable:conciliacion-bancaria-alinear-excel
                            {--dir= : Directorio con Excels Contaduría}
                            {--archivo= : Un Excel puntual}
                            {--mes=10 : Mes}
                            {--anio=2026 : Año}
                            {--aplicar : Persistir import EXTRACTO, pares y cierre Contaduría (sin esto es dry-run)}';

    protected $description = 'Alinea el ERP a los pendientes del Excel Contaduría (opción A: Contaduría manda)';

    public function handle(ConciliacionBancariaAlinearExcelService $service): int
    {
        // Los Excel Contaduría grandes (ex Itaú) + mayor del mes superan 2G con facilidad.
        ini_set('memory_limit', '6144M');
        set_time_limit(0);

        $mes = max(1, min(12, (int) $this->option('mes')));
        $anio = max(2000, (int) $this->option('anio'));
        $aplicar = (bool) $this->option('aplicar');
        $dir = trim((string) ($this->option('dir') ?: ''));
        $archivo = trim((string) ($this->option('archivo') ?: ''));

        if ($dir === '' && $archivo === '') {
            $this->error('Indicá --dir= o --archivo=');

            return self::FAILURE;
        }

        $this->info(($aplicar ? 'APLICAR' : 'DRY-RUN').sprintf(' · %02d/%d', $mes, $anio));

        try {
            if ($archivo !== '') {
                $resultados = [$service->alinearArchivo($archivo, $mes, $anio, $aplicar)];
            } else {
                // Una cuenta por vez: liberar mayor/Excel entre archivos.
                $resultados = [];
                $files = array_values(array_filter(
                    array_merge(glob(rtrim($dir, '/').'/*.xlsx') ?: [], glob(rtrim($dir, '/').'/*.xls') ?: []),
                    static fn (string $f) => \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaExcelReferenciaSupport::codigoCuentacajaDesdeNombreArchivo($f) !== null,
                ));
                sort($files);
                foreach ($files as $ruta) {
                    $this->line('→ '.basename($ruta));
                    $resultados[] = $service->alinearArchivo($ruta, $mes, $anio, $aplicar);
                    gc_collect_cycles();
                }
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $fallas = 0;
        foreach ($resultados as $r) {
            $this->newLine();
            $titulo = sprintf(
                '%s · empresa %s · cuentacaja %s · %s',
                $r['codigo'] ?? '?',
                $r['empresa_id'] ?? '?',
                $r['cuentacaja_id'] ?? '?',
                $r['archivo'] ?? '',
            );
            if (! empty($r['error'])) {
                $this->warn($titulo);
                $this->warn('  '.$r['error']);
                $fallas++;
                continue;
            }

            (! empty($r['ok']) ? $this->info($titulo.' · carátula OK') : $this->warn($titulo.' · carátula con desvíos'));

            if (! empty($r['import'])) {
                $imp = $r['import'];
                $this->line(sprintf(
                    '  EXTRACTO: %s filas · IB %s%s',
                    $imp['filas'] ?? 0,
                    $imp['account_number'] ?? '—',
                    ! empty($imp['cuenta_interbanking_asignada']) ? ' (asignada)' : '',
                ));
            }

            $this->line(sprintf(
                '  Cierre: %d candidatos · %d persistidos · protegidos C/B %d/%d',
                (int) ($r['pares_cierre_candidatos'] ?? 0),
                (int) ($r['pares_cierre_persistidos'] ?? 0),
                (int) ($r['protegidos_contables'] ?? 0),
                (int) ($r['protegidos_banco'] ?? 0),
            ));
            $this->line(sprintf(
                '  Final: pend.contables %d · pend.banco %d · cheques ERP %d · Excel ch/banco %d/%d · dif %s',
                (int) ($r['pendientes_contables_final'] ?? 0),
                (int) ($r['pendientes_banco_final'] ?? 0),
                (int) ($r['pendientes_cheques_final'] ?? 0),
                (int) ($r['excel_cheques'] ?? 0),
                (int) ($r['excel_banco_pendientes'] ?? 0),
                number_format((float) ($r['diferencia'] ?? 0), 2, ',', '.'),
            ));

            foreach ($r['excel_comparacion']['filas'] ?? [] as $fila) {
                $marca = ! empty($fila['ok']) ? 'OK' : 'Δ';
                $this->line(sprintf(
                    '  [%s] %s · Excel %s · ERP %s · Δ %s',
                    $marca,
                    $fila['concepto'],
                    $fila['excel'] === null ? '—' : number_format((float) $fila['excel'], 2, ',', '.'),
                    $fila['erp'] === null ? '—' : number_format((float) $fila['erp'], 2, ',', '.'),
                    $fila['delta'] === null ? '—' : number_format((float) $fila['delta'], 2, ',', '.'),
                ));
            }
            foreach ($r['avisos'] ?? [] as $aviso) {
                $this->line('  · '.$aviso);
            }

            if (empty($r['ok'])) {
                $fallas++;
            }
        }

        $this->newLine();
        if ($fallas > 0) {
            $this->warn("Listo con {$fallas} cuenta(s) a revisar.");

            return $aplicar ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Todas las cuentas alineadas a carátula Contaduría.');

        return self::SUCCESS;
    }
}
