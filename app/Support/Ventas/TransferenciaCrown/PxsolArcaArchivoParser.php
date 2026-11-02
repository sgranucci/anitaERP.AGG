<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Lee el CSV/Excel de Mis Comprobantes (ARCA) que consume p-tr-pxsol.c.
 * Con encabezado usa los nombres. Sin encabezado usa el orden fijo del C.
 */
final class PxsolArcaArchivoParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public function leer(string $path): array
    {
        $filas = $this->filasCrudas($path);
        if ($filas === []) {
            return [];
        }

        $mapa = $this->mapaEncabezado($filas[0]);
        $desde = $mapa === null ? 0 : 1;
        $out = [];
        for ($i = $desde; $i < count($filas); $i++) {
            $fila = $mapa === null
                ? $this->desdePosicion($filas[$i])
                : $this->desdeEncabezado($filas[$i], $mapa);
            if ($fila === null) {
                continue;
            }
            $out[] = $fila;
        }

        return $out;
    }

    /**
     * @return list<list<string>>
     */
    private function filasCrudas(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $out = [];
            foreach ($sheet->toArray(null, true, true, false) as $fila) {
                $out[] = array_map(static fn ($v): string => trim((string) $v), $fila);
            }

            return $out;
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new RuntimeException('No se pudo abrir el archivo.');
        }
        $out = [];
        while (($linea = fgets($fh)) !== false) {
            $linea = preg_replace('/^\xEF\xBB\xBF/', '', $linea) ?? $linea;
            $linea = rtrim($linea, "\r\n");
            if (trim($linea) === '') {
                continue;
            }
            $sep = substr_count($linea, ';') >= substr_count($linea, ',') ? ';' : ',';
            $out[] = array_map(static fn ($v): string => trim((string) $v, " \t\""), str_getcsv($linea, $sep));
        }
        fclose($fh);

        return $out;
    }

    /**
     * @param  list<string>  $encabezado
     * @return array<string, int>|null
     */
    private function mapaEncabezado(array $encabezado): ?array
    {
        $norm = [];
        foreach ($encabezado as $i => $titulo) {
            $norm[$i] = $this->normalizar($titulo);
        }
        $tieneFecha = false;
        $tieneTipo = false;
        foreach ($norm as $titulo) {
            if (str_contains($titulo, 'fecha')) {
                $tieneFecha = true;
            }
            if ($titulo === 'tipo' || str_contains($titulo, 'tipo de comprobante')) {
                $tieneTipo = true;
            }
        }
        if (! $tieneFecha || ! $tieneTipo) {
            return null;
        }

        $buscar = [
            'fecha' => ['fecha de emision', 'fecha'],
            'tipo' => ['tipo de comprobante', 'tipo'],
            'sucursal' => ['punto de venta'],
            'numero' => ['numero desde'],
            'cae' => ['cod. autorizacion', 'cod autorizacion', 'cae'],
            'cuit' => ['nro. doc. receptor', 'nro doc receptor', 'nro. doc receptor'],
            'nombre' => ['denominacion receptor'],
            'cotizacion' => ['tipo cambio'],
            'moneda' => ['moneda'],
            'neto0' => ['iva 0%'],
            'iva105' => ['iva 10,5%', 'iva 10.5%'],
            'gravado105' => ['neto gravado iva 10,5%', 'neto gravado iva 10.5%'],
            'iva21' => ['iva 21%'],
            'gravado21' => ['neto gravado iva 21%'],
            'iva27' => ['iva 27%'],
            'gravado27' => ['neto gravado iva 27%'],
            'no_gravado' => ['no gravado'],
            'exento' => ['op. exentas', 'op exentas'],
            'otros' => ['otros tributos'],
            'total' => ['imp. total', 'imp total'],
        ];

        $mapa = [];
        foreach ($buscar as $campo => $aliases) {
            foreach ($norm as $i => $titulo) {
                if ($titulo === '') {
                    continue;
                }
                foreach ($aliases as $alias) {
                    if ($titulo === $alias || str_contains($titulo, $alias)) {
                        if ($campo === 'neto0' && ! str_contains($titulo, '0')) {
                            continue;
                        }
                        if ($campo === 'iva105' && str_contains($titulo, 'neto')) {
                            continue;
                        }
                        if ($campo === 'iva21' && str_contains($titulo, 'neto')) {
                            continue;
                        }
                        if ($campo === 'iva27' && str_contains($titulo, 'neto')) {
                            continue;
                        }
                        if ($campo === 'fecha' && str_contains($titulo, 'vto')) {
                            continue;
                        }
                        $mapa[$campo] = $i;
                        break 2;
                    }
                }
            }
        }

        return isset($mapa['fecha'], $mapa['tipo'], $mapa['numero']) ? $mapa : null;
    }

    /**
     * @param  list<string>  $cols
     * @return array<string, mixed>|null
     */
    private function desdePosicion(array $cols): ?array
    {
        $fecha = $this->fecha((string) ($cols[0] ?? ''));
        if ($fecha === null) {
            return null;
        }
        $get = static fn (int $i): string => trim((string) ($cols[$i] ?? ''));

        return $this->armar(
            $fecha,
            $get(1),
            $get(2),
            $get(3),
            $get(5),
            $get(7),
            $get(8),
            $get(9),
            $get(10),
            $this->numero($get(19)),
            $this->numero($get(18)),
            $this->numero($get(17)),
            $this->numero($get(16)),
            $this->numero($get(21)),
            $this->numero($get(20)),
            $this->numero($get(23)) + $this->numero($get(11)),
            $this->numero($get(24)),
            $this->numero($get(25)),
            $this->numero($get(27)),
        );
    }

    /**
     * @param  list<string>  $cols
     * @param  array<string, int>  $mapa
     * @return array<string, mixed>|null
     */
    private function desdeEncabezado(array $cols, array $mapa): ?array
    {
        $toma = function (string $campo) use ($cols, $mapa): string {
            $i = $mapa[$campo] ?? null;

            return $i === null ? '' : trim((string) ($cols[$i] ?? ''));
        };
        $fecha = $this->fecha($toma('fecha'));
        if ($fecha === null) {
            return null;
        }

        return $this->armar(
            $fecha,
            $toma('tipo'),
            $toma('sucursal'),
            $toma('numero'),
            $toma('cae'),
            $toma('cuit'),
            $toma('nombre'),
            $toma('cotizacion'),
            $toma('moneda'),
            $this->numero($toma('gravado21')),
            $this->numero($toma('iva21')),
            $this->numero($toma('gravado105')),
            $this->numero($toma('iva105')),
            $this->numero($toma('gravado27')),
            $this->numero($toma('iva27')),
            $this->numero($toma('no_gravado')) + $this->numero($toma('neto0')),
            $this->numero($toma('exento')),
            $this->numero($toma('otros')),
            $this->numero($toma('total')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function armar(
        string $fecha,
        string $tipoComp,
        string $sucursal,
        string $numero,
        string $cae,
        string $cuit,
        string $nombre,
        string $cotizacion,
        string $moneda,
        float $gravado21,
        float $iva21,
        float $gravado105,
        float $iva105,
        float $gravado27,
        float $iva27,
        float $noGravado,
        float $exento,
        float $otros,
        float $total,
    ): array {
        $tipoAfip = (int) preg_replace('/\D+/', '', $tipoComp);
        [$tipo, $letra] = $this->tipoLetra($tipoAfip);

        return [
            'fecha' => $fecha,
            'tipo_afip' => $tipoAfip,
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => (int) preg_replace('/\D+/', '', $sucursal),
            'numero' => (int) preg_replace('/\D+/', '', $numero),
            'cae' => trim($cae),
            'cuit' => preg_replace('/\D+/', '', $cuit) ?? '',
            'nombre' => trim($nombre),
            'cotizacion' => abs($this->numero($cotizacion)),
            'moneda' => $this->moneda($moneda),
            'gravado21' => abs($gravado21),
            'iva21' => abs($iva21),
            'gravado105' => abs($gravado105),
            'iva105' => abs($iva105),
            'gravado27' => abs($gravado27),
            'iva27' => abs($iva27),
            'exento' => abs($noGravado + $exento),
            'otros' => abs($otros),
            'total' => abs($total),
            'noi' => 0.0,
            'percepcion_iva' => 0.0,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function tipoLetra(int $tipoAfip): array
    {
        return match ($tipoAfip) {
            1 => ['FAC', 'A'],
            2 => ['NDB', 'A'],
            3 => ['NCD', 'A'],
            6 => ['FAC', 'B'],
            7 => ['NDB', 'B'],
            8 => ['NCD', 'B'],
            195 => ['FAC', 'T'],
            196 => ['NDB', 'T'],
            197 => ['NCD', 'T'],
            default => ['NDB', 'B'],
        };
    }

    private function moneda(string $valor): string
    {
        $valor = strtoupper(trim($valor));
        if ($valor === '' || $valor === 'PES' || $valor === '$' || $valor === 'ARS' || $valor === '1') {
            return '1';
        }

        return '2';
    }

    private function fecha(string $valor): ?string
    {
        $valor = trim($valor);
        if (! str_contains($valor, '-') && ! str_contains($valor, '/')) {
            return null;
        }
        $valor = str_replace('/', '-', $valor);
        $partes = explode('-', $valor);
        if (count($partes) !== 3) {
            return null;
        }
        if (strlen($partes[0]) === 4) {
            $anio = (int) $partes[0];
            $mes = (int) $partes[1];
            $dia = (int) $partes[2];
        } else {
            $dia = (int) $partes[0];
            $mes = (int) $partes[1];
            $anio = (int) $partes[2];
        }
        if ($anio < 2000 || $mes < 1 || $mes > 12 || $dia < 1 || $dia > 31) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    private function numero(string $valor): float
    {
        $valor = trim($valor);
        if ($valor === '') {
            return 0.0;
        }
        $valor = str_replace(['$', ' '], '', $valor);
        if (str_contains($valor, ',')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }

        return (float) $valor;
    }

    private function normalizar(string $valor): string
    {
        $valor = mb_strtolower(trim($valor));
        $valor = strtr($valor, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', $valor) ?? $valor;
    }
}
