<?php

namespace App\Console\Commands\Tesoreria;

use App\Support\Tesoreria\PosicionBancaria\PosicionBancariaChequeMacroAlinearSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AlinearPosicionBancariaChequesMacroCommand extends Command
{
    protected $signature = 'tesoreria:alinear-posicion-cheques-macro
        {excel : Ruta al Excel Posición Bancos (cartera operativa Lucas)}
        {--fecha= : Fecha de posición YYYY-MM-DD (default: hoy)}
        {--dry-run : Solo analiza (default si no se pasa --ejecutar)}
        {--ejecutar : Persiste altas/bajas Macro en posicion_bancaria_cheque}';

    protected $description = 'Alinea solo MACRO en posicion_bancaria_cheque a la cartera del Excel operativo. Dry-run por defecto.';

    public function handle(PosicionBancariaChequeMacroAlinearSupport $alinear): int
    {
        $ruta = (string) $this->argument('excel');
        $ejecutar = (bool) $this->option('ejecutar');
        if ($this->option('dry-run') && $ejecutar) {
            $this->error('No combinar --dry-run con --ejecutar.');

            return self::FAILURE;
        }
        // Sin --ejecutar siempre es análisis.
        $ejecutar = $ejecutar && ! $this->option('dry-run');

        $fecha = $this->option('fecha')
            ? Carbon::parse((string) $this->option('fecha'))->startOfDay()
            : Carbon::today();

        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN').' Macro ← '.$ruta.' @ '.$fecha->toDateString());

        $r = $alinear->alinear($ruta, $fecha, $ejecutar);

        $this->table(
            ['abiertos_excel', 'importe_excel', 'insertar', 'actualizar', 'desactivar', 'importe_bajas'],
            [[
                $r['abiertos_excel'],
                number_format($r['importe_excel'], 2, ',', '.'),
                $r['a_insertar'],
                $r['a_actualizar'],
                $r['a_desactivar'],
                number_format($r['importe_desactivar'], 2, ',', '.'),
            ]],
        );

        $this->info('Por empresa (1=BSA 2=KSA 3=RSA):');
        foreach ($r['por_empresa'] as $empId => $vals) {
            $this->line(sprintf(
                '  e%s abiertos=%d $%s | +%d ~%d -%d',
                $empId,
                $vals['abiertos'],
                number_format($vals['importe'], 2, ',', '.'),
                $vals['insertar'],
                $vals['actualizar'],
                $vals['desactivar'],
            ));
        }

        $this->info('Aging Macro ANTES (activos actuales):');
        $this->lineAging($r['aging_antes']);
        $this->info('Aging Macro DESPUÉS (simulado / post-ejecución):');
        $this->lineAging($r['aging_despues_sim']);

        if ($r['muestras_insertar'] !== []) {
            $this->info('Muestra altas:');
            foreach ($r['muestras_insertar'] as $m) {
                $this->line(sprintf(
                    '  + e%s #%s vto=%s $%s %s',
                    $m['empresa_id'],
                    $m['numero'],
                    $m['vto'] ?? '-',
                    number_format((float) $m['importe'], 2, ',', '.'),
                    $m['detalle'],
                ));
            }
        }
        if ($r['muestras_desactivar'] !== []) {
            $this->info('Muestra bajas:');
            foreach ($r['muestras_desactivar'] as $m) {
                $this->line(sprintf(
                    '  - e%s #%s tip=%s origen=%s vto=%s $%s %s',
                    $m['empresa_id'],
                    $m['numero'],
                    $m['tip'],
                    $m['origen'],
                    $m['vto'] ?? '-',
                    number_format((float) $m['importe'], 2, ',', '.'),
                    $m['detalle'],
                ));
            }
        }

        if (! $ejecutar) {
            $this->warn('Nada persistido. Para aplicar: mismo comando con --ejecutar');
        } else {
            $this->info('Macro alineado en posicion_bancaria_cheque.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $aging
     */
    private function lineAging(array $aging): void
    {
        $macro = $aging['por_banco']['MACRO'] ?? ['retenidos' => 0, 'transito' => 0, 'diferidos' => 0, 'total' => 0];
        $this->line(sprintf(
            '  MACRO ret=$%s tr=$%s dif=$%s tot=$%s',
            number_format((float) $macro['retenidos'], 2, ',', '.'),
            number_format((float) $macro['transito'], 2, ',', '.'),
            number_format((float) $macro['diferidos'], 2, ',', '.'),
            number_format((float) $macro['total'], 2, ',', '.'),
        ));
        foreach ($aging['por_empresa'] ?? [] as $empId => $vals) {
            $this->line(sprintf(
                '  e%s tot=$%s (ret=$%s tr=$%s dif=$%s)',
                $empId,
                number_format((float) $vals['total'], 2, ',', '.'),
                number_format((float) $vals['retenidos'], 2, ',', '.'),
                number_format((float) $vals['transito'], 2, ',', '.'),
                number_format((float) $vals['diferidos'], 2, ',', '.'),
            ));
        }
    }
}
