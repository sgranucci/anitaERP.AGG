<?php

namespace App\Console\Commands;

use App\Services\Stock\DepmaeAnitaSyncService;
use App\Support\Stock\DepmaeAnitaFuentesSupport;
use Illuminate\Console\Command;

class SincronizarDepmaeDesdeAnita extends Command
{
    protected $signature = 'depmae:sincronizar-anita
                            {--empresa= : Sincronizar solo una empresa (modo AGG / sin fuentes por sala)}
                            {--codigo= : Importar solo un depósito por codigo Anita}
                            {--dry-run : Solo cuenta altas/actualizaciones sin persistir}';

    protected $description = 'Importa depmae desde Anita. Crown: cncadmin→CIP y acncadmin→AC. Omite códigos de máquinas (>100000).';

    public function handle(DepmaeAnitaSyncService $sync): int
    {
        $empresaOpt = $this->option('empresa');
        $codigo = trim((string) ($this->option('codigo') ?? ''));
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN: no se persisten cambios.');
        }

        try {
            if ($empresaOpt !== null && $empresaOpt !== '' && $codigo !== '') {
                if ($dryRun) {
                    $this->error('--dry-run no aplica a --codigo; usá sync completo.');

                    return self::FAILURE;
                }
                $empresaId = (int) $empresaOpt;
                $this->info("Importando depósito Anita empresa {$empresaId} codigo {$codigo}…");
                $estado = $sync->traerRegistroDeAnita($empresaId, $codigo);
                $this->info("Resultado: {$estado}");

                return self::SUCCESS;
            }

            if ($empresaOpt !== null && $empresaOpt !== '') {
                if (DepmaeAnitaFuentesSupport::usaFuentesPorSala()) {
                    $this->warn('Crown usa fuentes por sala; se ignora --empresa y se sincronizan las fuentes configuradas.');
                    $ret = $sync->sincronizarConAnita(null, $dryRun);
                } else {
                    $empresaId = (int) $empresaOpt;
                    $this->info("Sincronizando depmae desde Anita para empresa_id={$empresaId}…");
                    if ($dryRun) {
                        $ret = $sync->sincronizarConAnita([$empresaId], true);
                    } else {
                        $ret = $sync->sincronizarEmpresaDesdeAnita($empresaId);
                    }
                }
            } elseif (DepmaeAnitaFuentesSupport::usaFuentesPorSala()) {
                $this->info('Sincronizando depmae Crown (cncadmin→CIP, acncadmin→AC)…');
                $ret = $sync->sincronizarConAnita(null, $dryRun);
            } else {
                $this->info('Sincronizando depmae desde Anita (por empresa)…');
                $ret = $sync->sincronizarConAnita(null, $dryRun);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['En Anita', $ret['en_anita']],
            ['Omitidos (máquinas)', $ret['omitidos_maquina']],
            [$dryRun ? 'A importar' : 'Importados', $ret['importados']],
            [$dryRun ? 'A actualizar' : 'Actualizados', $ret['actualizados']],
            ['Omitidos', $ret['omitidos']],
        ]);

        if (! empty($ret['fuentes']) && is_array($ret['fuentes'])) {
            $filas = [];
            foreach ($ret['fuentes'] as $f) {
                $filas[] = [
                    $f['ifx_server'] ?? '',
                    $f['sala_codigo'] ?? '',
                    $f['en_anita'] ?? 0,
                    $f['importados'] ?? 0,
                    $f['actualizados'] ?? 0,
                    $f['errores'] ?? 0,
                ];
            }
            $this->table(
                ['IFX', 'Sala', 'En Anita', $dryRun ? 'A importar' : 'Importados', $dryRun ? 'A actualizar' : 'Actualizados', 'Errores'],
                $filas
            );
        }

        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
