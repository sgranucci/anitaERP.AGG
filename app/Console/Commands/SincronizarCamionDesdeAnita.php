<?php

namespace App\Console\Commands;

use App\Repositories\Ventas\CamionRepositoryInterface;
use Illuminate\Console\Command;

class SincronizarCamionDesdeAnita extends Command
{
    protected $signature = 'camion:sincronizar-anita
                            {--dry-run : Informe sin escribir en el ERP}
                            {--ejecutar : Persiste altas y actualizaciones desde Anita}';

    protected $description = 'Sincroniza camiones desde Anita (camion.sql: dominio, habilitación, tipo, acoplado, CUIT chofer, cant. precintos).';

    public function handle(CamionRepositoryInterface $repository): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ejecutar = (bool) $this->option('ejecutar');

        if ($dryRun && $ejecutar) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $ejecutar) {
            $this->warn('Sin --dry-run ni --ejecutar no se escribe. Use --dry-run para ver el impacto.');
            $dryRun = true;
        }

        $ret = $repository->resincronizarDesdeAnita($dryRun, true);

        $this->info('Anita: '.$ret['en_anita'].' | iguales: '.$ret['iguales']);
        $this->info('A crear: '.count($ret['crear']).' | a actualizar: '.count($ret['actualizar']));
        $this->info('Solo ERP (no se borran): '.count($ret['solo_erp']));

        if ($ret['crear'] !== []) {
            $this->line('');
            $this->info('Altas:');
            foreach ($ret['crear'] as $fila) {
                $this->line(sprintf(
                    '  codigo %s · %s · precintos %d · hab. %s',
                    $fila['codigo'],
                    $fila['dominio'] !== '' ? $fila['dominio'] : '(sin dominio)',
                    $fila['cantidad_precinto'],
                    $fila['habilitacion'] !== '' ? $fila['habilitacion'] : '-'
                ));
            }
        }

        if ($ret['actualizar'] !== []) {
            $this->line('');
            $this->info('Actualizaciones (ERP → Anita):');
            foreach ($ret['actualizar'] as $fila) {
                $this->line('  codigo '.$fila['codigo'].' id '.$fila['id']);
                foreach ($fila['diffs'] as $campo => $diff) {
                    $this->line('    '.$campo.': ['.$diff['erp'].'] → ['.$diff['anita'].']');
                }
            }
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se persistió nada. Para grabar: php artisan camion:sincronizar-anita --ejecutar');

            return self::SUCCESS;
        }

        $this->info('Persistido: importados '.$ret['importados'].'; actualizados '.$ret['actualizados'].'.');

        return $ret['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
