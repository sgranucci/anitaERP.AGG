<?php

namespace App\Console\Commands;

use App\Support\Ventas\ClienteDocumentoAnitaSupport;
use Illuminate\Console\Command;

/**
 * Completa numerodocumento / tipodocumento_id vacíos desde clim_cuit de Anita.
 * Default: dry-run. --ejecutar persiste.
 */
class ClienteCompletarDocumentoDesdeAnitaCommand extends Command
{
    protected $signature = 'cliente:completar-documento-desde-anita
                            {--codigo= : Limitar a un código de cliente}
                            {--ejecutar : Persiste (sin esto solo informa)}';

    protected $description = 'Completa CUIT/documento y tipodocumento vacíos en cliente desde Anita (clim_cuit). No pisa valores ya cargados.';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $codigo = is_string($this->option('codigo')) ? trim($this->option('codigo')) : '';

        $this->comment($ejecutar
            ? 'PERSISTIR: solo numerodocumento / tipodocumento_id vacíos.'
            : 'Dry-run: no se graba nada.');

        if ($codigo !== '') {
            $this->info("Filtro código={$codigo}");
        }

        $this->info('Leyendo climae en Anita…');

        try {
            $analisis = ClienteDocumentoAnitaSupport::analizar($codigo !== '' ? $codigo : null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Clientes Anita (con código)', $analisis['anita']],
                ['Clientes ERP analizados', $analisis['erp']],
                ['ERP sin fila Anita', $analisis['sin_anita']],
                ['Anita sin clim_cuit', $analisis['anita_sin_cuit']],
                ['Se completarían número', $analisis['completar_numero']],
                ['Se completarían tipo doc', $analisis['completar_tipo']],
                ['Ya completos (número+tipo)', $analisis['ya_completos']],
                ['Filas con cambio', count($analisis['filas'])],
            ]
        );

        if ($analisis['ejemplos'] !== []) {
            $this->newLine();
            $this->comment('Ejemplos');
            $this->table(
                ['Código', 'Nombre', 'Actual', 'Anita', 'Nuevo nº', 'Nuevo tipo'],
                array_map(static fn (array $r) => [
                    $r['codigo'],
                    mb_substr((string) $r['nombre'], 0, 40),
                    $r['numero_actual'] ?? '',
                    $r['anita_cuit'] ?? '',
                    $r['numerodocumento_nuevo'] ?? '',
                    $r['tipodocumento_id_nuevo'] ?? '',
                ], $analisis['ejemplos'])
            );
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->warn('Nada persistido. Para grabar: php artisan cliente:completar-documento-desde-anita --ejecutar');

            return self::SUCCESS;
        }

        $ok = ClienteDocumentoAnitaSupport::persistir($analisis);
        $this->info('Grabado numerodocumento='.$ok['numero'].' tipodocumento_id='.$ok['tipo']);

        return self::SUCCESS;
    }
}
