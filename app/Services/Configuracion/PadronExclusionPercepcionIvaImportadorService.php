<?php

namespace App\Services\Configuracion;

use DateTime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Importa el CSV AFIP de sujetos no alcanzados (percepción IVA) a padron_exclusionpercepcioniva.
 *
 * Formato esperado (delimiter `;`, encoding ISO-8859-1 típico AFIP):
 * CUIT;DENOMINACION;FECHA_DESDE;FECHA_HASTA
 */
class PadronExclusionPercepcionIvaImportadorService
{
    private const TAMANIO_LOTE = 500;

    /**
     * Reemplaza el padrón completo con el contenido del archivo.
     *
     * @return array{ok: bool, mensaje: string, importados: int, omitidas: int}
     */
    public function importarDesdeArchivo(UploadedFile|string $archivo): array
    {
        $ruta = $archivo instanceof UploadedFile
            ? $archivo->getRealPath()
            : (string) $archivo;

        if ($ruta === '' || ! is_readable($ruta)) {
            return $this->error('No se pudo leer el archivo de importación.');
        }

        $handle = fopen($ruta, 'rb');
        if ($handle === false) {
            return $this->error('No se pudo abrir el archivo de importación.');
        }

        $ahora = now()->format('Y-m-d H:i:s');
        $lote = [];
        $importados = 0;
        $omitidas = 0;

        try {
            DB::beginTransaction();
            DB::table('padron_exclusionpercepcioniva')->delete();

            while (($fila = fgetcsv($handle, 0, ';')) !== false) {
                $normalizada = $this->normalizarFila($fila);
                if ($normalizada === null) {
                    $omitidas++;

                    continue;
                }

                $lote[] = [
                    'cuit' => $normalizada['cuit'],
                    'nombre' => $normalizada['nombre'],
                    'desdefecha' => $normalizada['desdefecha'],
                    'hastafecha' => $normalizada['hastafecha'],
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];

                if (count($lote) >= self::TAMANIO_LOTE) {
                    DB::table('padron_exclusionpercepcioniva')->insert($lote);
                    $importados += count($lote);
                    $lote = [];
                }
            }

            if ($lote !== []) {
                DB::table('padron_exclusionpercepcioniva')->insert($lote);
                $importados += count($lote);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->error('Error al importar el padrón: '.$e->getMessage());
        } finally {
            fclose($handle);
        }

        if ($importados === 0) {
            return $this->error('No se importó ningún registro válido del archivo.');
        }

        $mensaje = "Padrón exclusión percepción IVA importado: {$importados} registros"
            .($omitidas > 0 ? " ({$omitidas} filas omitidas)." : '.');

        return [
            'ok' => true,
            'mensaje' => $mensaje,
            'importados' => $importados,
            'omitidas' => $omitidas,
        ];
    }

    /**
     * @param  array<int, mixed>  $fila
     * @return array{cuit: string, nombre: string, desdefecha: string, hastafecha: string|null}|null
     */
    private function normalizarFila(array $fila): ?array
    {
        if (count($fila) === 1 && is_string($fila[0] ?? null) && str_contains($fila[0], ';')) {
            $fila = str_getcsv($fila[0], ';');
        }

        if (count($fila) < 3) {
            return null;
        }

        $cuit = preg_replace('/\D+/', '', (string) ($fila[0] ?? '')) ?? '';
        if ($cuit === '' || ! ctype_digit($cuit) || strlen($cuit) < 10) {
            return null;
        }

        $nombre = $this->textoUtf8((string) ($fila[1] ?? ''));
        $nombre = mb_substr(trim($nombre), 0, 255);
        if ($nombre === '') {
            return null;
        }

        $desde = $this->fechaYmd($fila[2] ?? null);
        if ($desde === null) {
            return null;
        }

        $hasta = $this->fechaYmd($fila[3] ?? null);

        return [
            'cuit' => $cuit,
            'nombre' => $nombre,
            'desdefecha' => $desde,
            'hastafecha' => $hasta,
        ];
    }

    private function fechaYmd(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);
        if ($texto === '' || $texto === '0') {
            return null;
        }

        // PhpSpreadsheet/CSV a veces deja float (20230501.0)
        if (is_numeric($texto)) {
            $texto = (string) (int) $texto;
        }

        $fecha = DateTime::createFromFormat('Ymd', $texto);
        if (! $fecha || $fecha->format('Ymd') !== str_pad($texto, 8, '0', STR_PAD_LEFT)) {
            return null;
        }

        return $fecha->format('Y-m-d');
    }

    private function textoUtf8(string $texto): string
    {
        if ($texto === '') {
            return '';
        }

        if (mb_check_encoding($texto, 'UTF-8')) {
            return $texto;
        }

        $convertido = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $texto);

        return $convertido !== false ? $convertido : $texto;
    }

    /**
     * @return array{ok: bool, mensaje: string, importados: int, omitidas: int}
     */
    private function error(string $mensaje): array
    {
        return [
            'ok' => false,
            'mensaje' => $mensaje,
            'importados' => 0,
            'omitidas' => 0,
        ];
    }
}
