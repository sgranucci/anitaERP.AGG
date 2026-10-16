<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\ComprobanteProveedorIvaProrrateoReclasificarService;
use Illuminate\Console\Command;

/**
 * Reclasifica IVA de facturas octubre mal imputadas a Indirecto (LEGAJO sin prorrateo).
 */
class ComprobanteProveedorIvaProrrateoReclasificarCommand extends Command
{
    protected $signature = 'compras:reclasificar-iva-prorrateo-octubre
        {--ejecutar : Aplica conceptos + asiento ERP + ctamov Anita. Sin este flag solo informa.}
        {--ids= : Lista de comprobante_proveedor.id separados por coma (opcional)}';

    protected $description = 'Reclasifica IVA multi-CC / FNB de octubre mal imputado a 114010-002 (asientos octubre).';

    public function handle(ComprobanteProveedorIvaProrrateoReclasificarService $service): int
    {
        $idsRaw = trim((string) $this->option('ids'));
        $ids = null;
        if ($idsRaw !== '') {
            $ids = array_values(array_filter(array_map('intval', explode(',', $idsRaw))));
            if ($ids === []) {
                $this->error('Ningún id válido en --ids.');

                return self::FAILURE;
            }
        }

        $planes = $service->previsualizar($ids);
        if ($planes === []) {
            $this->info('No hay comprobantes de octubre para reclasificar.');

            return self::SUCCESS;
        }

        $this->table(
            ['CP', 'Asiento', 'Fecha', 'Comprobante', 'OC', 'Proveedor', 'Modo', 'IVA actual', 'IVA objetivo'],
            array_map(static function (array $p): array {
                return [
                    $p['comprobante_id'],
                    $p['asiento_id'],
                    $p['fechaiva'],
                    $p['comprobante'],
                    $p['oc'],
                    mb_substr((string) $p['proveedor'], 0, 28),
                    $p['modo'],
                    implode(' | ', $p['iva_actual']),
                    implode(' | ', $p['iva_objetivo']),
                ];
            }, $planes)
        );

        if (! $this->option('ejecutar')) {
            $this->comment('No se grabó nada. Para aplicarlo: --ejecutar');

            return self::SUCCESS;
        }

        $resultado = $service->aplicar($ids);
        $this->info('Procesados: '.$resultado['procesados']);
        if ($resultado['omitidos'] > 0) {
            $this->warn('Con error: '.$resultado['omitidos']);
            foreach ($resultado['errores'] as $err) {
                $this->error($err);
            }
        }

        return $resultado['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
