<?php

namespace App\Console\Commands;

use App\Services\Stock\RecepcionProveedorImportarDesdeAnitaService;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Illuminate\Console\Command;

class RecepcionProveedorImportarDesdeAnitaCommand extends Command
{
    protected $signature = 'recepcion-proveedor:importar-desde-anita
                            {--desde=2025-01-01 : Fecha ISO desde (inclusive)}
                            {--hasta= : Fecha ISO hasta (inclusive, default hoy)}
                            {--nro= : Importar solo una COM por recm_nro}
                            {--oc= : Importar todas las COM de una OC (recepmae + aplicped)}
                            {--sucursal=1 : Sucursal Anita (recm_sucursal) para --nro}
                            {--impactar : Confirma en ERP (stock + asiento + sync Anita); default histórico sin impacto}
                            {--dry-run : Solo contadores, sin grabar}';

    protected $description = 'Importa recepmae/recepmov COM desde Anita hacia recepcion_proveedor';

    public function handle(RecepcionProveedorImportarDesdeAnitaService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $nro = $this->option('nro');
        $numeroOc = $this->option('oc');
        $impactar = (bool) $this->option('impactar');

        if ($numeroOc !== null && $numeroOc !== '') {
            $stats = $service->asegurarPorNumeroOc((int) $numeroOc, $impactar, $dryRun);
            $this->table(['Métrica', 'Cantidad'], [
                ['COM en Anita', $stats['claves']],
                ['Importadas', $stats['importadas']],
                ['Vinculadas a la OC', $stats['vinculadas']],
                ['Omitidas / ya existían', $stats['omitidas']],
                ['Errores', count($stats['errores'])],
            ]);
            foreach ($stats['errores'] as $error) {
                $this->warn($error);
            }
            if ($dryRun) {
                $this->comment('Dry-run: no se grabó nada.');
            }

            return $stats['errores'] === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($nro !== null && $nro !== '') {
            $sucursal = (int) $this->option('sucursal');
            if ($sucursal <= 0) {
                $this->error('--sucursal inválida.');

                return self::FAILURE;
            }

            if ($impactar && ! \Auth::check()) {
                $usuarioId = (int) config('recepcion_proveedor.auditoria_asientos_com_diaria.usuario_id', 1);
                if ($usuarioId <= 0 || ! \Auth::loginUsingId($usuarioId)) {
                    $this->error('No se pudo autenticar usuario de sistema para confirmar COM.');

                    return self::FAILURE;
                }
            }

            $resultado = $service->importarCom($sucursal, (int) $nro, $impactar, $dryRun);

            $this->table(['Campo', 'Valor'], [
                ['Estado', $resultado['estado']],
                ['Recepción ERP id', $resultado['recepcion_id'] ?? '—'],
                ['Movimiento stock id', $resultado['movimientostock_id'] ?? '—'],
                ['Asiento id', $resultado['asiento_id'] ?? '—'],
                ['Líneas Anita', $resultado['lineas']],
                ['Mensaje', $resultado['mensaje'] ?? '—'],
            ]);

            if ($dryRun) {
                $this->comment('Dry-run: no se grabó nada.');
            }

            return in_array($resultado['estado'], ['importada', 'importada_con_impacto', 'omitida', 'vinculada', 'dry_run'], true)
                ? self::SUCCESS
                : self::FAILURE;
        }

        $desdeIso = (string) $this->option('desde');
        $hastaIso = $this->option('hasta') ? (string) $this->option('hasta') : date('Y-m-d');
        $dryRun = (bool) $this->option('dry-run');

        $fechaDesde = RecepcionProveedorAnitaImportSupport::fechaAnitaDesde($desdeIso);
        $fechaHasta = RecepcionProveedorAnitaImportSupport::fechaAnitaDesde($hastaIso);

        $total = RecepcionProveedorAnitaImportSupport::contarRecepmae($fechaDesde, $fechaHasta);
        $this->info("Recepciones COM/X en Anita desde {$desdeIso}: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $stats = $service->importarRecepmae($fechaDesde, $fechaHasta, $dryRun);

        $this->table(['Métrica', 'Cantidad'], [
            ['Importadas', $stats['importadas']],
            ['Omitidas / ya existían', $stats['omitidas']],
            ['Sin proveedor ERP', $stats['sin_proveedor']],
            ['Sin empresa ERP', $stats['sin_empresa']],
            ['OC Anita no encontrada en ERP', $stats['sin_oc']],
            ['Líneas grabadas', $stats['lineas']],
        ]);

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada.');
        }

        return self::SUCCESS;
    }
}
