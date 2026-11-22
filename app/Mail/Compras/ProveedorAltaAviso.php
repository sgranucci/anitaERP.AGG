<?php

declare(strict_types=1);

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProveedorAltaAviso extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{codigo: string, nombre: string, cuit: string, usuario: string}  $datos
     */
    public function __construct(public array $datos)
    {
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        $this->subject($codigo !== ''
            ? 'Proveedor '.$codigo.' dado de alta'
            : 'Proveedor dado de alta');
    }

    public function build(): self
    {
        return $this->view('mails.compras.proveedor_alta_aviso');
    }
}
