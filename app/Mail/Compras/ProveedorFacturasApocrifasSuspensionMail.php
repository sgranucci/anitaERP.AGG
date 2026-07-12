<?php

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProveedorFacturasApocrifasSuspensionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $informe
     */
    public function __construct(
        public array $informe,
    ) {}

    public function build(): self
    {
        $desde = (string) ($this->informe['desde'] ?? '');
        $hasta = (string) ($this->informe['hasta'] ?? '');
        $suspendidosProveedores = count($this->informe['proveedores_suspendidos'] ?? []);
        $suspendidosClientes = count($this->informe['clientes_suspendidos'] ?? []);
        $totalSuspendidos = $suspendidosProveedores + $suspendidosClientes;

        $asunto = sprintf(
            '[%s] Facturas apócrifas ARCA — %d suspendido(s) (P:%d C:%d) — %s→%s',
            config('app.name', 'anitaERP'),
            $totalSuspendidos,
            $suspendidosProveedores,
            $suspendidosClientes,
            $desde,
            $hasta,
        );

        return $this->subject($asunto)
            ->view('mails.compras.proveedor_facturas_apocrifas_suspension');
    }
}
