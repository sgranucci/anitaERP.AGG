<?php

namespace App\Console\Commands;

use App\Repositories\Contable\CuentacontableRepositoryInterface;
use Illuminate\Console\Command;

class SincronizarCuentacontableTipocuentaDesdeAnita extends Command
{
    protected $signature = 'cuentacontable:sincronizar-tipocuenta-anita
                            {--dry-run : Solo informar diferencias, no grabar}
                            {--ejecutar : Persistir tipocuenta desde ctamae}
                            {--empresas= : Códigos Anita de empresa separados por coma (ej. 1,2,3)}';

    protected $description = 'Alinea cuentacontable.tipocuenta con Anita ctamae.ctam_tipo (mapeo por entorno Ferli/resto).';

    public function handle(CuentacontableRepositoryInterface $cuentacontableRepository): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = (bool) $this->option('dry-run') || ! $ejecutar;
        if ($this->option('dry-run') && $ejecutar) {
            $this->error('Use --dry-run o --ejecutar, no ambos.');

            return self::FAILURE;
        }

        $empresas = $this->parseEmpresas($this->option('empresas'));
        $filtro = $empresas ? 'empresas '.implode(',', $empresas) : 'todas las empresas';

        $this->info(
            ($dryRun ? '[dry-run] ' : '')
            ."Sincronizando tipocuenta desde Anita ctamae ({$filtro})…"
        );

        try {
            $ret = $cuentacontableRepository->sincronizarTipocuentaDesdeAnita($dryRun, $empresas);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; "
            .($dryRun ? 'pendientes de actualizar' : 'actualizados').": {$ret['actualizados']}; "
            ."iguales: {$ret['iguales']}; "
            ."sin cuenta ERP: {$ret['sin_cuenta']}."
        );

        if ($ret['en_anita'] === 0) {
            $this->warn('Anita no devolvió registros en ctamae. Revise ANITA_* y el bridge contab.');
        }

        foreach (array_slice($ret['errores'], 0, 30) as $err) {
            $this->warn($err);
        }
        if (count($ret['errores']) > 30) {
            $this->warn('… y '.(count($ret['errores']) - 30).' errores más.');
        }

        if ($dryRun && $ret['actualizados'] > 0) {
            $this->comment('Para persistir: php artisan cuentacontable:sincronizar-tipocuenta-anita --ejecutar');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>|null
     */
    private function parseEmpresas(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $partes = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($v) => $v !== ''));

        return $partes === [] ? null : $partes;
    }
}
