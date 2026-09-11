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

        RequisicionListadoFiltros::aplicar($q, $filtros);

        $q->orderBy('requisicion.fecha', 'desc')->orderBy('requisicion.id', 'desc');

        if ($withArticulos) {
            $q->with([
                'requisicion_articulos.articulos',
                'requisicion_articulos.monedas',
                'requisicion_articulos.centrocostos_destino',
                'requisicion_articulos.partidagastos.articulos',
                'requisicion_articulos.capexs',
                'requisicion_articulos.color',
                'requisicion_articulos.talle',
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

    /**
     * Conteos por estado con los mismos filtros del listado, excepto el chip de estado.
     *
     * @param  array<string, mixed>|string|null  $filtros
     * @return array{total: int, por_estado: array<string, int>, provisorio: int, pendiente: int, en_compras: int, en_arbol: int, aprobada: int, genero_oc: int, cumplida: int, suspendida: int}
     */
    public function resumenIndex($filtros): array
    {
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

        $filtros['estado'] = '';

        $nombres = [];
        foreach (Requisicion_Estado::$enumEstado as $row) {
            $nombres[] = (string) ($row['nombre'] ?? '');
        }
        $porEstado = array_fill_keys(array_filter($nombres), 0);
        $total = 0;

        $q = $this->model->query()
            ->selectRaw('requisicion.estado as estado, COUNT(*) as cantidad')
            ->join('empresa', 'empresa.id', '=', 'requisicion.empresa_id')
            ->join('centrocosto', 'centrocosto.id', '=', 'requisicion.centrocosto_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'requisicion.proveedor_id')
            ->leftJoin('oficinacompra', 'oficinacompra.id', '=', 'requisicion.oficinacompra_id')
            ->leftJoin('formapago', 'formapago.id', '=', 'requisicion.formapago_id')
            ->join('usuario', 'usuario.id', '=', 'requisicion.creousuario_id');

        RequisicionVisibilidadSupport::aplicarFiltroListado($q);
        RequisicionListadoFiltros::aplicar($q, $filtros);
        $q->reorder();

        foreach ($q->groupBy('requisicion.estado')->get() as $row) {
            $estado = (string) ($row->estado ?? '');
            $n = (int) $row->cantidad;
            if ($estado === 'GENERO OC') {
                $estado = 'GENERO ORDEN COMPRA';
            }
            if ($estado !== '' && array_key_exists($estado, $porEstado)) {
                $porEstado[$estado] = ($porEstado[$estado] ?? 0) + $n;
            } elseif ($estado !== '') {
                $porEstado[$estado] = ($porEstado[$estado] ?? 0) + $n;
            }
            $total += $n;
        }

        $nombre = static function (string $codigo) {
            $idx = array_search($codigo, array_column(Requisicion_Estado::$enumEstado, 'valor'), true);

            return $idx === false ? '' : (Requisicion_Estado::$enumEstado[$idx]['nombre'] ?? '');
        };

        return [
            'total' => $total,
            'por_estado' => $porEstado,
            'provisorio' => (int) ($porEstado[$nombre('V')] ?? 0),
            'pendiente' => (int) ($porEstado[$nombre('P')] ?? 0),
            'en_compras' => (int) ($porEstado[$nombre('K')] ?? 0),
            'en_arbol' => (int) ($porEstado[$nombre('R')] ?? 0),
            'aprobada' => (int) ($porEstado[$nombre('A')] ?? 0),
            'genero_oc' => (int) ($porEstado[$nombre('O')] ?? 0),
            'cumplida' => (int) ($porEstado[$nombre('C')] ?? 0),
            'suspendida' => (int) ($porEstado[$nombre('S')] ?? 0),
        ];
    }

    private function enriquecerTotalesCabecera(Collection $requisiciones): void
    {
        foreach ($requisiciones as $req) {
            RequisicionTotalesCabecera::aplicarAtributosVirtuales($req, $this->cotizacionQuery);
        }
    }
}
