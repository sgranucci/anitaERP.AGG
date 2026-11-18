<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Support\Contable\AsientoSubdiarioDuplicadoTransferenciaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Borra asientos importados desde subdiario/subhist cuyo comprobante fiscal
 * ya está asentado por transferencia Crown / venta ERP (mismo tipo/letra/PV/nro).
 *
 * Caso hotel Cipo/Viedma 30–31/10/2026: Importación ARCA (4/11) + import Anita (10/11)
 * dejaron el mayor al doble.
 */
class EliminarAsientosSubdiarioDuplicadosTransferenciaCommand extends Command
{
    protected $signature = 'contable:eliminar-asientos-subdiario-duplicados-transferencia
                            {--empresa-id=1 : empresa_id ERP}
                            {--desde= : Fecha inicial Y-m-d}
                            {--hasta= : Fecha final Y-m-d}
                            {--sucursales= : Códigos PV Anita separados por coma (ej. 127,131). Vacío = todas}
                            {--dry-run : Solo lista (default si no hay --ejecutar)}
                            {--ejecutar : Borra movimientos y asientos (con audits)}';

    protected $description = 'Elimina asientos subdiario/subhist duplicados de comprobantes ya transferidos (venta/ARCA)';

    public function handle(): int
    {
        $empresaId = max(1, (int) $this->option('empresa-id'));
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');

        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }
        if ($desde === '' || $hasta === '') {
            $this->error('Indique --desde y --hasta (Y-m-d).');

            return self::FAILURE;
        }

        $sucursales = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('sucursales')),
        ), static fn (int $s) => $s > 0));

        $aBorrar = AsientoSubdiarioDuplicadoTransferenciaSupport::detectar(
            $empresaId,
            $desde,
            $hasta,
            $sucursales,
        );

        $ids = array_map(static fn (array $f) => (int) $f['asiento_id'], $aBorrar);
        $movs = $ids === [] ? 0 : (int) Asiento_Movimiento::query()->whereIn('asiento_id', $ids)->count();

        $this->line(sprintf(
            'Empresa %d | %s → %s | sucursales %s | %s',
            $empresaId,
            $desde,
            $hasta,
            $sucursales === [] ? 'todas' : implode(',', $sucursales),
            $dryRun ? 'DRY-RUN' : 'EJECUTAR',
        ));
        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Asientos subdiario/subhist a borrar', (string) count($aBorrar)],
                ['Movimientos a borrar', (string) $movs],
                ['Importe Debe (Anita)', number_format(array_sum(array_column($aBorrar, 'importe')), 2, ',', '.')],
            ],
        );

        if ($aBorrar === []) {
            $this->info('Nada para borrar.');

            return self::SUCCESS;
        }

        $filas = [];
        foreach (array_slice($aBorrar, 0, 40) as $item) {
            $filas[] = [
                (string) $item['asiento_id'],
                (string) $item['numeroasiento'],
                (string) $item['fecha'],
                (string) $item['anita_origen'],
                (string) $item['clave'],
                (string) $item['proceso_id'],
                (string) $item['proceso_numeroasiento'],
                (string) ($item['venta_id'] ?? ''),
                number_format((float) $item['importe'], 2, ',', '.'),
            ];
        }
        $this->line('Muestra (máx. 40):');
        $this->table(
            ['Id subd', 'Nro subd', 'Fecha', 'Origen', 'Documento', 'Id transf', 'Nro transf', 'venta_id', 'Importe'],
            $filas,
        );
        if (count($aBorrar) > 40) {
            $this->line('… y '.(count($aBorrar) - 40).' más.');
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se borró nada. Para persistir: --ejecutar con el mismo rango.');

            return self::SUCCESS;
        }

        $borradosMov = 0;
        $borradosAsi = 0;
        DB::transaction(function () use ($ids, &$borradosMov, &$borradosAsi) {
            foreach (array_chunk($ids, 100) as $chunk) {
                $borradosMov += EloquentAuditDeleteSupport::each(
                    Asiento_Movimiento::query()->whereIn('asiento_id', $chunk)
                );
                $borradosAsi += EloquentAuditDeleteSupport::each(
                    Asiento::query()->whereIn('id', $chunk)
                );
            }
        });

        $this->info("Borrados: {$borradosAsi} asientos, {$borradosMov} movimientos.");

        return self::SUCCESS;
    }
}
