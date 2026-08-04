<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Recuento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Stock\RecuentoListadoFiltros;
use App\Support\Stock\RecuentoVisibilidadSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RecuentoRepository implements RecuentoRepositoryInterface
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function all()
    {
        return $this->leeRecuentos(RecuentoListadoFiltros::filtrosVacios(), false);
    }

    public function leeRecuentos($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => RecuentoListadoFiltros::MODO_TODOS,
                'campo' => 'codigo',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = RecuentoListadoFiltros::filtrosVacios();
        }

        $query = Recuento::query()
            ->select('recuento.*')
            ->join('depmae', 'depmae.id', '=', 'recuento.deposito_id')
            ->join('empresa', 'empresa.id', '=', 'recuento.empresa_id')
            ->join('usuario', 'usuario.id', '=', 'recuento.usuario_id')
            ->with([
                'deposito:id,codigo,nombre,empresa_id',
                'empresa:id,nombre',
                'usuario:id,nombre',
            ])
            ->withCount('items');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'recuento.empresa_id');

        RecuentoVisibilidadSupport::aplicarFiltroDepositos($query);

        $usuarioId = (int) ($filtros['usuario_id'] ?? 0);
        if ($usuarioId > 0) {
            RecuentoVisibilidadSupport::aplicarFiltroUsuario($query, $usuarioId);
        }

        if (RecuentoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            RecuentoListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('recuento.id');

        return $paginar ? $query->paginate(15)->appends(RecuentoListadoFiltros::paraQueryString($filtros)) : $query->get();
    }

    public function find(int $id)
    {
        $recuento = Recuento::find($id);
        if (! $recuento) {
            throw new ModelNotFoundException('Recuento no encontrado');
        }

        return $recuento;
    }

    public function findConRelaciones(int $id)
    {
        $recuento = Recuento::query()
            ->with([
                'deposito',
                'empresa:id,nombre',
                'usuario:id,nombre',
                'items.articulos:id,sku,descripcion,unidadmedida_id,tipoarticulo_id,maneja_stock_color_talle',
                'items.articulos.tipoarticulos:id,nombre,abreviatura',
                'items.articulos.unidadesdemedidas:id,abreviatura,nombre',
                'items.unidadmedida:id,abreviatura,nombre',
                'items.color:id,nombre',
                'items.talle:id,nombre',
                'estados.usuarios:id,nombre',
                'archivos',
                'movimientoCierre',
                'movimientoAnulacion',
            ])
            ->find($id);

        if (! $recuento) {
            throw new ModelNotFoundException('Recuento no encontrado');
        }

        return $recuento;
    }

    public function create(array $data)
    {
        return Recuento::create($data);
    }

    public function update(int $id, array $data)
    {
        $recuento = $this->find($id);
        $recuento->fill($data)->save();

        return $recuento;
    }

    public function delete(int $id): bool
    {
        $recuento = Recuento::find($id);
        if (! $recuento) {
            return false;
        }

        return (bool) $recuento->delete();
    }
}
