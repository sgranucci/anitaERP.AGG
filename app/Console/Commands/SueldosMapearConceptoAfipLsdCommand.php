<?php

namespace App\Console\Commands;

use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Lsd_Concepto_Afip_Sueldos;
use App\Support\Sueldos\ConceptoTipo;
use App\Support\Sueldos\Lsd\LsdConceptoAfipAsignacionSupport;
use App\Support\Sueldos\Lsd\LsdConceptoAfipCatalogo;
use Illuminate\Console\Command;

/**
 * Asigna concepto_afip (y flags LSD) según el catálogo oficial / reglas de descripción.
 */
class SueldosMapearConceptoAfipLsdCommand extends Command
{
    protected $signature = 'sueldos:mapear-concepto-afip-lsd
        {--dry-run : Solo informa (default si no se pasa --ejecutar)}
        {--ejecutar : Persiste concepto_afip, codigo_lsd_empleador y lsd_subsistemas}
        {--forzar : Pisa códigos AFIP ya cargados}
        {--solo-flags : Solo reescribe lsd_subsistemas de conceptos ya mapeados}';

    protected $description = 'Sugiere y graba el código AFIP LSD de cada concepto exportable (dry-run por defecto).';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        $forzar = (bool) $this->option('forzar');

        if ((bool) $this->option('solo-flags')) {
            return $this->reaplicarFlags($dryRun);
        }

        $this->info($dryRun ? 'Modo análisis (no graba).' : 'Modo ejecución: persistirá mapeo AFIP.');

        $catalogo = Lsd_Concepto_Afip_Sueldos::query()->pluck('descripcion', 'codigo');
        $conceptos = Concepto_Sueldos::query()
            ->where('activo', true)
            ->whereNotIn('tipo', array_merge(ConceptoTipo::TIPOS_SIN_IMPACTO_TOTALES, ['neto']))
            ->orderBy('codigo')
            ->get();

        $filas = [];
        $crear = 0;
        $actualizar = 0;
        $igual = 0;
        $omitidos = 0;
        $porAfip = [];
        $aPersistir = [];

        foreach ($conceptos as $c) {
            $sugerido = LsdConceptoAfipAsignacionSupport::sugerir(
                (int) $c->codigo,
                (string) $c->descripcion,
                (string) $c->tipo
            );
            $actual = LsdConceptoAfipCatalogo::normalizarCodigo($c->concepto_afip);
            if ($sugerido === null) {
                $omitidos++;
                $filas[] = [
                    (string) $c->codigo,
                    (string) $c->descripcion,
                    (string) $c->tipo,
                    $actual ?? '—',
                    '—',
                    'omitir (no va al 03)',
                    'omitir',
                ];
                continue;
            }
            $nuevo = $sugerido['codigo'];
            $eti = (string) ($catalogo[$nuevo] ?? $nuevo);
            $porAfip[$nuevo] = ($porAfip[$nuevo] ?? 0) + 1;
            if ($actual === $nuevo) {
                $igual++;
                $filas[] = [(string) $c->codigo, (string) $c->descripcion, (string) $c->tipo, $nuevo, $eti, $sugerido['motivo'], 'igual'];
                continue;
            }
            if ($actual !== null && ! $forzar) {
                $igual++;
                $filas[] = [(string) $c->codigo, (string) $c->descripcion, (string) $c->tipo, $actual, $eti, 'ya tiene AFIP (use --forzar)', 'conservar'];
                continue;
            }
            $accion = $actual === null ? 'crear' : 'actualizar';
            if ($accion === 'crear') {
                $crear++;
            } else {
                $actualizar++;
            }
            $filas[] = [
                (string) $c->codigo,
                (string) $c->descripcion,
                (string) $c->tipo,
                $nuevo,
                $eti,
                $sugerido['motivo'].' ['.$sugerido['confianza'].']',
                $accion,
            ];
            $aPersistir[] = [$c, $nuevo];
        }

        $this->table(
            ['Código', 'Concepto', 'Tipo', 'AFIP', 'Catálogo', 'Motivo', 'Acción'],
            $filas
        );

        $resumenAfip = [];
        ksort($porAfip);
        foreach ($porAfip as $cod => $n) {
            $resumenAfip[] = [$cod, (string) ($catalogo[$cod] ?? ''), (string) $n];
        }
        $this->table(['AFIP', 'Descripción', 'Conceptos'], $resumenAfip);
        $this->table(['Exportables', 'A crear', 'A actualizar', 'Igual/conservar', 'Omitidos (no 03)'], [
            [$conceptos->count(), $crear, $actualizar, $igual, $omitidos],
        ]);

        if ($dryRun) {
            $this->comment('Nada persistido. Para grabar: php artisan sueldos:mapear-concepto-afip-lsd --ejecutar');

            return self::SUCCESS;
        }

        $n = 0;
        foreach ($aPersistir as [$concepto, $afip]) {
            $concepto->concepto_afip = $afip;
            if (trim((string) $concepto->codigo_lsd_empleador) === '') {
                $concepto->codigo_lsd_empleador = LsdConceptoAfipCatalogo::codigoEmpleadorDesdeInterno($concepto->codigo);
            }
            $concepto->lsd_subsistemas = LsdConceptoAfipAsignacionSupport::flagsParaCodigo(
                $afip,
                is_array($concepto->lsd_subsistemas) ? $concepto->lsd_subsistemas : null
            );
            $concepto->save();
            $n++;
        }
        $this->info('Persistidos: '.$n.' conceptos.');

        return self::SUCCESS;
    }

    private function reaplicarFlags(bool $dryRun): int
    {
        $this->info($dryRun ? 'Modo análisis flags (no graba).' : 'Reaplicará lsd_subsistemas.');
        $n = 0;
        $conceptos = Concepto_Sueldos::query()
            ->whereNotNull('concepto_afip')
            ->where('concepto_afip', '!=', '')
            ->orderBy('codigo')
            ->get();
        foreach ($conceptos as $c) {
            $afip = LsdConceptoAfipCatalogo::normalizarCodigo($c->concepto_afip);
            if ($afip === null) {
                continue;
            }
            $nuevo = LsdConceptoAfipAsignacionSupport::flagsParaCodigo($afip, null);
            $actual = is_array($c->lsd_subsistemas) ? $c->lsd_subsistemas : [];
            if ($actual === $nuevo) {
                continue;
            }
            $n++;
            if (! $dryRun) {
                $c->lsd_subsistemas = $nuevo;
                $c->save();
            }
        }
        $this->info(($dryRun ? 'A actualizar: ' : 'Persistidos: ').$n.' conceptos.');
        if ($dryRun) {
            $this->comment('Para grabar: php artisan sueldos:mapear-concepto-afip-lsd --solo-flags --ejecutar');
        }

        return self::SUCCESS;
    }
}
