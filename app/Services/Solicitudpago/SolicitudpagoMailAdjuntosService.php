<?php

namespace App\Services\Solicitudpago;

use App\Models\Solicitudpago\Solicitudpago;
use App\Models\Solicitudpago\Solicitudpago_Archivo;
use App\Support\Solicitudpago\SolicitudpagoArchivoStorageSupport;

/**
 * Arma adjuntos del mail de árbol SP: comprobante PDF + archivos asociados (originales).
 */
class SolicitudpagoMailAdjuntosService
{
    /** Tope blando para no superar límites SMTP habituales (~25 MB). */
    private const LIMITE_BYTES = 18 * 1024 * 1024;

    public function __construct(
        private SolicitudpagoComprobantePdfService $comprobantePdfService,
    ) {
    }

    /**
     * @return array{
     *   adjuntos: list<array{modo: 'data'|'path', contenido?: string, path?: string, nombre: string, mime: string}>,
     *   omitidos: list<string>,
     *   bytes_total: int
     * }
     */
    public function armarParaMail(int $solicitudpagoId): array
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '300');

        $sp = Solicitudpago::query()
            ->with(['archivos'])
            ->find($solicitudpagoId);

        if (! $sp) {
            return ['adjuntos' => [], 'omitidos' => ['Solicitud no encontrada'], 'bytes_total' => 0];
        }

        $adjuntos = [];
        $omitidos = [];
        $bytesTotal = 0;
        $nombresUsados = [];

        try {
            $comp = $this->comprobantePdfService->generar((int) $sp->id);
            $bytes = $comp['pdf']->output();
            $nombreComp = $this->nombreUnico((string) $comp['nombre'], $nombresUsados);
            $tam = strlen($bytes);
            if ($tam > 0 && ($bytesTotal + $tam) <= self::LIMITE_BYTES) {
                $adjuntos[] = [
                    'modo' => 'data',
                    'contenido' => $bytes,
                    'nombre' => $nombreComp,
                    'mime' => 'application/pdf',
                ];
                $bytesTotal += $tam;
            } else {
                $omitidos[] = $nombreComp.' (tamaño o límite del mail)';
            }
        } catch (\Throwable $e) {
            report($e);
            $omitidos[] = 'Comprobante PDF de la solicitud';
        }

        $codigoSp = (int) ($sp->codigo ?? 0);
        $archivos = $sp->archivos->sortBy('nro_linea')->values();

        foreach ($archivos as $arch) {
            /** @var Solicitudpago_Archivo $arch */
            $nombre = trim((string) ($arch->nombre_original ?: basename((string) $arch->archivo)));
            if ($nombre === '') {
                $nombre = 'archivo_'.$arch->id;
            }
            $nombre = $this->nombreUnico($nombre, $nombresUsados);

            $ruta = SolicitudpagoArchivoStorageSupport::rutaAbsoluta($arch, $codigoSp > 0 ? $codigoSp : null);
            if ($ruta === null || ! is_readable($ruta)) {
                $omitidos[] = $nombre.' (no encontrado en disco)';

                continue;
            }

            $tam = (int) (@filesize($ruta) ?: 0);
            if ($tam <= 0) {
                $omitidos[] = $nombre.' (vacío)';

                continue;
            }
            if (($bytesTotal + $tam) > self::LIMITE_BYTES) {
                $omitidos[] = $nombre.' (supera límite de adjuntos del mail; use Descargar)';

                continue;
            }

            $adjuntos[] = [
                'modo' => 'path',
                'path' => $ruta,
                'nombre' => $nombre,
                'mime' => $this->mimeDesdeNombre($nombre, $ruta),
            ];
            $bytesTotal += $tam;
        }

        return [
            'adjuntos' => $adjuntos,
            'omitidos' => $omitidos,
            'bytes_total' => $bytesTotal,
        ];
    }

    /**
     * @param  array<string, int>  $nombresUsados
     */
    private function nombreUnico(string $nombre, array &$nombresUsados): string
    {
        $base = $nombre;
        $clave = mb_strtolower($base);
        if (! isset($nombresUsados[$clave])) {
            $nombresUsados[$clave] = 1;

            return $base;
        }

        $nombresUsados[$clave]++;
        $ext = pathinfo($base, PATHINFO_EXTENSION);
        $stem = pathinfo($base, PATHINFO_FILENAME);
        $sufijo = '_'.$nombresUsados[$clave];

        return $ext !== ''
            ? $stem.$sufijo.'.'.$ext
            : $stem.$sufijo;
    }

    private function mimeDesdeNombre(string $nombre, string $ruta): string
    {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $porExt = match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
            default => null,
        };
        if ($porExt !== null) {
            return $porExt;
        }

        if (function_exists('mime_content_type')) {
            $detectado = @mime_content_type($ruta);
            if (is_string($detectado) && $detectado !== '') {
                return $detectado;
            }
        }

        return 'application/octet-stream';
    }
}
