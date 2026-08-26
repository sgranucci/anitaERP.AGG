<?php

namespace App\Services\Compras;

use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Presupuesto;
use App\Models\Compras\Requisicion_Presupuesto_Archivo;
use App\Models\Compras\Requisicion_Presupuesto_Articulo;
use App\Repositories\Compras\Requisicion_Presupuesto_ArchivoRepositoryInterface;
use App\Repositories\Compras\Requisicion_Presupuesto_ArticuloRepositoryInterface;
use App\Repositories\Compras\Requisicion_PresupuestoRepositoryInterface;
use App\Repositories\Compras\RequisicionRepositoryInterface;
use App\Support\Archivos\ArchivoAdjuntoCacheSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RequisicionPresupuestoService
{
    private $requisicionRepository;

    private $presupuestoRepository;

    private $presupuestoArticuloRepository;

    private $presupuestoArchivoRepository;

    public function __construct(
        RequisicionRepositoryInterface $requisicionRepository,
        Requisicion_PresupuestoRepositoryInterface $presupuestoRepository,
        Requisicion_Presupuesto_ArticuloRepositoryInterface $presupuestoArticuloRepository,
        Requisicion_Presupuesto_ArchivoRepositoryInterface $presupuestoArchivoRepository
    ) {
        $this->requisicionRepository = $requisicionRepository;
        $this->presupuestoRepository = $presupuestoRepository;
        $this->presupuestoArticuloRepository = $presupuestoArticuloRepository;
        $this->presupuestoArchivoRepository = $presupuestoArchivoRepository;
    }

    public function directorioPresupuesto(int $requisicionId, int $presupuestoId): string
    {
        return public_path('/storage/archivos/requisiciones/'.$requisicionId.'/presupuestos/'.$presupuestoId);
    }

    /**
     * @return array<string,mixed>
     */
    public function listarParaRequisicion(int $requisicionId): array
    {
        $items = $this->presupuestoRepository->listarCabecerasPorRequisicion($requisicionId);

        return $items->map(function (Requisicion_Presupuesto $p) use ($requisicionId) {
            return $this->serializaCabecera($p, $requisicionId);
        })->values()->all();
    }

    /**
     * Líneas de la requisición para armar o comparar cotizaciones de proveedor.
     *
     * @return list<array<string,mixed>>
     */
    public function lineasBaseRequisicion(int $requisicionId): array
    {
        $req = $this->requisicionRepository->find($requisicionId);

        return $req->requisicion_articulos->map(function ($ra) {
            $art = $ra->articulos;
            $mon = $ra->monedas;

            return [
                'requisicion_articulo_id' => $ra->id,
                'articulo_codigo' => $art ? $art->sku : '',
                'articulo_descripcion' => $art ? $art->descripcion : '',
                'cantidad' => (float) $ra->cantidad,
                'precio_requisicion' => (float) $ra->precio,
                'moneda_abreviatura' => $mon ? $mon->abreviatura : '',
            ];
        })->values()->all();
    }

    /**
     * @return array<string,mixed>
     */
    public function obtenerDetalle(int $requisicionId, int $presupuestoId): ?array
    {
        $p = $this->presupuestoRepository->findDetalle($requisicionId, $presupuestoId);

        if (! $p) {
            return null;
        }

        $cabecera = $this->serializaCabecera($p, $requisicionId);
        $cabecera['lineas_requisicion'] = $this->lineasBaseRequisicion($requisicionId);
        $cabecera['articulos'] = $p->requisicion_presupuesto_articulos->map(function (Requisicion_Presupuesto_Articulo $linea) {
            $ra = $linea->requisicion_articulo;
            $art = $ra ? $ra->articulos : null;

            return [
                'id' => $linea->id,
                'requisicion_articulo_id' => $linea->requisicion_articulo_id,
                'precio_unitario' => (float) $linea->precio_unitario,
                'observacion' => $linea->observacion ?? '',
                'articulo_codigo' => $art ? $art->sku : '',
                'articulo_descripcion' => $art ? $art->descripcion : '',
                'cantidad_requisicion' => $ra ? (float) $ra->cantidad : null,
                'precio_requisicion' => $ra ? (float) $ra->precio : null,
                'moneda_abreviatura' => $ra && $ra->monedas ? $ra->monedas->abreviatura : '',
            ];
        })->values()->all();

        return $cabecera;
    }

    /**
     * @return array<string,mixed>
     */
    private function serializaCabecera(Requisicion_Presupuesto $p, int $requisicionId): array
    {
        $prov = $p->proveedores;
        $ce = $p->condicionentregas;
        $cc = $p->condicioncompras;
        $cp = $p->condicionpagos;

        return [
            'id' => $p->id,
            'num_lineas_cotizadas' => (int) ($p->requisicion_presupuesto_articulos_count ?? 0),
            'requisicion_id' => $p->requisicion_id,
            'fecha' => $p->fecha ? substr((string) $p->fecha, 0, 10) : null,
            'condicionentrega_id' => $p->condicionentrega_id,
            'condicioncompra_id' => $p->condicioncompra_id,
            'condicionpago_id' => $p->condicionpago_id,
            'condicionentrega_nombre' => $ce?->nombre,
            'condicioncompra_nombre' => $cc?->nombre,
            'condicionpago_nombre' => $cp?->nombre,
            'proveedor_id' => $p->proveedor_id,
            'proveedor_codigo' => $prov ? $prov->codigo : '',
            'proveedor_nombre' => $prov ? $prov->nombre : '',
            'estado' => $p->estado,
            'archivos' => $p->requisicion_presupuesto_archivos
                ? $p->requisicion_presupuesto_archivos->map(function (Requisicion_Presupuesto_Archivo $a) use ($requisicionId, $p) {
                    $urlVer = route('requisicion_presupuesto_archivo_ver', [
                        'requisicion' => $requisicionId,
                        'presupuesto' => $p->id,
                        'archivo' => $a->id,
                    ]);

                    return [
                        'id' => $a->id,
                        'nombrearchivo' => $a->nombrearchivo,
                        'url_ver' => ArchivoAdjuntoCacheSupport::conVersion(
                            $urlVer,
                            $this->rutaFisicaArchivo((int) $requisicionId, (int) $p->id, $a)
                        ),
                    ];
                })->values()->all()
                : [],
        ];
    }

    /**
     * @return array{ok:bool,error?:string,id?:int}
     */
    public function crear(Request $request, int $requisicionId): array
    {
        $req = $this->requisicionRepository->find($requisicionId);

        $lineas = $this->normalizaLineasRequest($request, $req);
        if (is_string($lineas)) {
            return ['ok' => false, 'error' => $lineas];
        }

        DB::beginTransaction();
        try {
            $estado = $request->input('estado');
            $pres = $this->presupuestoRepository->create([
                'requisicion_id' => $requisicionId,
                'fecha' => $request->input('fecha'),
                'condicionentrega_id' => $request->input('condicionentrega_id') ?: null,
                'condicioncompra_id' => $request->input('condicioncompra_id') ?: null,
                'condicionpago_id' => $request->input('condicionpago_id') ?: null,
                'proveedor_id' => (int) $request->input('proveedor_id'),
                'estado' => $estado,
            ]);

            foreach ($lineas as $linea) {
                $this->presupuestoArticuloRepository->create([
                    'requisicion_presupuesto_id' => $pres->id,
                    'requisicion_articulo_id' => $linea['requisicion_articulo_id'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'observacion' => $linea['observacion'] ?? null,
                ]);
            }

            $this->guardaArchivosSubidos($request, $requisicionId, $pres->id);
            $this->ajustaEstadoElegidoUnico($requisicionId, $pres->id, $estado);

            DB::commit();

            return ['ok' => true, 'id' => $pres->id];
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public function actualizar(Request $request, int $requisicionId, int $presupuestoId): array
    {
        $req = $this->requisicionRepository->find($requisicionId);

        $pres = $this->presupuestoRepository->findCabecera($requisicionId, $presupuestoId);
        if (! $pres) {
            return ['ok' => false, 'error' => 'Presupuesto no encontrado.'];
        }

        $lineas = $this->normalizaLineasRequest($request, $req);
        if (is_string($lineas)) {
            return ['ok' => false, 'error' => $lineas];
        }

        DB::beginTransaction();
        try {
            $estado = $request->input('estado');
            $this->presupuestoRepository->updateCabecera($pres, [
                'fecha' => $request->input('fecha'),
                'condicionentrega_id' => $request->input('condicionentrega_id') ?: null,
                'condicioncompra_id' => $request->input('condicioncompra_id') ?: null,
                'condicionpago_id' => $request->input('condicionpago_id') ?: null,
                'proveedor_id' => (int) $request->input('proveedor_id'),
                'estado' => $estado,
            ]);

            $this->presupuestoArticuloRepository->deletePorPresupuesto($pres->id);
            foreach ($lineas as $linea) {
                $this->presupuestoArticuloRepository->create([
                    'requisicion_presupuesto_id' => $pres->id,
                    'requisicion_articulo_id' => $linea['requisicion_articulo_id'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'observacion' => $linea['observacion'] ?? null,
                ]);
            }

            $this->sincronizaArchivos($request, $requisicionId, $pres->id);
            $this->ajustaEstadoElegidoUnico($requisicionId, $pres->id, $estado);

            DB::commit();

            return ['ok' => true];
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public function eliminar(int $requisicionId, int $presupuestoId): array
    {
        $pres = $this->presupuestoRepository->findCabecera($requisicionId, $presupuestoId);
        if (! $pres) {
            return ['ok' => false, 'error' => 'Presupuesto no encontrado.'];
        }

        $dir = $this->directorioPresupuesto($requisicionId, $presupuestoId);
        DB::beginTransaction();
        try {
            $this->presupuestoArticuloRepository->deletePorPresupuesto($pres->id);
            $this->presupuestoArchivoRepository->deletePorPresupuesto($pres->id);
            $this->presupuestoRepository->deleteCabecera($pres);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }

        return ['ok' => true];
    }

    /**
     * @return array<int,array<string,mixed>>|string
     */
    private function normalizaLineasRequest(Request $request, Requisicion $req)
    {
        $ids = $request->input('requisicion_articulo_ids', []);
        $precios = $request->input('precios_unitarios', []);
        $obs = $request->input('observaciones_linea', []);

        if (! is_array($ids) || count($ids) === 0) {
            return 'Debe incluir al menos una línea de artículo del pedido.';
        }

        $permitidos = $req->requisicion_articulos->pluck('id')->flip();
        $out = [];
        foreach ($ids as $i => $rid) {
            $rid = (int) $rid;
            if (! $permitidos->has($rid)) {
                return 'Hay líneas que no pertenecen a esta requisición.';
            }
            $precio = isset($precios[$i]) ? $precios[$i] : null;
            if ($precio === null || $precio === '') {
                return 'Indique precio unitario para todas las líneas.';
            }
            $out[] = [
                'requisicion_articulo_id' => $rid,
                'precio_unitario' => $precio,
                'observacion' => is_array($obs) && isset($obs[$i]) ? $obs[$i] : null,
            ];
        }

        return $out;
    }

    private function ajustaEstadoElegidoUnico(int $requisicionId, int $presupuestoIdActual, string $estado): void
    {
        $elegido = Requisicion_Presupuesto::$enumEstado[array_search('E', array_column(Requisicion_Presupuesto::$enumEstado, 'valor'))]['nombre'];
        if ($estado !== $elegido) {
            return;
        }
        $activo = Requisicion_Presupuesto::$enumEstado[array_search('A', array_column(Requisicion_Presupuesto::$enumEstado, 'valor'))]['nombre'];

        $this->presupuestoRepository->demoteOtrosElegidos($requisicionId, $presupuestoIdActual, $elegido, $activo);
    }

    private function guardaArchivosSubidos(Request $request, int $requisicionId, int $presupuestoId): void
    {
        $archivos = $request->file('archivos_presupuesto');
        if (! $archivos) {
            return;
        }
        $path = $this->directorioPresupuesto($requisicionId, $presupuestoId);
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
        foreach ($archivos as $archivo) {
            if ($archivo) {
                $name = $archivo->getClientOriginalName();
                $archivo->move($path, $name);
                $this->presupuestoArchivoRepository->create([
                    'requisicion_presupuesto_id' => $presupuestoId,
                    'nombrearchivo' => $name,
                ]);
            }
        }
    }

    private function sincronizaArchivos(Request $request, int $requisicionId, int $presupuestoId): void
    {
        $idsConservar = $request->input('archivo_ids_conservar', []);
        if (! is_array($idsConservar)) {
            $idsConservar = [];
        }
        $idsConservar = array_map('intval', $idsConservar);

        $existentes = $this->presupuestoArchivoRepository->listarPorPresupuesto($presupuestoId);

        foreach ($existentes as $ex) {
            if (! in_array((int) $ex->id, $idsConservar, true)) {
                $full = $this->directorioPresupuesto($requisicionId, $presupuestoId).'/'.$ex->nombrearchivo;
                if (is_file($full)) {
                    @unlink($full);
                }
                $this->presupuestoArchivoRepository->deleteArchivo($ex);
            }
        }

        $this->guardaArchivosSubidos($request, $requisicionId, $presupuestoId);
    }

    public function rutaFisicaArchivo(int $requisicionId, int $presupuestoId, Requisicion_Presupuesto_Archivo $archivo): string
    {
        return $this->directorioPresupuesto($requisicionId, $presupuestoId).'/'.$archivo->nombrearchivo;
    }

    /**
     * Marca un presupuesto de la requisición como ELEGIDO y normaliza los demás que estuvieran en ELEGIDO.
     * Usado al generar OC cuando el precio proviene del presupuesto.
     */
    public function marcarComoElegidoParaOc(int $requisicionId, int $presupuestoId): void
    {
        $pres = $this->presupuestoRepository->findCabecera($requisicionId, $presupuestoId);
        if (! $pres) {
            return;
        }
        $activo = Requisicion_Presupuesto::$enumEstado[array_search('A', array_column(Requisicion_Presupuesto::$enumEstado, 'valor'), true)]['nombre'];
        if ($pres->estado !== $activo) {
            return;
        }
        $elegido = Requisicion_Presupuesto::$enumEstado[array_search('E', array_column(Requisicion_Presupuesto::$enumEstado, 'valor'), true)]['nombre'];

        DB::transaction(function () use ($requisicionId, $presupuestoId, $pres, $elegido, $activo) {
            $this->presupuestoRepository->demoteOtrosElegidos($requisicionId, $presupuestoId, $elegido, $activo);
            if ($pres->estado !== $elegido) {
                $this->presupuestoRepository->updateCabecera($pres, ['estado' => $elegido]);
            }
        });
    }
}
