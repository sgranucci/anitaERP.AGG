<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Ordencompra;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Support\Compras\OrdencompraTotalesCabecera;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrdencompraRepository implements OrdencompraRepositoryInterface
{
    public function __construct(
        private Ordencompra $model,
        private CotizacionQueryInterface $cotizacionQuery,
    ) {
    }

    public function create(array $data)
    {
        $data = self::limpiaPayloadCabecera($data);
        $data['numeroordencompra'] = self::siguienteNumero();

        return $this->model->create($data);
    }

    /**
     * Alta desde Anita: id secuencial (auto); numeroordencompra = penmp_nro en $data.
     */
    public function createDesdeAnita(array $data)
    {
        unset($data['id']);

        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $data = self::limpiaPayloadCabecera($data);

        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id) > 0;
    }

    public function find($id)
    {
        $oc = $this->model->with([
            'empresas', 'centrocostos', 'proveedores', 'requisiciones', 'usuarios', 'sector_legajocompras',
            'condicioncompras', 'condicionentregas', 'transportes',
            'ordencompra_articulos.articulos', 'ordencompra_articulos.monedas', 'ordencompra_articulos.centrocostos_destino',
            'ordencompra_articulos.partidagastos.articulos', 'ordencompra_articulos.capexs',
            'ordencompra_comprobantes.monedas', 'ordencompra_comprobantes.condicionpagos',
            'ordencompra_comprobantes.ordencompra_comprobante_cuotas.monedas',
            'ordencompra_comprobantes.ordencompra_comprobante_cuotas.formapagos',
            'ordencompra_estados.usuarios',
            'ordencompra_archivos',
        ])->find($id);
        if (! $oc) {
            throw new ModelNotFoundException('Orden de compra no encontrada');
        }

        return $oc;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }

    public function existeRegistro(): bool
    {
        return $this->model->query()->exists();
    }

    public function listadoIndex(?string $busqueda, ?int $sectorUsuarioId, bool $paginar = false)
    {
        $q = $this->queryListadoIndex($busqueda, $sectorUsuarioId);

        return $paginar ? $q->paginate(10) : $q->get();
    }

    public function listadoIndexCursor(?string $busqueda, ?int $sectorUsuarioId)
    {
        return $this->queryListadoIndex($busqueda, $sectorUsuarioId)->cursor();
    }

    public function listadoExport(?string $busqueda, ?int $sectorUsuarioId): Collection
    {
        $collection = $this->queryListadoExport($busqueda, $sectorUsuarioId)
            ->with([
                'ordencompra_articulos.articulos',
                'ordencompra_articulos.monedas',
                'ordencompra_articulos.centrocostos_destino',
                'ordencompra_articulos.partidagastos.articulos',
                'ordencompra_articulos.capexs',
            ])
            ->get();

        foreach ($collection as $oc) {
            OrdencompraTotalesCabecera::aplicarAtributosVirtuales($oc, $this->cotizacionQuery);
        }

        return $collection;
    }

    public function listadoExportCursor(?string $busqueda, ?int $sectorUsuarioId)
    {
        return $this->queryListadoExport($busqueda, $sectorUsuarioId)->cursor();
    }

    private function queryListadoIndex(?string $busqueda, ?int $sectorUsuarioId)
    {
        $q = $this->model->query()
            ->select([
                'ordencompra.id',
                'ordencompra.numeroordencompra',
                'ordencompra.fecha',
                'ordencompra.estadoordencompra',
                'ordencompra.requisicion_id',
                'ordencompra.sector_legajocompra_id',
                'ordencompra.creousuario_id',
                'empresa.nombre as nombreempresa',
                'centrocosto.nombre as nombrecentrocosto',
                'proveedor.nombre as nombreproveedor',
                'usuario.nombre as nombreusuario',
                'sector_legajocompra.nombre as nombresector',
                DB::raw('(select coalesce(sum(oa.cantidad * oa.precio * ifnull(oa.cotizacion, 1)), 0) from ordencompra_articulo oa where oa.ordencompra_id = ordencompra.id) as monto_lineas'),
            ])
            ->leftJoin('empresa', 'empresa.id', '=', 'ordencompra.empresa_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'ordencompra.centrocosto_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'ordencompra.proveedor_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'ordencompra.creousuario_id')
            ->leftJoin('sector_legajocompra', 'sector_legajocompra.id', '=', 'ordencompra.sector_legajocompra_id')
            ->orderByDesc('ordencompra.fecha')
            ->orderByDesc('ordencompra.id');

        $this->aplicarFiltrosListado($q, $busqueda, $sectorUsuarioId);

        return $q;
    }

    private function queryListadoExport(?string $busqueda, ?int $sectorUsuarioId)
    {
        $select = [
            'ordencompra.id',
            'ordencompra.numeroordencompra',
            'ordencompra.fecha',
            'ordencompra.fechaentrega',
            'ordencompra.estadoordencompra',
            'ordencompra.requisicion_id',
            'ordencompra.comentario',
            'ordencompra.detalle',
            'ordencompra.tratamiento',
            'empresa.nombre as nombreempresa',
            'centrocosto.codigo as codigocentrocosto',
            'centrocosto.nombre as nombrecentrocosto',
            'proveedor.codigo as codigoproveedor',
            'proveedor.nombre as nombreproveedor',
            'usuario.nombre as nombreusuario',
            'sector_legajocompra.nombre as nombresector',
            'condicioncompra.nombre as nombrecondicioncompra',
            'requisicion.numerorequisicion',
            'requisicion.motivotratamiento',
            'requisicion.contrataciondirecta',
        ];

        if (Schema::hasColumn('requisicion', 'nroinscripcion')) {
            $select[] = 'requisicion.nroinscripcion as nroinscripcion';
        }

        $q = $this->model->query()
            ->select($select)
            ->leftJoin('empresa', 'empresa.id', '=', 'ordencompra.empresa_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'ordencompra.centrocosto_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'ordencompra.proveedor_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'ordencompra.creousuario_id')
            ->leftJoin('sector_legajocompra', 'sector_legajocompra.id', '=', 'ordencompra.sector_legajocompra_id')
            ->leftJoin('condicioncompra', 'condicioncompra.id', '=', 'ordencompra.condicioncompra_id')
            ->leftJoin('requisicion', 'requisicion.id', '=', 'ordencompra.requisicion_id')
            ->orderByDesc('ordencompra.fecha')
            ->orderByDesc('ordencompra.id');

        $this->aplicarFiltrosListado($q, $busqueda, $sectorUsuarioId, true);

        return $q;
    }

    private function aplicarFiltrosListado($q, ?string $busqueda, ?int $sectorUsuarioId, bool $export = false): void
    {
        if ($sectorUsuarioId !== null && $sectorUsuarioId > 0) {
            $q->where('ordencompra.sector_legajocompra_id', $sectorUsuarioId);
        }

        if ($busqueda === null || $busqueda === '') {
            return;
        }

        $b = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($busqueda)).'%';
        $q->where(function ($w) use ($b, $export) {
            $w->where('ordencompra.numeroordencompra', 'like', $b)
                ->orWhere('ordencompra.comentario', 'like', $b)
                ->orWhere('ordencompra.detalle', 'like', $b)
                ->orWhere('ordencompra.estadoordencompra', 'like', $b)
                ->orWhere('ordencompra.tratamiento', 'like', $b)
                ->orWhere('proveedor.nombre', 'like', $b)
                ->orWhere('proveedor.codigo', 'like', $b)
                ->orWhere('empresa.nombre', 'like', $b)
                ->orWhere('centrocosto.nombre', 'like', $b)
                ->orWhere('centrocosto.codigo', 'like', $b)
                ->orWhere('usuario.nombre', 'like', $b);

            if ($export) {
                $w->orWhere('sector_legajocompra.nombre', 'like', $b)
                    ->orWhere('condicioncompra.nombre', 'like', $b)
                    ->orWhere('requisicion.numerorequisicion', 'like', $b)
                    ->orWhere('requisicion.motivotratamiento', 'like', $b)
                    ->orWhere('requisicion.contrataciondirecta', 'like', $b);

                if (Schema::hasColumn('requisicion', 'nroinscripcion')) {
                    $w->orWhere('requisicion.nroinscripcion', 'like', $b);
                }
            }
        });
    }

    public function proximoNumeroOrdencompra(): int
    {
        return self::siguienteNumero();
    }

    private static function limpiaPayloadCabecera(array $data): array
    {
        unset(
            $data['articulo_ids'],
            $data['cantidades'],
            $data['precios'],
            $data['moneda_linea_ids'],
            $data['fechaentrega_articulos'],
            $data['cantidadalternativas'],
            $data['detalle_articulos'],
            $data['centrocostodestino_ids'],
            $data['partidagasto_ids'],
            $data['capex_ids'],
            $data['ordencompra_articulo_ids'],
            $data['fechas'],
            $data['estados'],
            $data['usuario_ids'],
            $data['observacionestados'],
            $data['comprobantes_json'],
            $data['_token'],
            $data['_method'],
        );

        return $data;
    }

    private static function siguienteNumero(): int
    {
        $ultimo = DB::table('ordencompra')->max('numeroordencompra');

        return $ultimo ? ((int) $ultimo + 1) : 1;
    }
}
