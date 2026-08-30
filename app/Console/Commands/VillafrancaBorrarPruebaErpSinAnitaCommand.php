<?php

namespace App\Console\Commands;

use App\Support\Ventas\VillafrancaPruebaErpBorradoSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Solo ERP. No toca Anita /usr2/villafranca ni las FAC A 8 / A 10 de Bierzo.
 */
class VillafrancaBorrarPruebaErpSinAnitaCommand extends Command
{
    protected $signature = 'ventas:borrar-villafranca-prueba-erp-sin-anita
                            {--ejecutar : Persiste el borrado. Sin este flag solo informa}';

    protected $description = 'Borra del ERP las FAC/NC Villafranca con origen A 8 que no están en /usr2/villafranca. No toca Anita.';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $filas = VillafrancaPruebaErpBorradoSupport::candidatas();
        $ids = array_map(static fn (array $f): int => (int) $f['venta_id'], $filas);
        $hijas = VillafrancaPruebaErpBorradoSupport::contarHijas($ids);

        $this->info('Criterio: origen ERP sucursal 8 y sin venta en /usr2/villafranca.');
        $this->info('No se tocan: Anita, FAC A 8/A 10 de Bierzo, ORIGEN_10, ni las VF que sí están en el bridge.');
        $this->newLine();
        $this->table(
            ['tabla', 'filas'],
            collect($hijas)->map(static fn ($n, $t) => [$t, $n])->values()->all()
        );

        $this->table(
            ['id', 'fecha', 'comp', 'cliente', 'total', 'origen'],
            array_map(static function (array $f): array {
                return [
                    $f['venta_id'],
                    $f['fecha'],
                    $f['comprobante'],
                    mb_substr((string) $f['cliente'], 0, 28),
                    number_format((float) $f['total'], 2, ',', '.'),
                    $f['origen_comprobante'],
                ];
            }, $filas)
        );

        if (! $this->option('ejecutar')) {
            $this->comment('Dry-run. Nada se borró. Para persistir: --ejecutar');

            return self::SUCCESS;
        }

        $ok = 0;
        foreach ($ids as $id) {
            try {
                DB::transaction(static function () use ($id): void {
                    VillafrancaPruebaErpBorradoSupport::eliminarUna($id);
                });
                $ok++;
                $this->line("OK venta_id={$id}");
            } catch (\Throwable $e) {
                $this->error("FALLÓ venta_id={$id}: ".$e->getMessage());
            }
        }

        $this->info("Eliminadas {$ok}/".count($ids).' ventas ERP.');

        return $ok === count($ids) ? self::SUCCESS : self::FAILURE;
    }
}
