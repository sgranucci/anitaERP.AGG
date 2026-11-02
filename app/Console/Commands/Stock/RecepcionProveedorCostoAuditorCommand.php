<?php

namespace App\Console\Commands\Stock;

use App\Services\Stock\RecepcionProveedorCostoAuditorService;
use Illuminate\Console\Command;

class RecepcionProveedorCostoAuditorCommand extends Command
{
    protected $signature = 'recepcion-proveedor:auditar-costos
                            {--desde= : Desde Y-m-d (default: ventana configurada hasta hoy)}
                            {--hasta= : Hasta Y-m-d (default: hoy)}
                            {--sin-recetas : No recalcula el costo de recetas}
                            {--sin-mail : No envía correo}';

    protected $description = 'Audita desfasajes de costo de compra (precio, UM, coeficientes, TRA) y recetas con costo ≥ precio';

    public function handle(RecepcionProveedorCostoAuditorService $service): int
    {
        if (! config('recepcion_proveedor.auditoria_costos_diaria.habilitada', true)) {
            $this->warn('Auditoría deshabilitada (recepcion_proveedor.auditoria_costos_diaria.habilitada).');

            return self::SUCCESS;
        }

        $desde = trim((string) ($this->option('desde') ?? ''));
        $hasta = trim((string) ($this->option('hasta') ?? ''));
        $enviarMail = ! (bool) $this->option('sin-mail');

        try {
            $informe = $service->ejecutar(
                $desde !== '' ? $desde : null,
                $hasta !== '' ? $hasta : null,
                $enviarMail,
                $this->option('sin-recetas') ? false : null,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Costos de compra · '.$informe['fecha_calendario'].($enviarMail ? '' : ' · sin mail'));
        $this->table(['Concepto', 'Cantidad'], [
            ['Líneas de recepción', (string) $informe['lineas_auditadas']],
            ['Líneas de transferencia', (string) $informe['transferencias_auditadas']],
            ['Recetas', (string) $informe['recetas_auditadas']],
            ['Desfasajes', (string) count($informe['hallazgos'])],
            ['Recetas con costo ≥ precio', (string) count($informe['recetas'])],
            ['Errores', (string) count($informe['errores'])],
        ]);

        foreach (array_slice($informe['hallazgos'], 0, 40) as $fila) {
            $this->warn(sprintf('[%s] %s | %s | %s | %s', $fila['tipo'], $fila['documento'], $fila['sku'], $fila['detalle'], $fila['pista']));
        }
        foreach ($informe['recetas'] as $receta) {
            $this->warn(sprintf(
                '[Receta] %s %s | costo %s ≥ precio %s',
                $receta['codigo'],
                $receta['nombre'],
                number_format($receta['costo'], 2, ',', '.'),
                number_format($receta['precio'], 2, ',', '.'),
            ));
        }
        foreach ($informe['errores'] as $err) {
            $this->error($err['paso'].': '.$err['mensaje']);
        }

        if ($informe['mail_enviado']) {
            $this->info('Correo enviado a '.$informe['mail_destino']);
        } elseif ($informe['mail_error']) {
            $this->error('Mail: '.$informe['mail_error']);
        }

        return $informe['requiere_alerta'] ? self::FAILURE : self::SUCCESS;
    }
}
