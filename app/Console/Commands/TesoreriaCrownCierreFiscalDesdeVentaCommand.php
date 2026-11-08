<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Caja\TesoreriaCrownCierreFiscalDesdeVentaService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;
use Throwable;

/**
 * Crown: equivalente ERP de p-cierrefact — cierra facturación desde venta → tesoreria_crown_cierre_fiscal.
 */
class TesoreriaCrownCierreFiscalDesdeVentaCommand extends Command
{
    protected $signature = 'tesoreria-crown:cierre-fiscal-desde-venta
                            {fecha : Fecha de jornada Y-m-d}
                            {--sucursal= : Solo un PV/sucursal Anita (ej. 98)}
                            {--jornada= : jornada_id Crown (default: la del día)}
                            {--forzar-tomados : Regraba aunque el cierre ya esté en una rendición}
                            {--todas-con-venta : Incluye PVs con venta aunque Anita no tenga Z ese día}
                            {--dry-run : Solo analiza}
                            {--ejecutar : Persiste}';

    protected $description = 'CROWN: genera/actualiza cierres fiscales Z desde venta ERP (lógica p-cierrefact). Dry-run por defecto.';

    public function handle(): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->error('Este comando solo aplica a EMPRESA=CROWN.');

            return self::FAILURE;
        }
        if ((bool) $this->option('ejecutar') && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $fecha = trim((string) $this->argument('fecha'));
        $sucursalOpt = trim((string) ($this->option('sucursal') ?? ''));
        $sucursal = $sucursalOpt !== '' ? (int) $sucursalOpt : null;
        $jornadaId = (int) ($this->option('jornada') ?: 0);
        $jornada = null;
        if ($jornadaId > 0) {
            $jornada = TesoreriaCrownJornada::query()->find($jornadaId);
            if ($jornada === null) {
                $this->error('Jornada #'.$jornadaId.' no encontrada.');

                return self::FAILURE;
            }
        }

        $this->line(sprintf(
            'Fecha %s | PV %s%s',
            $fecha,
            $sucursal !== null ? (string) $sucursal : 'todos con venta',
            $ejecutar ? ' | EJECUTAR' : ' | MODO SIMULACIÓN'
        ));

        try {
            $resultado = TesoreriaCrownCierreFiscalDesdeVentaService::procesarFecha(
                $fecha,
                $jornada,
                $sucursal,
                (bool) $this->option('forzar-tomados'),
                ! $ejecutar,
                (bool) $this->option('todas-con-venta'),
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Sucursal', 'Z', 'Venta', 'Crédito', 'Acción'],
            array_map(static function (array $f): array {
                return [
                    (string) ($f['sucursal'] ?? ''),
                    (string) ($f['nro_z'] ?? ''),
                    number_format((float) ($f['tot_venta'] ?? 0), 2, '.', ''),
                    number_format((float) ($f['tot_cred'] ?? 0), 2, '.', ''),
                    (string) ($f['accion'] ?? ''),
                ];
            }, $resultado['filas'])
        );

        $this->info(sprintf(
            'Sucursales %d | creados %d | actualizados %d | omitidos tomados %d',
            $resultado['sucursales'],
            $resultado['creados'],
            $resultado['actualizados'],
            $resultado['omitidos_tomados']
        ));

        if (! $ejecutar) {
            $this->comment('Para grabar: php artisan tesoreria-crown:cierre-fiscal-desde-venta '.$fecha.' --ejecutar');
        }

        return self::SUCCESS;
    }
}
