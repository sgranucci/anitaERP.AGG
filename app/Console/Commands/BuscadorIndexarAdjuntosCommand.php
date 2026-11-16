<?php

namespace App\Console\Commands;

use App\Services\Seguridad\BuscadorFtsIndexadorService;
use App\Support\Seguridad\BuscadorFtsCatalogoSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Indexa adjuntos para el buscador global (nombre + contenido).
 * Por defecto solo estima (--dry-run implícito sin --ejecutar).
 */
class BuscadorIndexarAdjuntosCommand extends Command
{
    protected $signature = 'buscador:indexar-adjuntos
                            {--dry-run : Solo estima impacto (default si no hay --ejecutar)}
                            {--ejecutar : Persiste el índice}
                            {--origen= : origen_tipo o padre_tipo (ej. ordencompra_archivo)}
                            {--limite= : Tope de filas por fuente}';

    protected $description = 'Indexa adjuntos (PDF/Office/texto) para la búsqueda global';

    public function handle(BuscadorFtsIndexadorService $indexador): int
    {
        if (! Schema::hasTable('buscador_fts_documento')) {
            $this->error('Falta la tabla buscador_fts_documento. Corré la migración 2026_11_15_140000.');

            return self::FAILURE;
        }

        $origen = $this->option('origen');
        $origen = is_string($origen) && $origen !== '' ? $origen : null;
        $limiteOpt = $this->option('limite');
        $limite = is_numeric($limiteOpt) ? max(1, (int) $limiteOpt) : null;
        $ejecutar = (bool) $this->option('ejecutar');

        $this->line('Fuentes configuradas: '.count(BuscadorFtsCatalogoSupport::fuentes()));
        if ($origen !== null) {
            $this->line('Filtro origen: '.$origen);
        }

        $estimacion = $indexador->estimar($origen, $limite);
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Fuentes con tabla', $estimacion['fuentes']],
                ['Candidatos leídos', $estimacion['candidatos']],
                ['A crear', $estimacion['a_crear']],
                ['A actualizar', $estimacion['a_actualizar']],
                ['Sin cambio', $estimacion['omitidos']],
                ['Sin archivo en disco', $estimacion['sin_archivo']],
            ]
        );

        if (! $ejecutar) {
            $this->warn('Dry-run: no se escribió nada. Para persistir: --ejecutar (mismo --origen/--limite).');

            return self::SUCCESS;
        }

        $this->info('Indexando…');
        $resultado = $indexador->indexar($origen, $limite);
        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ['Creados', $resultado['creados']],
                ['Actualizados', $resultado['actualizados']],
                ['Omitidos', $resultado['omitidos']],
                ['Sin archivo', $resultado['sin_archivo']],
                ['Errores', $resultado['errores']],
            ]
        );

        return self::SUCCESS;
    }
}
