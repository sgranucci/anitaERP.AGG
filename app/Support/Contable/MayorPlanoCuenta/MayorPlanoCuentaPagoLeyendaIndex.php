<?php

namespace App\Support\Contable\MayorPlanoCuenta;

/**
 * Índice de pag_leyenda (che_ban.pago) — equivalente a busca_op() en l-mayor.c.
 */
class MayorPlanoCuentaPagoLeyendaIndex
{
    /** @var array<string, string> */
    private array $porClave = [];

    /**
     * @param  list<object>  $filasPago
     */
    public static function desdeFilas(array $filasPago): self
    {
        $index = new self();

        foreach ($filasPago as $fila) {
            $tipo = strtoupper(trim((string) ($fila->pag_tipo ?? '')));
            $rec = (int) ($fila->pag_rec ?? 0);
            $sucursal = (int) ($fila->pag_sucursal ?? 0);
            $leyenda = trim((string) ($fila->pag_leyenda ?? ''));

            if ($tipo === '' || $rec <= 0 || ! self::leyendaUtilizable($leyenda)) {
                continue;
            }

            $index->porClave[self::clave($tipo, $sucursal, $rec)] = self::normalizarTexto($leyenda);
        }

        return $index;
    }

    public function leyenda(string $tipo, int $sucursal, int $nro): ?string
    {
        $tipo = strtoupper(trim($tipo));
        if ($tipo === '' || $nro <= 0) {
            return null;
        }

        return $this->porClave[self::clave($tipo, $sucursal, $nro)] ?? null;
    }

    public function cantidadClaves(): int
    {
        return count($this->porClave);
    }

    public static function clave(string $tipo, int $sucursal, int $nro): string
    {
        return strtoupper(trim($tipo)).'|'.$sucursal.'|'.$nro;
    }

    private static function leyendaUtilizable(string $leyenda): bool
    {
        if ($leyenda === '' || $leyenda === '0') {
            return false;
        }

        return trim($leyenda) !== '';
    }

    private static function normalizarTexto(string $texto): string
    {
        return str_replace('*', ' ', $texto);
    }
}
