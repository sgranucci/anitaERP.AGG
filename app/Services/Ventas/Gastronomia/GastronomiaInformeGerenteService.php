<?php

namespace App\Services\Ventas\Gastronomia;

use App\Queries\Ventas\GastronomiaInformeGerenteQuery;
use Carbon\Carbon;

/**
 * Arma el payload del informe gerente (gráficos + grillas).
 */
final class GastronomiaInformeGerenteService
{
    public function __construct(
        private readonly GastronomiaInformeGerenteQuery $query,
        private readonly GastronomiaRecepcionesAnitaService $recepcionesAnita,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generar(int $empresaId, string $fechaJornada): array
    {
        $fecha = $this->normalizarFecha($fechaJornada);

        $topCantidad = $this->query->top10PorCantidad($empresaId, $fecha);
        $topValor = $this->query->top10PorValor($empresaId, $fecha);
        $topMesCantidad = $this->query->top10MesPorCantidad($empresaId, $fecha);
        $porTurno = $this->query->ventasPorTurno($empresaId, $fecha);
        $porPv = $this->query->ventasPorPuntoVenta($empresaId, $fecha);
        $descuentos = $this->query->facturasPorDescuento($empresaId, $fecha);
        $recepciones = $this->recepcionesAnita->resumen($empresaId, $fecha);

        $totalJornada = $this->query->totalVentasJornada($empresaId, $fecha);
        $fechaCarbon = Carbon::parse($fecha);

        return [
            'empresa_id' => $empresaId,
            'fecha_jornada' => $fecha,
            'fecha_jornada_label' => $fechaCarbon->format('d/m/Y'),
            'mes_jornada_label' => $this->etiquetaMes($fechaCarbon),
            'total_ventas_jornada' => $totalJornada,
            'top10_cantidad' => $topCantidad,
            'top10_valor' => $topValor,
            'top10_mes_cantidad' => $topMesCantidad,
            'ventas_por_turno' => $porTurno,
            'ventas_por_puntoventa' => $porPv,
            'facturas_por_descuento' => $descuentos,
            'recepciones' => $recepciones,
            'recepciones_resumen' => $this->resumenRecepciones($recepciones),
            'charts' => [
                'turno' => $this->pieDesdeFilas($porTurno, 'etiqueta', 'total'),
                'puntoventa' => $this->pieDesdeFilas($porPv, 'nombre', 'total'),
                'descuento' => $this->pieDescuentos($descuentos),
                'recepciones_dia' => $this->pieRecepcionesPorProveedor($recepciones['dia'] ?? []),
                'recepciones_mes' => $this->pieRecepcionesPorProveedor($recepciones['mes'] ?? []),
                'articulos_dia' => $this->barDesdeTop10($topCantidad, 'cantidad'),
                'articulos_mes' => $this->barDesdeTop10($topMesCantidad, 'cantidad'),
            ],
        ];
    }

    private function etiquetaMes(Carbon $fecha): string
    {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return ($meses[(int) $fecha->format('n')] ?? $fecha->format('m')).' '.$fecha->format('Y');
    }

    /**
     * @param  list<array{sku?:string,descripcion?:string,cantidad?:float,importe?:float}>  $filas
     * @return array{labels:list<string>,values:list<float>,metric:string}
     */
    private function barDesdeTop10(array $filas, string $metric = 'cantidad'): array
    {
        $labels = [];
        $values = [];

        foreach ($filas as $fila) {
            $valor = $metric === 'importe'
                ? round((float) ($fila['importe'] ?? 0), 2)
                : round((float) ($fila['cantidad'] ?? 0), 2);
            if ($valor <= 0) {
                continue;
            }

            $sku = trim((string) ($fila['sku'] ?? ''));
            $descripcion = trim((string) ($fila['descripcion'] ?? ''));
            $etiqueta = $sku !== '' ? $sku : $descripcion;
            if ($sku !== '' && $descripcion !== '') {
                $etiqueta = $sku.' — '.$this->truncarTexto($descripcion, 32);
            }
            if ($etiqueta === '') {
                $etiqueta = 'Artículo';
            }

            $labels[] = $etiqueta;
            $values[] = $valor;
        }

        if ($labels !== []) {
            $labels = array_reverse($labels);
            $values = array_reverse($values);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'metric' => $metric,
        ];
    }

    private function truncarTexto(string $texto, int $max): string
    {
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, max(1, $max - 1))).'…';
    }

    private function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return Carbon::today()->format('Y-m-d');
        }

        return Carbon::parse($fecha)->format('Y-m-d');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{labels:list<string>,values:list<float>}
     */
    private function pieDesdeFilas(array $filas, string $labelKey, string $valueKey): array
    {
        $labels = [];
        $values = [];
        foreach ($filas as $fila) {
            $valor = round((float) ($fila[$valueKey] ?? 0), 2);
            if (abs($valor) <= 0.0001) {
                continue;
            }
            $labels[] = (string) ($fila[$labelKey] ?? '—');
            $values[] = $valor;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param  array{filas:list<array<string,mixed>>,sin_descuento:array{cantidad:int,importe:float}}  $descuentos
     * @return array{labels:list<string>,values:list<float>}
     */
    private function pieDescuentos(array $descuentos): array
    {
        $labels = [];
        $values = [];
        foreach ($descuentos['filas'] as $fila) {
            $importe = round((float) ($fila['importe'] ?? 0), 2);
            if (abs($importe) <= 0.0001) {
                continue;
            }
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            $nombre = trim((string) ($fila['nombre'] ?? ''));
            $labels[] = $codigo !== '' ? $codigo.' — '.$nombre : $nombre;
            $values[] = $importe;
        }

        $sin = $descuentos['sin_descuento'] ?? ['importe' => 0.0];
        $importeSin = round((float) ($sin['importe'] ?? 0), 2);
        if (abs($importeSin) > 0.0001) {
            $labels[] = 'Sin descuento';
            $values[] = $importeSin;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param  array{filas?:list<array<string,mixed>>,importe_total?:float,cantidad_comprobantes?:int}  $bloque
     * @return array{labels:list<string>,values:list<float>}
     */
    private function pieRecepcionesPorProveedor(array $bloque, int $maxSlices = 10): array
    {
        $filas = $bloque['filas'] ?? [];
        /** @var array<string, float> $porProveedor */
        $porProveedor = [];

        foreach ($filas as $fila) {
            $etiqueta = trim((string) ($fila['proveedor_nombre'] ?? ''));
            if ($etiqueta === '') {
                $etiqueta = trim((string) ($fila['proveedor'] ?? ''));
            }
            if ($etiqueta === '') {
                $etiqueta = 'Sin proveedor';
            }
            $importe = round((float) ($fila['importe'] ?? 0), 2);
            if (abs($importe) <= 0.0001) {
                continue;
            }
            $porProveedor[$etiqueta] = round(($porProveedor[$etiqueta] ?? 0) + $importe, 2);
        }

        if ($porProveedor === []) {
            return ['labels' => [], 'values' => []];
        }

        arsort($porProveedor, SORT_NUMERIC);

        if (count($porProveedor) <= $maxSlices) {
            return [
                'labels' => array_keys($porProveedor),
                'values' => array_values($porProveedor),
            ];
        }

        $labels = [];
        $values = [];
        $otros = 0.0;
        $i = 0;
        foreach ($porProveedor as $proveedor => $importe) {
            if ($i < $maxSlices - 1) {
                $labels[] = $proveedor;
                $values[] = $importe;
            } else {
                $otros = round($otros + $importe, 2);
            }
            $i++;
        }

        if (abs($otros) > 0.0001) {
            $labels[] = 'Otros ('.(count($porProveedor) - ($maxSlices - 1)).' prov.)';
            $values[] = $otros;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param  array<string, mixed>  $recepciones
     * @return array{
     *   dia_importe:float,
     *   mes_importe:float,
     *   dia_pct_mes:?float,
     *   proveedores_dia:int,
     *   proveedores_mes:int,
     *   comprobantes_dia:int,
     *   comprobantes_mes:int
     * }
     */
    private function resumenRecepciones(array $recepciones): array
    {
        $dia = $recepciones['dia'] ?? [];
        $mes = $recepciones['mes'] ?? [];
        $diaImporte = round((float) ($dia['importe_total'] ?? 0), 2);
        $mesImporte = round((float) ($mes['importe_total'] ?? 0), 2);

        $proveedoresDia = $this->contarProveedoresDistintos($dia['filas'] ?? []);
        $proveedoresMes = $this->contarProveedoresDistintos($mes['filas'] ?? []);

        $diaPctMes = null;
        if ($mesImporte > 0.0001) {
            $diaPctMes = round(($diaImporte / $mesImporte) * 100, 1);
        }

        return [
            'dia_importe' => $diaImporte,
            'mes_importe' => $mesImporte,
            'dia_pct_mes' => $diaPctMes,
            'proveedores_dia' => $proveedoresDia,
            'proveedores_mes' => $proveedoresMes,
            'comprobantes_dia' => (int) ($dia['cantidad_comprobantes'] ?? 0),
            'comprobantes_mes' => (int) ($mes['cantidad_comprobantes'] ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function contarProveedoresDistintos(array $filas): int
    {
        $set = [];
        foreach ($filas as $fila) {
            $prov = trim((string) ($fila['proveedor'] ?? ''));
            $set[$prov !== '' ? $prov : '__sin__'] = true;
        }

        return count($set);
    }
}
