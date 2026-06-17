<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor_Archivo;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class RecepcionProveedorArchivoSupport
{
    public static function sincronizarAdjuntosDesdeRequest(int $recepcionId, Request $request): void
    {
        $conservarIds = collect((array) $request->input('archivos_adjuntos_conservar', []))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();

        $query = Recepcion_Proveedor_Archivo::query()
            ->where('recepcion_proveedor_id', $recepcionId)
            ->where('tipo_archivo', Recepcion_Proveedor_Archivo::TIPO_ADJUNTO);

        $aEliminar = $conservarIds === []
            ? $query->get()
            : $query->whereNotIn('id', $conservarIds)->get();

        foreach ($aEliminar as $archivo) {
            self::eliminarFisico($archivo);
            $archivo->delete();
        }

        /** @var array<int, UploadedFile|null> $archivosNuevos */
        $archivosNuevos = $request->file('nombrearchivos', []);
        if (! is_array($archivosNuevos)) {
            return;
        }

        foreach ($archivosNuevos as $archivo) {
            if ($archivo === null || ! $archivo->isValid()) {
                continue;
            }

            $ruta = $archivo->store('recepcion_proveedor/adjuntos/'.date('Y/m'), 'local');

            Recepcion_Proveedor_Archivo::create([
                'recepcion_proveedor_id' => $recepcionId,
                'nombre' => $archivo->getClientOriginalName(),
                'ruta' => $ruta,
                'tipo_archivo' => Recepcion_Proveedor_Archivo::TIPO_ADJUNTO,
                'mime' => $archivo->getMimeType(),
            ]);
        }
    }

    public static function eliminarFisico(Recepcion_Proveedor_Archivo $archivo): void
    {
        $ruta = (string) ($archivo->ruta ?? '');
        if ($ruta !== '' && Storage::disk('local')->exists($ruta)) {
            Storage::disk('local')->delete($ruta);
        }
    }

    public static function rutaAbsoluta(Recepcion_Proveedor_Archivo $archivo): string
    {
        return Storage::disk('local')->path($archivo->ruta);
    }
}
