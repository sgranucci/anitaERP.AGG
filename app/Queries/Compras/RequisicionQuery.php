<?php

namespace App\Queries\Compras;

use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Estado;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\RequisicionLineasOcSupport;
use App\Support\Compras\RequisicionListadoFiltros;
use App\Support\Compras\RequisicionTotalesCabecera;
use App\Support\Compras\RequisicionVisibilidadSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RequisicionQuery implements RequisicionQueryInterface
{
    protected $model;

    protected $empresaRepository;

    protected CotizacionQueryInterface $cotizacionQuery;

    public function __construct(
        Requisicion $model,
        EmpresaRepositoryInterface $empresaRepository,
        CotizacionQueryInterface $cotizacionQuery,
    ) {
        $this->model = $model;
        $this->empresaRepository = $empresaRepository;
        $this->cotizacionQuery = $cotizacionQuery;
    }

    public function first()
    {
        return $this->model->first();
    }

    public function requisicionAccesiblePorUsuario(int $id): bool
    {
        return RequisicionVisibilidadSupport::requisicionAccesiblePorId($id);
    }

    public function puedeUsuarioGenerarMultiplesOcDesdeRequisicion(Requisicion $r): bool
    {
        if (! can('crear-ordencompra', false)) {
            return false;
        }
        if (! $this->requisicionAccesiblePorUsuario((int) $r->id)) {
            return false;
        }
        $aprobada = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'), true)]['nombre'];
        $generoOc = Requisicion_Estado::$enumEstado[array_search('O', array_column(Requisicion_Estado::$enumEstado, 'valor'), true)]['nombre'];
        $estado = (string) ($r->estado ?? '');
        $estadoPermitido = $estado === $aprobada
            || $estado === $generoOc
            || $estado === 'GENERO OC';
        if (! $estadoPermitido) {
            return false;
        }

        return RequisicionLineasOcSupport::cuentaPendientesOc((int) $r->id) > 0;
    }

    public function leeRequisicion($filtros, $flPaginando = null, $withArticulos = false)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => RequisicionListadoFiltros::MODO_TODOS,
                'campo' => 'numerorequisicion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = RequisicionListadoFiltros::filtrosVacios();
        }

        $select = [
            'requisicion.id as id',
            'requisicion.fecha as fecha',
            'requisicion.fechaentrega as fechaentrega',
            'requisicion.numerorequisicion as numerorequisicion',
            'empresa.nombre as nombreempresa',
            'requisicion.tratamiento as tratamiento',
            'requisicion.motivotratamiento as motivotratamiento',
            'requisicion.contrataciondirecta as contrataciondirecta',
            'centrocosto.codigo as codigocentrocosto',
            'centrocosto.nombre as nombrecentrocosto',
            'requisicion.comentario as comentario',
            'requisicion.estado as estado',
            'usuario.nombre as nombreusuario',
            'requisicion.detalle as detalle',
            'proveedor.codigo as codigoproveedor',
            'proveedor.nombre as nombreproveedor',
            'oficinacompra.nombre as nombreoficinacompra',
            'formapago.nombre as nombreformapago',
        ];

        if (Schema::hasColumn($this->model->getTable(), 'nroinscripcion')) {
            $select[] = 'requisicion.nroinscripcion as nroinscripcion';
        }

        $select[] = DB::raw('(SELECT COUNT(*) FROM ordencompra WHERE ordencompra.requisicion_id = requisicion.id) AS ordencompra_vinculadas_count');

        $q = $this->model->select($select)
            ->join('empresa', 'empresa.id', '=', 'requisicion.empresa_id')
            ->join('centrocosto', 'centrocosto.id', '=', 'requisicion.centrocosto_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'requisicion.proveedor_id')
            ->leftJoin('oficinacompra', 'oficinacompra.id', '=', 'requisicion.oficinacompra_id')
            ->leftJoin('formapago', 'formapago.id', '=', 'requisicion.formapago_id')
            ->join('usuario', 'usuario.id', '=', 'requisicion.creousuario_id');

        RequisicionVisibilidadSupport::aplicarFiltroListado($q);

        if (RequisicionListadoFiltros::tieneCriteriosAplicados($filtros)) {
            RequisicionListadoFiltros::aplicar($q, $filtros);
        }

        $q->orderBy('requisicion.fecha', 'desc')->orderBy('requisicion.id', 'desc');

        if ($withArticulos) {
            $q->with([
                'requisicion_articulos.articulos',
                'requisicion_articulos.monedas',
                'requisicion_articulos.centrocostos_destino',
                'requisicion_articulos.partidagastos.articulos',
                'requisicion_articulos.capexs',
            ]);
        }

        if ($flPaginando) {
            $paginator = $q->paginate(10);
            if ($withArticulos) {
                $this->enriquecerTotalesCabecera($paginator->getCollection());
            }

            return $paginator;
        }

        $collection = $q->get();
        if ($withArticulos) {
            $this->enriquecerTotalesCabecera($collection);
        }

        return $collection;
    }

    private function enriquecerTotalesCabecera(Collection $requisiciones): void
    {
        foreach ($requisiciones as $req) {
            RequisicionTotalesCabecera::aplicarAtributosVirtuales($req, $this->cotizacionQuery);
        }
    }
}
