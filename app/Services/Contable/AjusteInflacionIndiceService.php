<?php

namespace App\Services\Contable;

use App\Models\Contable\AjusteInflacionIndice;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AjusteInflacionIndiceService
{
    public function guardar(string $periodo, float $valor, int $usuarioId, string $fuente, bool $provisorio): AjusteInflacionIndice
    {
        $periodoNormalizado = $this->normalizarPeriodo($periodo);
        if ($valor <= 0) {
            throw new InvalidArgumentException('El índice debe ser mayor que cero.');
        }

        return AjusteInflacionIndice::query()->updateOrCreate(
            ['periodo' => $periodoNormalizado],
            [
                'valor' => $valor,
                'fuente' => trim($fuente) !== '' ? trim($fuente) : 'FACPCE RT 6',
                'provisorio' => $provisorio,
                'usuario_id' => $usuarioId,
            ]
        );
    }

    /**
     * Importa CSV con encabezados periodo,valor y opcionales fuente,provisorio.
     *
     * @return array{creados: int, actualizados: int, errores: list<string>}
     */
    public function importarCsv(UploadedFile $archivo, int $usuarioId): array
    {
        $ruta = $archivo->getRealPath();
        if ($ruta === false || ! is_readable($ruta)) {
            throw new InvalidArgumentException('No se pudo leer el archivo de índices.');
        }

        $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lineas === false || $lineas === []) {
            throw new InvalidArgumentException('El archivo de índices está vacío.');
        }

        $delimitador = substr_count($lineas[0], ';') >= substr_count($lineas[0], ',') ? ';' : ',';
        $encabezados = array_map(
            fn ($valor) => $this->normalizarEncabezado((string) $valor),
            str_getcsv((string) array_shift($lineas), $delimitador)
        );

        $posPeriodo = array_search('periodo', $encabezados, true);
        $posValor = array_search('valor', $encabezados, true);
        if ($posPeriodo === false || $posValor === false) {
            throw new InvalidArgumentException('El CSV debe incluir las columnas periodo y valor.');
        }

        $posFuente = array_search('fuente', $encabezados, true);
        $posProvisorio = array_search('provisorio', $encabezados, true);
        $creados = 0;
        $actualizados = 0;
        $errores = [];

        DB::transaction(function () use (
            $lineas,
            $delimitador,
            $posPeriodo,
            $posValor,
            $posFuente,
            $posProvisorio,
            $usuarioId,
            &$creados,
            &$actualizados,
            &$errores
        ): void {
            foreach ($lineas as $indice => $linea) {
                $numeroLinea = $indice + 2;
                $columnas = str_getcsv((string) $linea, $delimitador);

                try {
                    $periodo = trim((string) ($columnas[$posPeriodo] ?? ''));
                    $valor = $this->normalizarNumero((string) ($columnas[$posValor] ?? ''));
                    $fuente = $posFuente !== false
                        ? trim((string) ($columnas[$posFuente] ?? ''))
                        : 'FACPCE RT 6';
                    $provisorio = $posProvisorio !== false
                        && in_array(strtolower(trim((string) ($columnas[$posProvisorio] ?? ''))), ['1', 's', 'si', 'sí', 'true'], true);

                    $periodoNormalizado = $this->normalizarPeriodo($periodo);
                    $existia = AjusteInflacionIndice::query()->whereDate('periodo', $periodoNormalizado)->exists();
                    $this->guardar($periodo, $valor, $usuarioId, $fuente, $provisorio);
                    $existia ? $actualizados++ : $creados++;
                } catch (\Throwable $e) {
                    $errores[] = 'Línea '.$numeroLinea.': '.$e->getMessage();
                }
            }
        });

        return compact('creados', 'actualizados', 'errores');
    }

    private function normalizarPeriodo(string $periodo): string
    {
        $periodo = trim($periodo);
        foreach (['Y-m', 'Y-m-d', 'm/Y', 'm-Y'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $periodo);
                if ($fecha !== false) {
                    return $fecha->startOfMonth()->format('Y-m-d');
                }
            } catch (\Throwable) {
                // Probar el siguiente formato.
            }
        }

        throw new InvalidArgumentException('Período inválido «'.$periodo.'». Use YYYY-MM.');
    }

    private function normalizarNumero(string $valor): float
    {
        $valor = trim(str_replace([' ', "\xc2\xa0"], '', $valor));
        if ($valor === '') {
            throw new InvalidArgumentException('El valor del índice está vacío.');
        }

        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            $valor = str_replace('.', '', $valor);
        }
        $valor = str_replace(',', '.', $valor);
        if (! is_numeric($valor) || (float) $valor <= 0) {
            throw new InvalidArgumentException('Índice inválido «'.$valor.'».');
        }

        return (float) $valor;
    }

    private function normalizarEncabezado(string $valor): string
    {
        $valor = mb_strtolower(trim($valor));
        $valor = str_replace(['í', 'ó'], ['i', 'o'], $valor);

        return preg_replace('/[^a-z0-9_]/', '', $valor) ?? '';
    }
}
