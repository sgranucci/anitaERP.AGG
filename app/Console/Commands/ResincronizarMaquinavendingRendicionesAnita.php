<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\MaquinavendingRendicionResincronizarAnitaService;
use Illuminate\Console\Command;

class ResincronizarMaquinavendingRendicionesAnita extends Command
{
    protected $signature = 'maquinavending:resincronizar-rendiciones-anita
                            {--empresas= : IDs empresa separados por coma (default: todas con rendiciones)}
                            {--rendicion= : ID maquinavending_rendicion puntual}
                            {--dry-run : Simula sin escribir en Anita}';

    protected $description = 'Re-sincroniza rendiciones vending ERP → Anita (rendgastro, rendvalor, rendmvart)';

    public function handle(MaquinavendingRendicionResincronizarAnitaService $service): int
    {
        $empresasOpt = trim((string) $this->option('empresas'));
        $empresaIds = $empresasOpt !== ''
            ? array_values(array_filter(array_map('intval', explode(',', $empresasOpt))))
            : [];

        $rendicionId = (int) $this->option('rendicion');
        $dryRun = (bool) $this->option('dry-run');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Rendiciones vending → Anita%s%s',
            $dryRun ? ' | MODO SIMULACIÓN' : '',
            $rendicionId > 0 ? ' | rendición #'.$rendicionId : '',
        ));

        if ($empresaIds !== []) {
            $this->line('Empresas: '.implode(', ', $empresaIds));
        }

        if (! filter_var(config('rendicion_maquinavending_anita.incluir_rendg_nro_ticket', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->comment(
                'Nº cierre Ventas → rendg_ult_ticket en rendgastro (rendg_nro_ticket no existe aún en Informix).'
            );
        }

        try {
            $informe = $service->ejecutar(
                $empresaIds,
                $rendicionId > 0 ? $rendicionId : null,
                $dryRun,
                fn (string $msg) => $this->line('  '.$msg),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['Total rendiciones ERP', (string) ($informe['total'] ?? 0)],
                [$dryRun ? 'Simuladas' : 'Actualizadas en Anita', (string) ($informe['actualizadas'] ?? 0)],
                ['Insertadas (faltaba cabecera)', (string) ($informe['insertadas'] ?? 0)],
                ['Errores', (string) count($informe['errores'] ?? [])],
            ],
        );

        foreach ($informe['errores'] ?? [] as $err) {
            $this->error(sprintf(
                'Rendición #%d (emp %d, cierre %d): %s',
                (int) ($err['rendicion_id'] ?? 0),
                (int) ($err['empresa_id'] ?? 0),
                (int) ($err['numero_cierre'] ?? 0),
                (string) ($err['mensaje'] ?? ''),
            ));
        }

        if (($informe['errores'] ?? []) !== []) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Simulación completada (sin cambios en Anita).');
        } else {
            $this->info('Re-sincronización completada.');
        }

        return self::SUCCESS;
    }
}
