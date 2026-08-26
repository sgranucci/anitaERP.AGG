<?php

namespace App\Support\Compras;

use RuntimeException;

/**
 * La factura ya existe en Anita (tabla compra). No se puede repetir desde el ERP.
 */
final class ComprobanteProveedorYaExistenteEnAnitaException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $fila
     */
    public function __construct(
        string $message,
        private readonly array $fila = [],
        private readonly ?int $nroInterno = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function desdeFila(
        array $fila,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?string $tipoArca = null,
    ): self {
        $nroInterno = (int) ($fila['com_nro_interno'] ?? 0);

        return new self(
            ComprobanteProveedorAnitaCompraExistenciaSupport::mensajeDuplicado(
                $fila,
                $letra,
                $sucursal,
                $numerocomprobante,
                $tipoArca,
            ),
            $fila,
            $nroInterno > 0 ? $nroInterno : null,
        );
    }

    /** @return array<string, mixed> */
    public function fila(): array
    {
        return $this->fila;
    }

    public function nroInterno(): ?int
    {
        return $this->nroInterno;
    }
}
