<?php

namespace App\Repositories\Admin;

use App\Models\Seguridad\Usuario;
use Illuminate\Support\Collection;

interface UsuarioRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePorUsuarioId($id);

    /**
     * Busca por id numérico o por código de login (columna usuario). Solo usuarios operativos.
     */
    public function findPorIdOCodigo(string $valor, $empresa_id = null);

    public function findOperativo(int $id): ?Usuario;

    /**
     * @param  list<int>  $usuarioIds
     * @return list<int>
     */
    public function filtrarIdsOperativos(array $usuarioIds): array;

    /**
     * @param  list<int>  $usuarioIds
     * @return list<int>
     */
    public function filtrarIdsOperativosPorEmpresa(array $usuarioIds, int $empresaId): array;

    /**
     * Listado para selects y pantallas que eligen un usuario operativo.
     *
     * @param  list<string>  $columnas
     */
    public function listadoOperativoParaSelector(
        ?int $empresaId = null,
        ?int $centrocostoId = null,
        array $columnas = ['id', 'nombre', 'email', 'usuario'],
        bool $soloConEmail = false,
        array $with = [],
    ): Collection;
}
