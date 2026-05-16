<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

/**
 * Clave compuesta de OC en Anita (tipo, letra, sucursal, nro).
 */
final class AnitaOcClave
{
    public function __construct(
        public readonly string $tipo,
        public readonly string $letra,
        public readonly int $sucursal,
        public readonly int $nro,
    ) {
    }

    public static function desdePendmaep(object $row): self
    {
        return new self(
            trim((string) ($row->penmp_tipo ?? '')),
            trim((string) ($row->penmp_letra ?? '')),
            (int) ($row->penmp_sucursal ?? 0),
            (int) ($row->penmp_nro ?? 0),
        );
    }

    public function wherePendmaep(): string
    {
        return $this->whereConPrefijo('penmp');
    }

    public function wherePendmovp(): string
    {
        return $this->whereConPrefijo('penvp');
    }

    public function whereOccuota(): string
    {
        return $this->whereConPrefijo('occ');
    }

    public function whereOcvley(): string
    {
        return $this->whereConPrefijo('ocvl');
    }

    public function whereMovpresup(): string
    {
        return $this->whereConPrefijo('movp');
    }

    private function whereConPrefijo(string $p): string
    {
        $tipo = addslashes($this->tipo);
        $letra = addslashes($this->letra);

        return " WHERE {$p}_tipo='{$tipo}' AND {$p}_letra='{$letra}'"
            ." AND {$p}_sucursal={$this->sucursal} AND {$p}_nro={$this->nro}";
    }
}
