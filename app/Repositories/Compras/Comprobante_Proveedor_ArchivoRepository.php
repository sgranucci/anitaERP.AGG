<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Comprobante_Proveedor_Archivo;
use App\Support\Compras\ComprobanteProveedorArchivoTipos;
use Illuminate\Http\Request;

class Comprobante_Proveedor_ArchivoRepository implements Comprobante_Proveedor_ArchivoRepositoryInterface
{
    public function __construct(private Comprobante_Proveedor_Archivo $model) {}

    public function sincronizarDesdeRequest(Request $request, int $comprobanteId): void
    {
        $adjuntosAntes = $this->model->newQuery()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->whereIn('tipo', ComprobanteProveedorArchivoTipos::subibles())
            ->get();

        $nombresAntes = $adjuntosAntes->pluck('nombrearchivo')->all();

        $this->model->newQuery()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->whereIn('tipo', ComprobanteProveedorArchivoTipos::subibles())
            ->delete();

        $nombrearchivos = $request->file('nombrearchivos');
        if ($nombrearchivos) {
            foreach ($nombrearchivos as $i => $archivo) {
                if (! $archivo) {
                    continue;
                }

                $tipo = $this->resolverTipoUpload($request, $i);
                $this->guardarArchivoFisico($archivo, $comprobanteId);

                $this->model->create([
                    'comprobante_proveedor_id' => $comprobanteId,
                    'tipo' => $tipo,
                    'nombrearchivo' => $archivo->getClientOriginalName(),
                    'origen_externo' => false,
                ]);
            }
        }

        $nombresAnteriores = $request->input('nombresanteriores', []);
        $tiposAnteriores = $request->input('nombresanteriores_tipo', []);

        for ($i = 0; $i < count($nombresAnteriores); $i++) {
            $nombre = trim((string) ($nombresAnteriores[$i] ?? ''));
            if ($nombre === '') {
                continue;
            }

            $flEncontro = false;
            if ($nombrearchivos) {
                foreach ($nombrearchivos as $archivo) {
                    if ($archivo && $archivo->getClientOriginalName() === $nombre) {
                        $flEncontro = true;
                        break;
                    }
                }
            }

            if ($flEncontro) {
                continue;
            }

            $tipo = (string) ($tiposAnteriores[$i] ?? ComprobanteProveedorArchivoTipos::ADJUNTO);
            if (! in_array($tipo, ComprobanteProveedorArchivoTipos::subibles(), true)) {
                $tipo = ComprobanteProveedorArchivoTipos::ADJUNTO;
            }

            $this->model->create([
                'comprobante_proveedor_id' => $comprobanteId,
                'tipo' => $tipo,
                'nombrearchivo' => $nombre,
                'origen_externo' => false,
            ]);
        }

        $nombresDespues = $this->model->newQuery()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->whereIn('tipo', ComprobanteProveedorArchivoTipos::subibles())
            ->pluck('nombrearchivo')
            ->all();

        $pathBase = $this->directorioAbsoluto($comprobanteId);
        foreach (array_diff($nombresAntes, $nombresDespues) as $nombre) {
            if ($nombre === '' || $nombre === null) {
                continue;
            }
            $full = $pathBase.'/'.basename((string) $nombre);
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    private function resolverTipoUpload(Request $request, int $indice): string
    {
        $tipos = $request->input('archivo_tipos', []);
        $tipo = (string) ($tipos[$indice] ?? ComprobanteProveedorArchivoTipos::ADJUNTO);

        if (! in_array($tipo, ComprobanteProveedorArchivoTipos::subibles(), true)) {
            return ComprobanteProveedorArchivoTipos::ADJUNTO;
        }

        return $tipo;
    }

    private function guardarArchivoFisico(\Illuminate\Http\UploadedFile $archivo, int $comprobanteId): void
    {
        $path = $this->directorioAbsoluto($comprobanteId);
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $archivo->move($path, $archivo->getClientOriginalName());
    }

    private function directorioAbsoluto(int $comprobanteId): string
    {
        return public_path('storage/archivos/comprobantes_proveedor/'.$comprobanteId);
    }
}
