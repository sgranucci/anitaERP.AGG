<?php

declare(strict_types=1);

namespace App\Repositories\Ventas;

use App\Models\Ventas\Contrato_Venta;
use Illuminate\Support\Collection;

interface Contrato_VentaRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|Collection<int, Contrato_Venta>
     */
    public function leeContratoVenta($filtros, bool $paginar = false);

    public function findPorCodigo(string $codigo, ?int $empresaId = null): ?Contrato_Venta;

    /**
     * @param  list<array{clave?: string, valor?: string|null}>  $filas
     */
    public function sincronizarDatos(int $contratoId, array $filas): void;

    /**
     * @return Collection<int, Contrato_Venta>
     */
    public function listadoActivosParaConsulta(string $texto, ?int $empresaId = null);

    /**
     * Abonos vigentes en la fecha que aún no tienen el período facturado.
     *
     * @return Collection<int, Contrato_Venta>
     */
    public function listadoColaFacturacion(
        ?int $empresaId,
        ?int $clienteId,
        string $fechaYmd,
        bool $soloPendientes = true
    );
}
