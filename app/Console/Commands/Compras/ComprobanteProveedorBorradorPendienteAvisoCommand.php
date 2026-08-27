<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\ComprobanteProveedorBorradorPendienteAvisoService;
use App\Support\Compras\ComprobanteProveedorBorradorPendienteSupport;
use Illuminate\Console\Command;

class ComprobanteProveedorBorradorPendienteAvisoCommand extends Command
{
    protected $signature = 'compras:avisar-comprobantes-borrador
                            {--sin-mail : Lista las facturas y no envía correo}';

    protected $description = 'Avisa a cuentas a pagar (módulo de avisos) las facturas de proveedor en BORRADOR sin contabilizar';

    public function handle(ComprobanteProveedorBorradorPendienteAvisoService $service): int
    {
        if (! config('compras.factura_borrador_aviso.habilitado', true)) {
            $this->warn('Aviso deshabilitado (compras.factura_borrador_aviso.habilitado).');

            return self::SUCCESS;
        }

        $enviarMail = ! (bool) $this->option('sin-mail');
        $digest = ComprobanteProveedorBorradorPendienteSupport::recopilar();
        $this->line(sprintf(
            'Facturas en BORRADOR: %d · %s%s',
            (int) ($digest['cantidad'] ?? 0),
            (string) ($digest['fecha'] ?? ''),
            $enviarMail ? '' : ' · sin mail',
        ));

        foreach (array_slice($digest['facturas'] ?? [], 0, 20) as $fila) {
            $this->warn(sprintf(
                '%s %s | %s | $ %s | %s',
                (string) ($fila['empresa'] ?? ''),
                (string) ($fila['comprobante'] ?? ''),
                (string) ($fila['proveedor'] ?? ''),
                (string) ($fila['total'] ?? '0,00'),
                (string) ($fila['antiguedad'] ?? ''),
            ));
        }

        $resultado = $service->enviarResumen($enviarMail);

        if ($resultado['omitido'] !== null) {
            $this->info('Sin envíos: '.$resultado['omitido']);

            return self::SUCCESS;
        }

        $this->info(($enviarMail ? 'Mails encolados: ' : 'Destinatarios que recibirían mail: ').$resultado['enviados']);

        return self::SUCCESS;
    }
}
