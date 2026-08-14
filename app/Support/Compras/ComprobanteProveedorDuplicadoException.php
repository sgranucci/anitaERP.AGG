<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use RuntimeException;

/**
 * Factura ya registrada (misma clave fiscal). El alta debe redirigir al existente.
 */
final class ComprobanteProveedorDuplicadoException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $comprobanteId,
        private readonly bool $estaEliminado = false,
    ) {
        parent::__construct($message);
    }

    public static function desdeExistente(Comprobante_Proveedor $existente, ?string $codigoAfip = null): self
    {
        $eliminado = $existente->trashed();

        return new self(
            ComprobanteProveedorUnicidadSupport::mensajeDuplicado($existente, $codigoAfip),
            (int) $existente->id,
            $eliminado,
        );
    }

    public function comprobanteId(): int
    {
        return $this->comprobanteId;
    }

    public function estaEliminado(): bool
    {
        return $this->estaEliminado;
    }

    public function puedeAbrirEdicion(): bool
    {
        return $this->comprobanteId > 0 && ! $this->estaEliminado;
    }
}
