<?php

namespace App\Console\Commands;

use App\Models\Uif\Cliente_Premio_Archivo_Uif;
use Illuminate\Console\Command;

class MigrarArchivosPremioUifASubcarpetas extends Command
{
    protected $signature = 'premio-uif:migrar-archivos
        {--dry-run : No mueve archivos, solo informa}';

    protected $description = 'Mueve los archivos de premios UIF de /storage/archivos/clientes_premios_uif/{file} a /storage/archivos/clientes_premios_uif/{id}/{file} (mismo criterio que clientes UIF).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $base = public_path('/storage/archivos/clientes_premios_uif');

        if (! is_dir($base)) {
            $this->warn("No existe el directorio base: {$base}");

            return self::SUCCESS;
        }

        $registros = Cliente_Premio_Archivo_Uif::query()->orderBy('cliente_premio_uif_id')->get();

        $this->info('Registros a evaluar: '.$registros->count().($dryRun ? ' (dry-run)' : ''));

        $movidos = 0;
        $yaUbicados = 0;
        $sinOrigen = 0;
        $errores = 0;

        foreach ($registros as $arch) {
            $nombre = (string) $arch->nombrearchivo;
            $premioId = (int) $arch->cliente_premio_uif_id;

            if ($nombre === '' || $premioId <= 0) {
                continue;
            }

            $destinoDir = $base.'/'.$premioId;
            $destinoFile = $destinoDir.'/'.$nombre;
            $origenFile = $base.'/'.$nombre;

            if (is_file($destinoFile)) {
                $yaUbicados++;

                continue;
            }

            if (! is_file($origenFile)) {
                $sinOrigen++;
                $this->line("Sin origen: {$origenFile}");

                continue;
            }

            if ($dryRun) {
                $this->line("DRY: {$origenFile} -> {$destinoFile}");
                $movidos++;

                continue;
            }

            if (! is_dir($destinoDir) && ! @mkdir($destinoDir, 0775, true) && ! is_dir($destinoDir)) {
                $errores++;
                $this->error("No se pudo crear directorio: {$destinoDir}");

                continue;
            }

            if (! @rename($origenFile, $destinoFile)) {
                $errores++;
                $this->error("No se pudo mover: {$origenFile} -> {$destinoFile}");

                continue;
            }

            $movidos++;
        }

        $this->newLine();
        $this->info('Movidos: '.$movidos.($dryRun ? ' (dry-run)' : ''));
        $this->info('Ya ubicados en subcarpeta: '.$yaUbicados);
        $this->info('Sin archivo origen: '.$sinOrigen);
        $this->info('Errores: '.$errores);

        return self::SUCCESS;
    }
}
