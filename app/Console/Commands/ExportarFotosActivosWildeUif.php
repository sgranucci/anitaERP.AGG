<?php

namespace App\Console\Commands;

use App\Services\Uif\ActivosWildePlanillaReader;
use App\Services\Uif\ExportarFotosActivosWildeService;
use Illuminate\Console\Command;

class ExportarFotosActivosWildeUif extends Command
{
    protected $signature = 'uif:exportar-fotos-activos-wilde
                            {--excel=* : Planillas xlsx (default: Crystal + Platino Wilde en ~/tmp)}
                            {--salida=/home/sergio/anitaERP_fotos_activos_wilde : Carpeta destino con subcarpetas por DNI}
                            {--servidor-kandiko=192.168.20.100:8080 : Bridge Anita Kandiko/Wilde}
                            {--servidor-biyemas=10.20.30.200:8080 : Bridge Anita Biyemas (fallback UIF y climae)}
                            {--dni-pdf-mount=/scan/tesoreria/dni_uif : Documento DNI (abm_clientes_uif: {DNI}.pdf)}
                            {--adjuntos-mount=/scan/uif/archivos : Adjuntos scan (clientes_KSA, clientes, …)}
                            {--premio-http=http://192.168.20.100:8080/mod_fotos/fotos_clientes : Foto premio Anita (pago_{inropremioid})}
                            {--premio-mount-fallback=/scan/tesoreria/fotos_clientes : Fallback si mod_fotos no tiene el pago_*}
                            {--dry-run : Solo consulta Anita e informa; no copia archivos}';

    protected $description = 'Exporta DNI (dni_uif), adjuntos UIF y foto de primer premio (mod_fotos) por DNI — planillas Activos Wilde.';

    public function handle(ExportarFotosActivosWildeService $service): int
    {
        $excels = $this->option('excel');
        if (! is_array($excels) || $excels === []) {
            $excels = [
                '/home/sergio/tmp/Copia de Activos Crystal Wilde.xlsx',
                '/home/sergio/tmp/Copia de Activos Platino Wilde.xlsx',
            ];
        }

        $faltantes = array_filter($excels, static fn ($p) => ! is_readable((string) $p));
        if ($faltantes !== []) {
            foreach ($faltantes as $p) {
                $this->error('Planilla no legible: '.$p);
            }

            return self::FAILURE;
        }

        $titulares = ActivosWildePlanillaReader::leerDesdeArchivos($excels);
        if ($titulares === []) {
            $this->error('No se encontraron DNIs en las planillas.');

            return self::FAILURE;
        }

        $this->info('DNIs únicos a procesar: '.count($titulares));
        $dryRun = (bool) $this->option('dry-run');

        try {
            $resumen = $service->exportar($titulares, [
                'salida' => (string) $this->option('salida'),
                'dry_run' => $dryRun,
                'servidor_kandiko' => (string) $this->option('servidor-kandiko'),
                'servidor_biyemas' => (string) $this->option('servidor-biyemas'),
                'dni_pdf_mount' => (string) $this->option('dni-pdf-mount'),
                'adjuntos_mount' => (string) $this->option('adjuntos-mount'),
                'premio_http' => (string) $this->option('premio-http'),
                'premio_mount_fallback' => (string) $this->option('premio-mount-fallback'),
            ]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['DNI', 'Titular', 'Fuente', 'ID', 'Premio', 'Docs', 'Notas'],
            array_map(static function (array $f): array {
                return [
                    $f['dni'],
                    mb_substr((string) $f['titular'], 0, 24),
                    (string) $f['fuente'],
                    $f['inroclienteid'] ?? '-',
                    $f['inropremioid'] ?? '-',
                    count($f['dni_archivos']),
                    mb_substr(implode(' | ', $f['notas']), 0, 55),
                ];
            }, $resumen['detalle'])
        );

        $this->newLine();
        $this->info('Completos (documento/adj. + premio): '.$resumen['ok']);
        $this->info('Parciales: '.$resumen['parcial']);
        $this->warn('Sin datos: '.$resumen['sin_datos']);

        if (! $dryRun) {
            $this->info('Salida: '.$this->option('salida'));
            $this->info('Resumen: '.$this->option('salida').'/resumen.json');
        }

        return self::SUCCESS;
    }
}
