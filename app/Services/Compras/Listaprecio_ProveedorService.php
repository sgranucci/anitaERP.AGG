<?php

namespace App\Services\Compras;

use App\Models\Compras\Listaprecio_Proveedor;
use App\Models\Compras\Listaprecio_Proveedor_Estado;
use App\Repositories\Compras\Listaprecio_Proveedor_ArchivoRepositoryInterface;
use App\Repositories\Compras\Listaprecio_Proveedor_ArticuloRepositoryInterface;
use App\Repositories\Compras\Listaprecio_Proveedor_EstadoRepositoryInterface;
use App\Repositories\Compras\Listaprecio_ProveedorRepositoryInterface;
use App\Support\Compras\ArticuloProveedorCodigoSyncSupport;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class Listaprecio_ProveedorService
{
    public function __construct(
        private Listaprecio_ProveedorRepositoryInterface $listaprecioProveedorRepository,
        private Listaprecio_Proveedor_EstadoRepositoryInterface $listaprecioProveedorEstadoRepository,
        private Listaprecio_Proveedor_ArticuloRepositoryInterface $listaprecioProveedorArticuloRepository,
        private Listaprecio_Proveedor_ArchivoRepositoryInterface $listaprecioProveedorArchivoRepository,
        private ListaprecioProveedorImportPreviewService $importPreviewService,
    ) {}

    public function guarda($request): array
    {
        $activa = Listaprecio_Proveedor_Estado::$enumEstado[0]['nombre'];
        $data = $request->all();
        $data['creousuario_id'] = Auth::user()->id;
        $data['estado'] = $activa;

        $cabecera = $this->armaCabecera($data, true);

        DB::beginTransaction();
        try {
            $lista = $this->listaprecioProveedorRepository->create($cabecera);
            $this->listaprecioProveedorEstadoRepository->createInicial(
                $lista->id,
                $activa,
                Auth::user()->id,
                'Alta de lista de precios'
            );
            $this->listaprecioProveedorArticuloRepository->syncFromRequest($data, $lista->id, Auth::user()->id);
            $this->listaprecioProveedorArchivoRepository->create($request, $lista->id);
            $importacion = $this->importarExcelSiCorresponde($request, (int) $lista->id, Auth::user()->id);
            $this->listaprecioProveedorRepository->persistirEnAnita((int) $lista->id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        $ok = ['mensaje' => 'ok'];
        if ($importacion !== null) {
            $ok['importados'] = $importacion['importados'];
            $ok['errores_excel'] = $importacion['errores'];
        }

        return $ok;
    }

    public function actualiza($request, int $id): array
    {
        $data = $request->all();
        $existente = $this->listaprecioProveedorRepository->find($id);
        $cabecera = $this->armaCabecera($data, false);
        if (isset($data['estado']) && Listaprecio_Proveedor_Estado::esNombreEstadoValido((string) $data['estado'])) {
            $cabecera['estado'] = $data['estado'];
        } else {
            $cabecera['estado'] = $existente->estado;
        }

        DB::beginTransaction();
        try {
            unset($cabecera['creousuario_id']);
            $this->listaprecioProveedorRepository->update($cabecera, $id);
            $this->listaprecioProveedorArticuloRepository->syncFromRequest($data, $id, Auth::user()->id);
            $this->listaprecioProveedorArchivoRepository->update($request, $id);
            $this->listaprecioProveedorRepository->persistirEnAnita($id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function cambiarEstado(int $id, string $observacion = ''): array
    {
        $lista = $this->listaprecioProveedorRepository->find($id);
        $actual = $lista->estado ?? '';
        $nuevo = Listaprecio_Proveedor_Estado::otroEstado($actual);
        if ($nuevo === null) {
            return ['mensaje' => 'error', 'errores' => 'Estado no reconocido.'];
        }

        DB::beginTransaction();
        try {
            $this->listaprecioProveedorRepository->update(['estado' => $nuevo], $id);
            $this->listaprecioProveedorEstadoRepository->creaEstado(
                $id,
                $nuevo,
                Auth::user()->id,
                $observacion !== '' ? $observacion : 'Cambio de estado de '.$actual.' a '.$nuevo
            );
            $this->listaprecioProveedorRepository->persistirEnAnita($id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function leeHistoriaJson(int $listaprecio_proveedor_id)
    {
        $rows = $this->listaprecioProveedorEstadoRepository->leeHistoria($listaprecio_proveedor_id);

        return $rows->map(function ($e) {
            return [
                'fecha' => $e->created_at ? $e->created_at->format('Y-m-d H:i') : '',
                'estado' => $e->estado,
                'usuarios' => ['nombre' => $e->usuarios->nombre ?? ''],
                'observacion' => $e->observacion ?? '',
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $opciones
     * @return array{importados: int, errores: list<string>}
     */
    public function importarDesdeArchivo(
        UploadedFile $archivo,
        string $fechavigencia,
        int $listaId,
        int $usuarioId,
        array $opciones = []
    ): array {
        $lista = Listaprecio_Proveedor::query()->find($listaId);
        $proveedorId = (int) ($opciones['proveedor_id'] ?? $lista?->proveedor_id ?? 0);

        set_time_limit(0);
        $preview = $this->importPreviewService->previsualizar(
            $archivo,
            $proveedorId > 0 ? $proveedorId : null,
            $opciones['col_sku'] ?? null,
            $opciones['col_descripcion'] ?? null,
            $opciones['col_precio'] ?? null,
            $opciones['col_descuento'] ?? null,
            $opciones['col_codigo_proveedor'] ?? null,
            isset($opciones['fila_encabezado']) ? (int) $opciones['fila_encabezado'] : null,
            isset($opciones['hoja_indice']) ? (int) $opciones['hoja_indice'] : null,
            true
        );

        $errores = $preview['errores'] ?? [];
        if (! empty($preview['mensaje']) && empty($preview['lineas'])) {
            $errores[] = (string) $preview['mensaje'];
        }

        $importados = 0;
        foreach ($preview['lineas'] ?? [] as $ln) {
            $articuloId = (int) ($ln['articulo_id'] ?? 0);
            if ($articuloId <= 0) {
                continue;
            }
            $codigo = substr((string) ($ln['codigo_articulo_proveedor'] ?? ''), 0, 100);
            $this->listaprecioProveedorArticuloRepository->createRow([
                'listaprecio_proveedor_id' => $listaId,
                'articulo_id' => $articuloId,
                'precio' => (float) ($ln['precio'] ?? 0),
                'descuento' => min(100, max(0, (float) ($ln['descuento'] ?? 0))),
                'codigo_articulo_proveedor' => $codigo,
                'fechavigencia' => $fechavigencia,
                'usuarioultcambio_id' => $usuarioId,
            ]);
            if ($proveedorId > 0) {
                ArticuloProveedorCodigoSyncSupport::desdeLista(
                    $articuloId,
                    $proveedorId,
                    $codigo,
                    $listaId
                );
            }
            $importados++;
        }

        return [
            'importados' => $importados,
            'errores' => $errores,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function opcionesImportacionDesdeRequest(Request $request): array
    {
        $fila = $request->filled('fila_encabezado') ? (int) $request->input('fila_encabezado') : null;
        $hoja = $request->filled('hoja_indice') ? (int) $request->input('hoja_indice') : null;

        return [
            'col_sku' => $request->input('col_sku'),
            'col_descripcion' => $request->input('col_descripcion'),
            'col_precio' => $request->input('col_precio'),
            'col_descuento' => $request->input('col_descuento'),
            'col_codigo_proveedor' => $request->input('col_codigo_proveedor'),
            'fila_encabezado' => $fila,
            'hoja_indice' => $hoja,
            'proveedor_id' => $request->filled('proveedor_id') ? (int) $request->input('proveedor_id') : null,
        ];
    }

    public static function mensajeImportacion(int $importados, array $errores): string
    {
        $msg = 'Importación Excel: '.$importados.' ítem(s) cargados.';
        if ($errores !== []) {
            $msg .= ' Advertencias: '.implode(' ', array_slice($errores, 0, 15));
            if (count($errores) > 15) {
                $msg .= '…';
            }
        }

        return $msg;
    }

    /**
     * @return array{importados: int, errores: list<string>}|null
     */
    private function importarExcelSiCorresponde($request, int $listaId, int $usuarioId): ?array
    {
        if (! $request->hasFile('archivoexcel')) {
            return null;
        }

        $fecha = trim((string) $request->input('fechavigencia_excel', ''));
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        return $this->importarDesdeArchivo(
            $request->file('archivoexcel'),
            $fecha,
            $listaId,
            $usuarioId,
            self::opcionesImportacionDesdeRequest($request)
        );
    }

    private function armaCabecera(array $data, bool $esAlta): array
    {
        $row = [
            'proveedor_id' => $data['proveedor_id'] ?? null,
            'fecha' => $data['fecha'] ?? date('Y-m-d'),
            'nombre' => $data['nombre'] ?? '',
            'observaciones' => $data['observaciones'] ?? '',
            'condicionpago_id' => ! empty($data['condicionpago_id']) ? $data['condicionpago_id'] : null,
            'condicionentrega_id' => ! empty($data['condicionentrega_id']) ? $data['condicionentrega_id'] : null,
            'condicioncompra_id' => ! empty($data['condicioncompra_id']) ? $data['condicioncompra_id'] : null,
            'moneda_id' => ! empty($data['moneda_id']) ? $data['moneda_id'] : null,
        ];
        if ($esAlta) {
            $row['creousuario_id'] = $data['creousuario_id'] ?? Auth::user()->id;
            $row['estado'] = $data['estado'] ?? Listaprecio_Proveedor_Estado::$enumEstado[0]['nombre'];
        }

        return $row;
    }
}
