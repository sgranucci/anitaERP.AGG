<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Resume tiempos recientes de emisión gastronomía desde storage/logs/laravel.log.
 */
class GastronomiaEmisionTiemposCommand extends Command
{
    protected $signature = 'gastronomia:emision-tiempos
                            {--ultimas=25 : Cantidad de emisiones a listar}
                            {--log= : Ruta al log (default storage/logs/laravel.log)}';

    protected $description = 'Resume tiempos de emisión gastronomía (gastronomia.emision.profile) del log';

    public function handle(): int
    {
        $logPath = (string) ($this->option('log') ?: storage_path('logs/laravel.log'));
        if (! is_readable($logPath)) {
            $this->error('No se puede leer: '.$logPath);

            return self::FAILURE;
        }

        $limite = max(1, (int) $this->option('ultimas'));
        $entradas = $this->leerEntradasProfile($logPath, $limite);

        if ($entradas === []) {
            $this->warn('Sin entradas gastronomia.emision.profile en '.$logPath);
            $this->line('Active GASTRONOMIA_EMISION_PROFILE=true y emita al menos una factura.');

            return self::SUCCESS;
        }

        $totales = array_column($entradas, 'total_ms');
        $promedio = array_sum($totales) / count($totales);
        $maximo = max($totales);
        $minimo = min($totales);
        $umbral = max(0, (int) config('gastronomia.emision_umbral_advertencia_ms', 10000));
        $sobreUmbral = count(array_filter($totales, static fn (float $t): bool => $umbral > 0 && $t >= $umbral));

        $this->info('Emisiones gastronomía · últimas '.count($entradas).' del log');
        $this->line(sprintf(
            'Total servidor: min %.0f ms · prom %.0f ms · max %.0f ms · ≥ umbral %d ms: %d',
            $minimo,
            $promedio,
            $maximo,
            $umbral,
            $sobreUmbral,
        ));
        $this->newLine();

        $filas = [];
        foreach ($entradas as $e) {
            $filas[] = [
                $e['fecha'],
                $e['cuenta_id'],
                number_format($e['total_ms'], 0, ',', '.'),
                $this->etapasDestacadas($e['etapas']),
            ];
        }

        $this->table(['Fecha', 'Cuenta', 'Total ms', 'Etapas más lentas'], $filas);

        return self::SUCCESS;
    }

    /**
     * @return list<array{fecha:string,cuenta_id:int,total_ms:float,etapas:list<array{etapa:string,ms:float,acum_ms:float>}>}>
     */
    private function leerEntradasProfile(string $logPath, int $limite): array
    {
        $handle = fopen($logPath, 'rb');
        if ($handle === false) {
            return [];
        }

        $buffer = '';
        $entradas = [];

        while (! feof($handle)) {
            $chunk = fread($handle, 65536);
            if ($chunk === false) {
                break;
            }
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $linea = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if (! str_contains($linea, 'gastronomia.emision.profile')) {
                    continue;
                }

                $jsonStart = strpos($linea, '{');
                if ($jsonStart === false) {
                    continue;
                }

                $payload = json_decode(substr($linea, $jsonStart), true);
                if (! is_array($payload)) {
                    continue;
                }

                preg_match('/^\[([^\]]+)\]/', $linea, $m);
                $entradas[] = [
                    'fecha' => $m[1] ?? '',
                    'cuenta_id' => (int) ($payload['cuenta_id'] ?? 0),
                    'total_ms' => (float) ($payload['total_ms'] ?? 0),
                    'etapas' => is_array($payload['etapas'] ?? null) ? $payload['etapas'] : [],
                ];
            }
        }

        fclose($handle);

        return array_slice(array_reverse($entradas), 0, $limite);
    }

    /**
     * @param  list<array{etapa:string,ms:float,acum_ms:float}>  $etapas
     */
    private function etapasDestacadas(array $etapas): string
    {
        if ($etapas === []) {
            return '—';
        }

        $copia = $etapas;
        usort($copia, static fn (array $a, array $b): int => ($b['ms'] <=> $a['ms']));
        $partes = [];
        foreach (array_slice($copia, 0, 3) as $e) {
            $partes[] = ($e['etapa'] ?? '?').' '.number_format((float) ($e['ms'] ?? 0), 0, ',', '.').' ms';
        }

        return implode(' · ', $partes);
    }
}
