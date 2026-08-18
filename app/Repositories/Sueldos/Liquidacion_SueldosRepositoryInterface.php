<?php

namespace App\Repositories\Sueldos;

interface Liquidacion_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function leeLiquidacion($filtros, $flPaginando = null);

    public function proximoNumero(int $empresaId): int;

    public function cambiarEstado(int $id, string $estado, ?int $usuarioId = null);

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Sueldos\Liquidacion_Sueldos>
     */
    public function listadoParaConsulta(string $consulta = '', ?int $empresaId = null);

    public function findPorNumero(int $numero, ?int $empresaId = null): ?\App\Models\Sueldos\Liquidacion_Sueldos;

    public function findParaConsulta(int $id, ?int $empresaId = null): ?\App\Models\Sueldos\Liquidacion_Sueldos;
}
