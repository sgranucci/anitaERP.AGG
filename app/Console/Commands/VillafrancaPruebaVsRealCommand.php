<?php

namespace App\Console\Commands;

use App\Support\Ventas\VillafrancaPruebaVsRealSupport;
use Illuminate\Console\Command;

/**
 * Solo lectura: origen A 8 / A 10 de cada FAC/NC Villafranca del ERP y presencia en Anita.
 */
class VillafrancaPruebaVsRealCommand extends Command
{
    protected $signature = 'ventas:reporte-villafranca-prueba-real
                            {--dir= : Directorio de CSV (default storage/app/reportes/villafranca_prueba_vs_real)}';

    protected $description = 'FAC/NC Villafranca del ERP: origen sucursal 8, 10 o ausente, y si está en /usr2/villafranca. No borra nada.';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $this->info('Solo lectura. No se borra ningún comprobante.');
        $this->comment('ERP Villafranca + origen A 8/A 10 + bridge /usr2/villafranca…');

        $datos = VillafrancaPruebaVsRealSupport::generar();
        $erp = $datos['erp'];
        $enAnita = $datos['en_anita'];

        $dir = trim((string) $this->option('dir'));
        if ($dir === '') {
            $dir = storage_path('app/reportes/villafranca_prueba_vs_real');
        }
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $stamp = date('Ymd_His');
        $csvErp = $dir.'/erp_origen_'.$stamp.'.csv';
        $csvAnita = $dir.'/en_anita_'.$stamp.'.csv';
        $this->escribirCsv($csvErp, $erp);
        $this->escribirCsv($csvAnita, $enAnita);

        $this->imprimirResumen('Origen en ERP (A 8 / A 10 / sin factura)', $datos['resumen_origen']);
        $this->imprimirResumen('Están en Anita /usr2/villafranca', $datos['resumen_anita']);

        $this->info('=== Todas las FAC/NC Villafranca del ERP ===');
        $this->table(
            ['id', 'fecha', 'comp', 'cliente', 'total', 'origen', 'factura origen', 'anita'],
            array_map(static function (array $f): array {
                return [
                    $f['venta_id'],
                    $f['fecha'],
                    $f['comprobante'],
                    mb_substr((string) $f['cliente'], 0, 26),
                    number_format((float) $f['total'], 2, ',', '.'),
                    $f['origen'],
                    $f['origen_comprobante'] ?: '-',
                    $f['en_anita_vf'],
                ];
            }, $erp)
        );

        $this->info('=== Solo las que están en /usr2/villafranca ('.count($enAnita).') ===');
        $this->table(
            ['id', 'fecha', 'comp', 'origen', 'anita'],
            array_map(static function (array $f): array {
                return [
                    $f['venta_id'],
                    $f['fecha'],
                    $f['comprobante'],
                    $f['origen_comprobante'] ?: $f['origen'],
                    $f['anita_clave'] ?: $f['en_anita_vf'],
                ];
            }, $enAnita)
        );

        $this->info('CSV todas:     '.$csvErp);
        $this->info('CSV en Anita:  '.$csvAnita);

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function escribirCsv(string $path, array $filas): void
    {
        $headers = VillafrancaPruebaVsRealSupport::headers();
        $fh = fopen($path, 'w');
        if ($fh === false) {
            throw new \RuntimeException('No se pudo escribir '.$path);
        }
        fprintf($fh, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fh, $headers, ';');
        foreach ($filas as $f) {
            $row = [];
            foreach ($headers as $h) {
                $val = $f[$h] ?? '';
                $row[] = $h === 'total'
                    ? number_format((float) $val, 2, ',', '')
                    : $val;
            }
            fputcsv($fh, $row, ';');
        }
        fclose($fh);
    }

    /**
     * @param  array<string, array{n:int,total:float}>  $resumen
     */
    private function imprimirResumen(string $titulo, array $resumen): void
    {
        $this->newLine();
        $this->info('=== '.$titulo.' ===');
        $rows = [];
        foreach ($resumen as $clase => $a) {
            $rows[] = [$clase, $a['n'], number_format($a['total'], 2, ',', '.')];
        }
        $this->table(['grupo', 'cantidad', 'total'], $rows);
    }
}
