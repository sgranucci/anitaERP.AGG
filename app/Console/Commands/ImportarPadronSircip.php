<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Configuracion\PadronSircipCargaService;
use App\Support\Configuracion\Sircip\SircipVigenciaSupport;
use Illuminate\Console\Command;
use Throwable;

/**
 * Padrón SIRCIP mensual (CSV UTF-8, un archivo para todas las jurisdicciones).
 *
 * Reemplaza el período completo (los CUIT que ya no vienen se borran, en la
 * misma transacción). Sin --ejecutar solo analiza y muestra el impacto. Escribir en padron_sircip
 * requiere --ejecutar explícito, después de revisar el dry-run con el operador.
 */
class ImportarPadronSircip extends Command
{
    protected $signature = 'padron-sircip:import
                            {archivo : Ruta al ZIP del portal o al TXT/CSV del padrón SIRCIP}
                            {--ejecutar : Persistir en padron_sircip (sin esto solo analiza)}';

    protected $description = 'Analiza (y opcionalmente carga) el padrón único SIRCIP';

    public function handle(PadronSircipCargaService $servicio): int
    {
        $archivo = (string) $this->argument('archivo');
        $ejecutar = (bool) $this->option('ejecutar');

        try {
            $resumen = $servicio->analizar($archivo);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->mostrarResumen($resumen);
        $this->mostrarImpactoSobrePeriodo($resumen);

        if (! $ejecutar) {
            $this->warn('Dry-run: no se escribió nada. Para cargar, repetir con --ejecutar tras la autorización.');

            return self::SUCCESS;
        }

        try {
            $cargado = $servicio->cargar($archivo);
        } catch (Throwable $e) {
            $this->error('Carga fallida: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Período %s: filas persistidas %d, filas del período que ya no vienen y se borraron %d (%.1f s).',
            $cargado['periodo'],
            $cargado['filas_persistidas'],
            $cargado['filas_borradas'],
            $cargado['segundos'],
        ));

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $r */
    private function mostrarImpactoSobrePeriodo(array $r): void
    {
        $this->newLine();
        foreach ($r['periodos'] as $periodo) {
            $actuales = SircipVigenciaSupport::periodosCargados()[(string) $periodo] ?? 0;
            $this->line(sprintf(
                'Período %s: hoy hay %s fila(s) cargadas; el archivo trae %s CUIT. %s',
                $periodo,
                number_format($actuales, 0, ',', '.'),
                number_format((int) $r['cuits_distintos'], 0, ',', '.'),
                $actuales === 0 ? 'Es un período nuevo.' : 'Se REEMPLAZA el período completo.'
            ));
        }
    }

    /** @param array<string, mixed> $r */
    private function mostrarResumen(array $r): void
    {
        $this->line('Archivo: '.$r['archivo']);
        $this->line('Períodos: '.implode(', ', $r['periodos']));
        $this->line(sprintf(
            'Filas leídas: %d | válidas: %d | inválidas: %d',
            $r['filas_leidas'],
            $r['filas_validas'],
            $r['filas_invalidas'],
        ));
        $this->line(sprintf('CUIT distintos: %d | duplicados: %d', $r['cuits_distintos'], $r['cuits_duplicados']));

        $this->newLine();
        $this->line('Letras de alícuota:');
        foreach ($r['letras'] as $letra => $n) {
            $this->line("  {$letra}: {$n}");
        }

        $this->newLine();
        $this->line('Jurisdicciones adheridas (según campo 7):');
        foreach ($r['jurisdicciones_adheridas'] as $j => $n) {
            $this->line("  {$j}: {$n} CUIT");
        }

        if ($r['ejemplos_invalidas'] !== []) {
            $this->newLine();
            $this->warn('Ejemplos de líneas inválidas:');
            foreach ($r['ejemplos_invalidas'] as $e) {
                $this->line("  línea {$e['linea']}: {$e['contenido']}");
            }
        }
    }
}
