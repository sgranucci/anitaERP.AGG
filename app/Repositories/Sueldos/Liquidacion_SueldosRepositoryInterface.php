<?php

namespace App\Repositories\Sueldos;

interface Liquidacion_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function leeLiquidacion($filtros, $flPaginando = null);

    public function proximoNumero(int $empresaId): int;

    public function cambiarEstado(int $id, string $estado, ?int $usuarioId = null);

    /**
     * @param  list<int>|null  $empresaIds
     * @return \Illuminate\Support\Collection<int, \App\Models\Sueldos\Liquidacion_Sueldos>
     */
    public function listadoParaConsulta(string $consulta = '', ?int $empresaId = null, ?array $empresaIds = null);

    public function findPorNumero(int $numero, ?int $empresaId = null): ?\App\Models\Sueldos\Liquidacion_Sueldos;

    public function contarPorNumero(int $numero, ?int $empresaId = null): int;

    public function findParaConsulta(int $id, ?int $empresaId = null): ?\App\Models\Sueldos\Liquidacion_Sueldos;
}
