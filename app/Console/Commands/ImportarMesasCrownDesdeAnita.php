<?php

namespace App\Console\Commands;

use App\Services\Caja\TesoreriaCrownMesaAnitaImportService;
use Illuminate\Console\Command;

class ImportarMesasCrownDesdeAnita extends Command
{
    protected $signature = 'tesoreria-crown:importar-mesas-anita
                            {--fecha= : Jornada Y-m-d}
                            {--excepto= : Números de mesa Anita que no se importan}
                            {--reemplazar-prueba : Borra los movimientos de prueba de la mesa 1 (números chicos) antes de grabar}
                            {--ejecutar : Graba. Sin este flag solo muestra el plan}';

    protected $description = 'Importa movimientos de mesas de cncadmin (fichammae y efemesa) a la jornada.';

    public function handle(TesoreriaCrownMesaAnitaImportService $import): int
    {
        $fecha = trim((string) $this->option('fecha'));
        if ($fecha === '') {
            $this->error('Hace falta --fecha.');

            return self::FAILURE;
        }
        $excepto = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('excepto'))
        )));

        try {
            $plan = $import->plan($fecha, $excepto);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Mesa 1 (prueba ERP vs Anita)');
        foreach ($plan['mesa1']['detalle'] as $linea) {
            if ($plan['mesa1']['coincide']) {
                $this->line($linea);
            } else {
                $this->warn($linea);
            }
        }
        if (! $plan['mesa1']['coincide']) {
            $this->warn('Los movimientos de prueba de la mesa 1 no coinciden con Anita.');
            if ($this->option('ejecutar') && ! $this->option('reemplazar-prueba')) {
                $this->error('Para grabar con la mesa 1 correcta usá también --reemplazar-prueba.');

                return self::FAILURE;
            }
        }

        $filas = [];
        foreach ($plan['crear'] as $fila) {
            $filas[] = [
                $fila['mesa'],
                $fila['numero'],
                $fila['tipo'].' '.$fila['tipo_nombre'],
                $fila['hora'],
                $fila['cajero'],
                $fila['renglones'],
                $fila['comentario'],
            ];
        }
        $this->table(['Mesa', 'Número', 'Tipo', 'Hora', 'Cajero', 'Renglones', 'Comentario'], $filas);
        foreach ($plan['omitir'] as $aviso) {
            $this->warn($aviso);
        }
        foreach ($plan['avisos'] as $aviso) {
            $this->warn($aviso);
        }
        $this->info(count($plan['crear']).' movimientos para crear.');
        if ($plan['pruebas'] !== []) {
            $this->line('Pruebas ERP mesa 1: '.count($plan['pruebas']).' (números '.implode(', ', array_column($plan['pruebas'], 'numero')).').');
        }

        if (! $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar no se grabó nada.');
            if (! $plan['mesa1']['coincide']) {
                $this->line('Al grabar: --ejecutar --reemplazar-prueba');
            }

            return self::SUCCESS;
        }

        $altas = $import->aplicar($plan, (bool) $this->option('reemplazar-prueba'));
        $this->info('Grabados: '.$altas);

        return self::SUCCESS;
    }
}
