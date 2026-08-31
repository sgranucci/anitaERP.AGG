<?php

declare(strict_types=1);

namespace App\Repositories\Ventas;

interface Concepto_VentaRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeConceptoVenta($filtros, bool $paginar = false);

    /**
     * Reemplaza solo las cuentas de empresas asignadas al usuario.
     * Sin asignación (acceso total) sincroniza todas.
     *
     * @param  list<array<string, mixed>>  $filas
     */
    public function sincronizarCuentas(int $conceptoId, array $filas): void;

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function sincronizarPrecios(int $conceptoId, array $filas): void;

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function sincronizarTags(int $conceptoId, array $filas): void;

    public function findPorCodigo(string $codigo): ?\App\Models\Ventas\Concepto_Venta;

    public function findPorCodigoAnita(int $codigoAnita): ?\App\Models\Ventas\Concepto_Venta;

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Ventas\Concepto_Venta>
     */
    public function listadoActivosParaConsulta(string $texto);
}
