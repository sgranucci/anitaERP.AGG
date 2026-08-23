<?php

namespace App\Console\Commands\Seguridad;

use App\Services\Configuracion\ModuloAvisoService;
use App\Services\Seguridad\IngresoProveedorAbonoReporteService;
use Illuminate\Console\Command;

class EnviarAlertaAbonoSinIngresos extends Command
{
    protected $signature = 'seguridad:alerta-abono-sin-ingresos
                            {--simular : Lista los contratos sin enviar avisos}';

    protected $description = 'Avisa contratos vigentes del mes sin tickets Finalizado (checklist de pago de abono).';

    public function handle(
        IngresoProveedorAbonoReporteService $reporteService,
        ModuloAvisoService $avisoService
    ): int {
        $simular = (bool) $this->option('simular');
        $resultado = $reporteService->generar([
            'fecha_desde' => now()->startOfMonth()->format('Y-m-d'),
            'fecha_hasta' => now()->endOfMonth()->format('Y-m-d'),
        ]);
        $filas = array_values(array_filter(
            $resultado['filas'],
            static fn (array $f) => (int) ($f['tickets_finalizados'] ?? 0) === 0
        ));
        $this->line('Contratos sin tickets Finalizado en el mes: '.count($filas));
        if ($filas === []) {
            return self::SUCCESS;
        }

        $this->table(
            ['OC', 'Proveedor', 'Empresa', 'Tickets'],
            array_map(static fn (array $f) => [
                $f['oc_numero'] ?? '',
                $f['proveedor'] ?? '',
                $f['nombreempresa'] ?? '',
                $f['tickets_finalizados'] ?? 0,
            ], $filas)
        );

        if ($simular) {
            $this->info('Simulación: no se enviaron avisos.');

            return self::SUCCESS;
        }

        $periodo = now()->startOfMonth()->format('d/m/Y').' — '.now()->endOfMonth()->format('d/m/Y');
        $enviados = 0;
        foreach ($filas as $fila) {
            $avisoService->enviar(
                'seguridad',
                'ingreso_proveedor_abono_sin_cierre',
                (int) $fila['oc_id'],
                [
                    'periodo' => $periodo,
                    'tickets' => (string) ($fila['tickets_finalizados'] ?? 0),
                ]
            );
            $enviados++;
        }
        $this->info('Avisos encolados: '.$enviados);

        return self::SUCCESS;
    }
}
