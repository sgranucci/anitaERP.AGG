<?php

namespace App\Services\Compras;

use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Presupuesto;
use App\Models\Compras\Requisicion_Presupuesto_Archivo;
use App\Models\Compras\Requisicion_Presupuesto_Articulo;
use App\Repositories\Compras\RequisicionRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RequisicionPresupuestoService
{
    private $requisicionRepository;

    public function __construct(RequisicionRepositoryInterface $requisicionRepository)
    {
        $this->requisicionRepository = $requisicionRepository;
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
        $items = Requisicion_Presupuesto::query()
            ->where('requisicion_id', $requisicionId)
            ->with(['proveedores', 'requisicion_presupuesto_archivos'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

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
        $p = Requisicion_Presupuesto::query()
            ->where('requisicion_id', $requisicionId)
            ->where('id', $presupuestoId)
            ->with([
                'proveedores',
                'requisicion_presupuesto_articulos.requisicion_articulo.articulos',
                'requisicion_presupuesto_articulos.requisicion_articulo.monedas',
                'requisicion_presupuesto_archivos',
            ])
            ->first();

        if (! $p) {
            return null;
        }

        $cabecera = $this->serializaCabecera($p, $requisicionId);
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

        return [
            'id' => $p->id,
            'requisicion_id' => $p->requisicion_id,
            'fecha' => $p->fecha ? substr((string) $p->fecha, 0, 10) : null,
            'condiciones_entrega' => $p->condiciones_entrega,
            'condiciones_compra' => $p->condiciones_compra,
            'condiciones_pago' => $p->condiciones_pago,
            'proveedor_id' => $p->proveedor_id,
            'proveedor_codigo' => $prov ? $prov->codigo : '',
            'proveedor_nombre' => $prov ? $prov->nombre : '',
            'estado' => $p->estado,
            'archivos' => $p->requisicion_presupuesto_archivos
                ? $p->requisicion_presupuesto_archivos->map(function (Requisicion_Presupuesto_Archivo $a) use ($requisicionId, $p) {
                    return [
                        'id' => $a->id,
                        'nombrearchivo' => $a->nombrearchivo,
                        'url_ver' => route('requisicion_presupuesto_archivo_ver', [
                            'requisicion' => $requisicionId,
                            'presupuesto' => $p->id,
                            'archivo' => $a->id,
                        ]),
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
            $pres = Requisicion_Presupuesto::create([
                'requisicion_id' => $requisicionId,
                'fecha' => $request->input('fecha'),
                'condiciones_entrega' => $request->input('condiciones_entrega'),
                'condiciones_compra' => $request->input('condiciones_compra'),
                'condiciones_pago' => $request->input('condiciones_pago'),
                'proveedor_id' => (int) $request->input('proveedor_id'),
                'estado' => $estado,
            ]);

            foreach ($lineas as $linea) {
                Requisicion_Presupuesto_Articulo::create([
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

        $pres = Requisicion_Presupuesto::query()
            ->where('requisicion_id', $requisicionId)
            ->where('id', $presupuestoId)
            ->first();
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
            $pres->update([
                'fecha' => $request->input('fecha'),
                'condiciones_entrega' => $request->input('condiciones_entrega'),
                'condiciones_compra' => $request->input('condiciones_compra'),
                'condiciones_pago' => $request->input('condiciones_pago'),
                'proveedor_id' => (int) $request->input('proveedor_id'),
                'estado' => $estado,
            ]);

            Requisicion_Presupuesto_Articulo::query()->where('requisicion_presupuesto_id', $pres->id)->delete();
            foreach ($lineas as $linea) {
                Requisicion_Presupuesto_Articulo::create([
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
        $pres = Requisicion_Presupuesto::query()
            ->where('requisicion_id', $requisicionId)
            ->where('id', $presupuestoId)
            ->first();
        if (! $pres) {
            return ['ok' => false, 'error' => 'Presupuesto no encontrado.'];
        }

        $dir = $this->directorioPresupuesto($requisicionId, $presupuestoId);
        DB::beginTransaction();
        try {
            Requisicion_Presupuesto_Articulo::query()->where('requisicion_presupuesto_id', $pres->id)->delete();
            Requisicion_Presupuesto_Archivo::query()->where('requisicion_presupuesto_id', $pres->id)->delete();
            $pres->delete();
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

        Requisicion_Presupuesto::query()
            ->where('requisicion_id', $requisicionId)
            ->where('id', '<>', $presupuestoIdActual)
            ->where('estado', $elegido)
            ->update(['estado' => $activo]);
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
                Requisicion_Presupuesto_Archivo::create([
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

        $existentes = Requisicion_Presupuesto_Archivo::query()
            ->where('requisicion_presupuesto_id', $presupuestoId)
            ->get();

        foreach ($existentes as $ex) {
            if (! in_array((int) $ex->id, $idsConservar, true)) {
                $full = $this->directorioPresupuesto($requisicionId, $presupuestoId).'/'.$ex->nombrearchivo;
                if (is_file($full)) {
                    @unlink($full);
                }
                $ex->delete();
            }
        }

        $this->guardaArchivosSubidos($request, $requisicionId, $presupuestoId);
    }

    public function rutaFisicaArchivo(int $requisicionId, int $presupuestoId, Requisicion_Presupuesto_Archivo $archivo): string
    {
        return $this->directorioPresupuesto($requisicionId, $presupuestoId).'/'.$archivo->nombrearchivo;
    }
}
