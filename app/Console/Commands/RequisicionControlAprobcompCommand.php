<?php

namespace App\Console\Commands;

use App\Models\Compras\Requisicion;
use App\Services\Compras\RequisicionAnitaAprobcompSyncService;
use Illuminate\Console\Command;

class RequisicionControlAprobcompCommand extends Command
{
    protected $signature = 'requisicion:control-aprobcomp
                            {--lote=120 : Tamaño de cada lectura Anita}
                            {--desde-nro=0 : Solo numerorequisicion mayor o igual}
                            {--origen=erp : erp=nacidas y aprobadas en anitaERP (default); todas=incluye importadas de Anita}
                            {--escribir : Inserta snapshot ERP en las que faltan y se pueden grabar}
                            {--muestra=40 : Cuántas faltantes listar}';

    protected $description = 'Controla requisiciones ERP con historia APROBADA vs aprobcomp Anita (l-proy)';

    public function handle(RequisicionAnitaAprobcompSyncService $sync): int
    {
        $lote = (int) $this->option('lote');
        $desdeNro = max(0, (int) $this->option('desde-nro'));
        $escribir = (bool) $this->option('escribir');
        $muestra = max(0, (int) $this->option('muestra'));

        $origen = strtolower(trim((string) $this->option('origen')));
        $soloErp = $origen !== 'todas';
        $this->info($soloErp
            ? 'Solo requisiciones nacidas y aprobadas en anitaERP (alta provisorio / Alta de requisición).'
            : 'Todas las requisiciones ERP con historia APROBADA (incluye importadas de Anita).'
        );
        $ctrl = $sync->controlarAprobadas($lote, $desdeNro, $soloErp);

        $this->table(['Métrica', 'Cantidad'], [
            ['ERP con historia APROBADA', $ctrl['erp_aprobadas']],
            ['Lecturas bridge', $ctrl['lecturas_bridge']],
            ['Ya tienen aprobcomp (cualquier estado)', $ctrl['con_aprobcomp']],
            ['  de esas, con estado Aprobado (3)', $ctrl['con_estado_aprobado']],
            ['Sin fila aprobcomp', $ctrl['sin_aprobcomp']],
            ['  aún en árbol / pendiente (no se escribe)', $ctrl['sin_aprobcomp_vivo']],
            ['  se pueden grabar (snapshot ERP)', $ctrl['sin_aprobcomp_escribible']],
        ]);

        if ($ctrl['por_estado_sin'] !== []) {
            $this->line('Sin aprobcomp por estado ERP:');
            $filas = [];
            foreach ($ctrl['por_estado_sin'] as $estado => $cant) {
                $filas[] = [$estado, $cant];
            }
            $this->table(['Estado ERP', 'Sin aprobcomp'], $filas);
        }

        if ($muestra > 0 && $ctrl['faltantes'] !== []) {
            $this->line('Muestra de faltantes:');
            $this->table(
                ['id', 'nro', 'estado'],
                array_map(
                    static fn (array $f) => [$f['id'], $f['numerorequisicion'], $f['estado']],
                    array_slice($ctrl['faltantes'], 0, $muestra)
                )
            );
        }

        if (! $escribir) {
            $this->comment('Sin --escribir: solo control. Para grabar las escribibles: requisicion:control-aprobcomp --escribir');

            return self::SUCCESS;
        }

        $ins = 0;
        $omit = 0;
        $err = 0;
        foreach ($ctrl['faltantes'] as $f) {
            if (in_array($f['estado'], \App\Support\Compras\AnitaSync\Requisicion\RequisicionAnitaAprobcompMapper::ESTADOS_ANITA_VIVO, true)) {
                $omit++;

                continue;
            }
            $req = Requisicion::query()->find($f['id']);
            if ($req === null) {
                $omit++;

                continue;
            }
            $res = $sync->asegurarSnapshot($req, true);
            if ($res === RequisicionAnitaAprobcompSyncService::RESULTADO_INSERTADO) {
                $ins++;
            } elseif ($res === RequisicionAnitaAprobcompSyncService::RESULTADO_ERROR) {
                $err++;
                $this->warn('Error nro '.$f['numerorequisicion']);
            } else {
                $omit++;
            }
        }

        $this->info("Escritura: insertados={$ins} omitidos={$omit} errores={$err}");

        return $err > 0 ? self::FAILURE : self::SUCCESS;
    }
}
