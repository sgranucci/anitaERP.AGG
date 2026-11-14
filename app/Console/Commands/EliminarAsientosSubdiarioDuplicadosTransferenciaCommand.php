<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Support\Contable\AsientoAnitaMetadatosSupport;
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

        $proceso = Asiento::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('venta_id')->where('venta_id', '>', 0);
                })->orWhere('anita_origen', AsientoAnitaMetadatosSupport::ORIGEN_TRANSFERENCIA);
            })
            ->whereNotNull('anita_tipo')
            ->where('anita_sucursal', '>', 0)
            ->where('anita_nro', '>', 0)
            ->when($sucursales !== [], fn ($q) => $q->whereIn('anita_sucursal', $sucursales))
            ->get([
                'id', 'numeroasiento', 'fecha', 'anita_origen', 'anita_tipo', 'anita_letra',
                'anita_sucursal', 'anita_nro', 'venta_id', 'observacion',
            ]);

        $cubiertos = [];
        foreach ($proceso as $asiento) {
            $clave = AsientoAnitaMetadatosSupport::claveDocumento(
                $asiento->anita_tipo,
                $asiento->anita_letra,
                $asiento->anita_sucursal,
                $asiento->anita_nro,
            );
            if ($clave === null || isset($cubiertos[$clave])) {
                continue;
            }
            $cubiertos[$clave] = $asiento;
        }

        if ($cubiertos === []) {
            $this->warn('No hay asientos de transferencia/venta con documento en el rango.');

            return self::SUCCESS;
        }

        $candidatos = Asiento::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereIn('anita_origen', [
                AsientoAnitaMetadatosSupport::ORIGEN_SUBDIARIO,
                AsientoAnitaMetadatosSupport::ORIGEN_SUBHIST,
            ])
            ->whereNotNull('anita_tipo')
            ->where('anita_sucursal', '>', 0)
            ->where('anita_nro', '>', 0)
            ->when($sucursales !== [], fn ($q) => $q->whereIn('anita_sucursal', $sucursales))
            ->orderBy('fecha')
            ->orderBy('anita_sucursal')
            ->orderBy('anita_nro')
            ->orderBy('id')
            ->get([
                'id', 'numeroasiento', 'fecha', 'anita_origen', 'anita_tipo', 'anita_letra',
                'anita_sucursal', 'anita_nro', 'observacion', 'created_at',
            ]);

        $aBorrar = [];
        foreach ($candidatos as $asiento) {
            $clave = AsientoAnitaMetadatosSupport::claveDocumento(
                $asiento->anita_tipo,
                $asiento->anita_letra,
                $asiento->anita_sucursal,
                $asiento->anita_nro,
            );
            if ($clave === null || ! isset($cubiertos[$clave])) {
                continue;
            }
            $proc = $cubiertos[$clave];
            $aBorrar[] = [
                'asiento' => $asiento,
                'proceso' => $proc,
                'clave' => $clave,
            ];
        }

        $ids = array_map(static fn (array $f) => (int) $f['asiento']->id, $aBorrar);
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
                ['Documentos transferencia/venta', (string) count($cubiertos)],
                ['Asientos subdiario/subhist a borrar', (string) count($aBorrar)],
                ['Movimientos a borrar', (string) $movs],
            ],
        );

        if ($aBorrar === []) {
            $this->info('Nada para borrar.');

            return self::SUCCESS;
        }

        $filas = [];
        foreach (array_slice($aBorrar, 0, 40) as $item) {
            $a = $item['asiento'];
            $p = $item['proceso'];
            $filas[] = [
                (string) $a->id,
                (string) $a->numeroasiento,
                (string) $a->fecha,
                (string) $a->anita_origen,
                $item['clave'],
                (string) $p->id,
                (string) $p->numeroasiento,
                (string) ($p->venta_id ?? ''),
            ];
        }
        $this->line('Muestra (máx. 40):');
        $this->table(
            ['Id subd', 'Nro subd', 'Fecha', 'Origen', 'Documento', 'Id transf', 'Nro transf', 'venta_id'],
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
