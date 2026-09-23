<?php

namespace App\Console\Commands;

use App\Repositories\Caja\ChequeRepositoryInterface;
use Illuminate\Console\Command;

class ChequeSincronizarAnitaCommand extends Command
{
    protected $signature = 'cheque:sincronizar-anita
                            {--cht : Solo cheques de terceros (ctermae)}
                            {--chp : Solo cheques propios (cpromae)}
                            {--solo-cartera : CHT solo estados cartera (espacio/N)}
                            {--desde= : Fecha YMD mínima CHP (ej. 20250101); default según CHEQUE_SYNC_ANIOS}
                            {--estados= : Estados CHP Anita separados por coma (ej. "*,A,R"); default abiertos espacio,N}
                            {--dry-run : Solo cuenta filas Anita sin importar}';

    protected $description = 'Importa/actualiza cheques desde Anita (CHT ctermae / CHP cpromae)';

    public function handle(ChequeRepositoryInterface $repo): int
    {
        $cht = (bool) $this->option('cht');
        $chp = (bool) $this->option('chp');
        if (! $cht && ! $chp) {
            $cht = true;
            $chp = true;
        }

        $anios = (int) config('cheque.sync_anios', 5);
        $desdeDefault = \App\Support\Caja\ChequeAnitaSyncSupport::fechaDesdeSyncAnios($anios);
        $desdeChp = $this->resolverDesdeYmd($desdeDefault);
        $estadosChp = $this->resolverEstadosChp();

        if ($this->option('dry-run')) {
            if ($cht) {
                $filas = $this->option('solo-cartera')
                    ? \App\Support\Caja\ChequeAnitaSyncSupport::listarCtermaeEnCartera($desdeDefault)
                    : \App\Support\Caja\ChequeAnitaSyncSupport::listarCtermaeTodos($desdeDefault);
                $this->info('CHT Anita: '.count($filas).' filas desde '.$desdeDefault);
            }
            if ($chp) {
                $n = count(\App\Support\Caja\ChequeAnitaSyncSupport::listarCpromaePorEstados($desdeChp, $estadosChp));
                $this->info('CHP Anita estados ['.$this->formatoEstados($estadosChp)."]: {$n} filas desde {$desdeChp}");
            }

            return self::SUCCESS;
        }

        if ($cht) {
            $this->info('Sincronizando CHT…');
            $antes = \App\Models\Caja\Cheque::query()->where('origen', 'R')->count();
            $repo->sincronizarCtermaeConAnita((bool) $this->option('solo-cartera'));
            $despues = \App\Models\Caja\Cheque::query()->where('origen', 'R')->count();
            $this->info("CHT ERP: {$antes} → {$despues}");
        }

        if ($chp) {
            $this->info('Sincronizando CHP estados ['.$this->formatoEstados($estadosChp)."] desde {$desdeChp}…");
            $antes = \App\Models\Caja\Cheque::query()->where('origen', 'E')->count();
            $stats = $repo->sincronizarCpromaeConAnita($desdeChp, $estadosChp);
            $despues = \App\Models\Caja\Cheque::query()->where('origen', 'E')->count();
            $this->info("CHP Anita leídos: {$stats['leidos']}");
            $this->info("CHP creados: {$stats['creados']} | ya existían: {$stats['existentes']} | omitidos: {$stats['omitidos']}");
            $this->info("CHP ERP: {$antes} → {$despues}");
        }

        return self::SUCCESS;
    }

    private function resolverDesdeYmd(int $default): int
    {
        $raw = trim((string) $this->option('desde'));
        if ($raw === '') {
            return $default;
        }
        $ymd = (int) preg_replace('/\D/', '', $raw);
        if ($ymd < 19000101 || $ymd > 29991231) {
            $this->warn("Fecha --desde inválida ({$raw}), uso default {$default}");

            return $default;
        }

        return $ymd;
    }

    /**
     * @return list<string>
     */
    private function resolverEstadosChp(): array
    {
        $raw = trim((string) $this->option('estados'));
        if ($raw === '') {
            return [' ', 'N'];
        }

        $out = [];
        foreach (explode(',', $raw) as $part) {
            $e = trim($part);
            if ($e === '' || strcasecmp($e, 'espacio') === 0) {
                $e = ' ';
            }
            $out[] = $e;
        }

        return $out !== [] ? $out : [' ', 'N'];
    }

    /**
     * @param  list<string>  $estados
     */
    private function formatoEstados(array $estados): string
    {
        return implode(',', array_map(
            static fn (string $e): string => $e === ' ' ? 'espacio' : $e,
            $estados
        ));
    }
}
