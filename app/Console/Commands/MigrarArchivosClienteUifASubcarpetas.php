<?php

namespace App\Console\Commands;

use App\Models\Uif\Cliente_Archivo_Uif;
use Illuminate\Console\Command;

class MigrarArchivosClienteUifASubcarpetas extends Command
{
    protected $signature = 'cliente-uif:migrar-archivos
        {--dry-run : No mueve archivos, solo informa}';

    protected $description = 'Mueve los archivos de clientes UIF de /storage/archivos/clientes_uif/{file} a /storage/archivos/clientes_uif/{id}/{file} (mismo criterio que proveedores).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $base = public_path('/storage/archivos/clientes_uif');

        if (! is_dir($base)) {
            $this->warn("No existe el directorio base: {$base}");

            return self::SUCCESS;
        }

        $registros = Cliente_Archivo_Uif::query()->orderBy('cliente_uif_id')->get();

        $this->info('Registros a evaluar: '.$registros->count().($dryRun ? ' (dry-run)' : ''));

        $movidos = 0;
        $yaUbicados = 0;
        $sinOrigen = 0;
        $errores = 0;

        foreach ($registros as $arch) {
            $nombre = (string) $arch->nombrearchivo;
            $clienteId = (int) $arch->cliente_uif_id;

            if ($nombre === '' || $clienteId <= 0) {
                continue;
            }

            $destinoDir = $base.'/'.$clienteId;
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
