<?php

namespace App\Console\Commands\Compras;

use App\ApiAnita;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Services\Compras\ProveedorCuentacorrienteAplicacionAnitaSyncService;
use Illuminate\Console\Command;

class SyncAplicacionCuentacorrienteAnitaCommand extends Command
{
    protected $signature = 'compras:sync-aplicacion-cc-anita
                            {--fecha= : Fecha Y-m-d de las aplicaciones (default: hoy)}
                            {--proveedor-id= : Solo un proveedor ERP}
                            {--aplicacion-id= : Solo un id de proveedor_cuentacorriente_aplicacion}
                            {--dry-run : Lista sin escribir Anita (default si no hay --ejecutar)}
                            {--ejecutar : Graba aplmovp y promov.prov_t_pagado}';

    protected $description = 'Espeja aplicaciones de CC proveedor ERP → Anita (aplmovp + promov.t_pagado)';

    public function handle(ProveedorCuentacorrienteAplicacionAnitaSyncService $sync): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $fecha = trim((string) ($this->option('fecha') ?: now()->format('Y-m-d')));
        $proveedorId = (int) ($this->option('proveedor-id') ?: 0);
        $aplicacionId = (int) ($this->option('aplicacion-id') ?: 0);

        $query = Proveedor_Cuentacorriente_Aplicacion::query()
            ->where('total', '<', 0)
            ->with([
                'proveedor_cuentacorrientes.proveedores',
                'proveedor_cuentacorrientes.comprobante_proveedores.tipotransaccion_compras',
                'proveedor_cuentacorriente_aplicados.pagoproveedores',
                'proveedor_cuentacorriente_aplicados.comprobante_proveedores.tipotransaccion_compras',
            ]);

        if ($aplicacionId > 0) {
            $query->where('id', $aplicacionId);
        } else {
            $query->whereDate('fecha', $fecha);
            if ($proveedorId > 0) {
                $query->whereHas(
                    'proveedor_cuentacorrientes',
                    static fn ($q) => $q->where('proveedor_id', $proveedorId)
                );
            }
        }

        $aplicaciones = $query->orderBy('id')->get();

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line($dryRun ? 'DRY-RUN: no se escribe Anita.' : 'EJECUTAR: aplmovp + promov.t_pagado.');
        $this->line('Aplicaciones lado deuda: '.$aplicaciones->count());

        $ok = 0;
        $errores = 0;
        foreach ($aplicaciones as $apl) {
            $deuda = $apl->proveedor_cuentacorrientes;
            $label = '#'.$apl->id.' '.$apl->fecha?->format('Y-m-d')
                .' '.$apl->comprobanteaplicado
                .' → '.(string) ($deuda?->comprobante_proveedores?->numerocomprobante ?? $deuda?->id)
                .' '.number_format(abs((float) $apl->total), 2, ',', '.');

            if ($dryRun) {
                $this->line('  '.$label);
                $ok++;
                continue;
            }

            try {
                $sync->syncAplicar($apl);
                $this->info('  OK '.$label);
                $ok++;
            } catch (\Throwable $e) {
                $this->error('  FAIL '.$label.': '.$e->getMessage());
                $errores++;
            }
        }

        $this->table(['Resultado', 'Cantidad'], [
            ['ok', $ok],
            ['errores', $errores],
        ]);

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
