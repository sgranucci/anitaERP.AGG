<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Empresa;
use App\Models\Stock\MozoGastronomia;
use App\Repositories\Stock\MozoGastronomiaRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarMozoGastronomiaDesdeUnl extends Command
{
    protected $signature = 'mozo-gastronomia:importar-unl
                            {archivo : Ruta al .unl con columnas codigo|nombre|}
                            {--empresa= : ID de empresa (omite config por defecto)}';

    protected $description = 'Carga mozo_gastronomia desde un archivo .unl (codigo y nombre separados por |).';

    public function handle(MozoGastronomiaRepositoryInterface $repository): int
    {
        $path = $this->argument('archivo');
        if (! is_readable($path)) {
            $this->error("No se puede leer el archivo: {$path}");

            return self::FAILURE;
        }

        $empresaId = $this->resolvedEmpresaId();
        if ($empresaId === null) {
            return self::FAILURE;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            $this->error('Error al leer el contenido del archivo.');

            return self::FAILURE;
        }

        $importados = 0;
        $actualizados = 0;
        $omitidos = 0;

        foreach (explode("\n", $contents) as $line) {
            $line = rtrim($line, "\r");
            if ($line === '') {
                continue;
            }

            $parts = explode('|', $line, 3);
            $codigo = trim((string) ($parts[0] ?? ''));
            $nombre = trim((string) ($parts[1] ?? ''));

            if ($codigo === '' || $nombre === '') {
                $omitidos++;

                continue;
            }

            $datos = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'empresa_id' => $empresaId,
            ];

            $existente = MozoGastronomia::query()->where('codigo', $codigo)->first();

            DB::beginTransaction();
            try {
                if ($existente) {
                    $existente->update($datos);
                    $actualizados++;
                } else {
                    $repository->create($datos);
                    $importados++;
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Fila código {$codigo}: ".$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info("Importados: {$importados}; actualizados: {$actualizados}; líneas omitidas: {$omitidos}.");

        return self::SUCCESS;
    }

    private function resolvedEmpresaId(): ?int
    {
        $raw = $this->option('empresa');
        if ($raw !== null && $raw !== '') {
            $id = (int) $raw;
            if ($id <= 0 || ! Empresa::query()->where('id', $id)->exists()) {
                $this->error("empresa_id {$id} inexistente.");

                return null;
            }

            return $id;
        }

        $id = (int) config('mozo_gastronomia_anita.empresa_default_id', 1);
        if ($id <= 0 || ! Empresa::query()->where('id', $id)->exists()) {
            $this->error(
                "empresa_id por defecto ({$id}) inválido o inexistente. Use --empresa=ID o ajuste MOZO_GASTRONOMIA_SYNC_EMPRESA_ID / cliente.EMPRESA_DEFAULT_ID."
            );

            return null;
        }

        return $id;
    }
}
