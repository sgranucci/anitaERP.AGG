<?php

namespace App\Console\Commands;

use App\Repositories\Contable\CuentacontableRepositoryInterface;
use Illuminate\Console\Command;

class SincronizarCuentacontableConceptosDesdeAnita extends Command
{
    protected $signature = 'cuentacontable:sincronizar-conceptos-anita
                            {--dry-run : Solo informar diferencias, no grabar}
                            {--con-cuentas-faltantes : También importar cuentas de ctamae que aún no existen en ERP}
                            {--empresas= : Códigos Anita de empresa separados por coma (ej. 1,2,3). Aplica a ctamae/ctaconc}';

    protected $description = 'Resincroniza cuentacontable.conceptogasto_id desde Anita ctaconc (ctaco_concepto). Actualiza cuentas existentes.';

    public function handle(CuentacontableRepositoryInterface $cuentacontableRepository): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $empresas = $this->parseEmpresas($this->option('empresas'));

        try {
            if ($this->option('con-cuentas-faltantes')) {
                $filtro = $empresas ? 'empresas '.implode(',', $empresas) : 'todas las empresas';
                $this->info("Importando cuentas contables faltantes desde Anita (ctamae, {$filtro})…");
                $retCuentas = $cuentacontableRepository->sincronizarConAnita($empresas);
                $this->info(
                    "ctamae: {$retCuentas['en_anita']}; importadas: {$retCuentas['importados']}; "
                    ."omitidas: {$retCuentas['omitidos']}."
                );
                foreach ($retCuentas['errores'] as $err) {
                    $this->warn($err);
                }
            }

            $this->info(
                ($dryRun ? '[dry-run] ' : '')
                .'Sincronizando conceptos de gasto desde Anita (ctaconc)…'
            );
            $ret = $cuentacontableRepository->sincronizarConceptosDesdeAnita($dryRun, $empresas);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; "
            .($dryRun ? 'pendientes de actualizar' : 'actualizados').": {$ret['actualizados']}; "
            ."iguales: {$ret['iguales']}; "
            ."sin cuenta ERP: {$ret['sin_cuenta']}; "
            ."sin concepto maestro: {$ret['sin_concepto']}."
        );

        if ($ret['en_anita'] === 0) {
            $this->warn('Anita no devolvió registros en ctaconc. Revise ANITA_* y el bridge contab.');
        }

        $erroresMostrar = array_slice($ret['errores'], 0, 30);
        foreach ($erroresMostrar as $err) {
            $this->warn($err);
        }
        if (count($ret['errores']) > 30) {
            $this->warn('… y '.(count($ret['errores']) - 30).' errores más.');
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
