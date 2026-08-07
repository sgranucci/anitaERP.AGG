<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\OrdencompraContratoAvisoService;
use App\Support\Compras\OrdencompraContratoVencimientoSupport;
use Illuminate\Console\Command;

class EnviarAlertasContratosVencimiento extends Command
{
    protected $signature = 'compras:alertas-contratos-vencimiento
                            {--empresa= : Limitar a una empresa (id)}
                            {--simular : Muestra las novedades sin enviar mails ni registrar el log}';

    protected $description = 'Avisa el vencimiento de contratos / OC abiertas: fin de vigencia, límite de preaviso de no renovación y consumo del monto contratado.';

    public function handle(OrdencompraContratoAvisoService $service): int
    {
        $empresaOpt = $this->option('empresa');
        $empresaId = $empresaOpt !== null && $empresaOpt !== '' ? (int) $empresaOpt : null;
        $simular = (bool) $this->option('simular');

        if ($simular) {
            $this->mostrarNovedades($empresaId);
        }

        $resultado = $service->procesar($empresaId, $simular);

        $this->line(sprintf(
            'Contratos con novedad: %d preventivos, %d vencidos.',
            $resultado['contratos_preventivos'],
            $resultado['contratos_vencidos']
        ));

        if ($resultado['omitido'] !== null) {
            $this->info('Sin envíos: '.$resultado['omitido']);

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Mails encolados: %d. Avisos registrados: %d.',
            $resultado['enviados'],
            $resultado['avisos_registrados']
        ));

        return self::SUCCESS;
    }

    private function mostrarNovedades(?int $empresaId): void
    {
        $novedades = OrdencompraContratoVencimientoSupport::novedades($empresaId);

        foreach (['preventivos' => 'Preventivos', 'vencidos' => 'Vencidos'] as $clave => $titulo) {
            if ($novedades[$clave] === []) {
                continue;
            }

            $this->newLine();
            $this->line('<comment>'.$titulo.'</comment>');
            $this->table(
                ['OC', 'Empresa', 'Proveedor', 'Vence', 'Motivo', 'Responsable'],
                array_map(static fn (array $c) => [
                    $c['numero'],
                    $c['empresa'],
                    $c['proveedor'],
                    OrdencompraContratoVencimientoSupport::fmtFecha($c['vigencia_hasta']),
                    $c['motivo'],
                    $c['responsable'] !== '' ? $c['responsable'] : 'sin asignar',
                ], $novedades[$clave])
            );
        }
    }
}
