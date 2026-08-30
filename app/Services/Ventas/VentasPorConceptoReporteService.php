<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Contable\Cuentacontable;
use App\Models\Ventas\Concepto_Venta;
use App\Support\Ventas\ConceptoVentaMatrizSupport;
use App\Support\Ventas\VentaImporteDosDecimalesSupport;
use App\Support\Ventas\VentasPorConceptoListadoFiltros;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VentasPorConceptoReporteService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, totales: array<string, float|int>}
     */
    public function generar(array $filtros): array
    {
        $datos = $this->consultarLineas($filtros);
        $filas = $this->aplanarFilas($datos, $filtros);

        return [
            'filas' => $filas,
            'totales' => $this->totalesGenerales($filas),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consultarLineas(array $filtros): Collection
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        $conceptoId = (int) ($filtros['concepto_venta_id'] ?? 0);
        $tipoId = (int) ($filtros['tipotransaccion_id'] ?? 0);

        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('concepto_venta as cv', 'cv.id', '=', 've.concepto_venta_id')
            ->leftJoin('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->leftJoin('cliente as cl', 'cl.id', '=', 'v.cliente_id')
            ->leftJoin('empresa as e', 'e.id', '=', 'pv.empresa_id')
            ->leftJoin('impuesto as imp', 'imp.id', '=', 've.impuesto_id')
            ->whereNotNull('ve.concepto_venta_id')
            ->where('pv.empresa_id', $empresaId)
            ->whereDate('v.fecha', '>=', $desde)
            ->whereDate('v.fecha', '<=', $hasta)
            ->where(function ($q) {
                $q->whereNull('v.nombre')
                    ->orWhereRaw("UPPER(TRIM(v.nombre)) NOT LIKE 'ANULADA%'");
            })
            ->where(function ($q) {
                $q->whereNull('tt.abreviatura')
                    ->orWhereRaw("UPPER(TRIM(tt.abreviatura)) <> 'PRE'");
            })
            ->select([
                've.id as emision_id',
                've.venta_id',
                've.concepto_venta_id',
                've.cantidad',
                've.precio',
                've.descuento',
                've.incluyeimpuesto',
                've.detalle',
                've.impuesto_id',
                'v.fecha',
                'v.numerocomprobante',
                'v.codigo as venta_codigo',
                'v.cliente_id',
                'v.tipotransaccion_id',
                'pv.empresa_id',
                'cv.codigo as concepto_codigo',
                'cv.nombre as concepto_nombre',
                'tt.abreviatura as tipo_abreviatura',
                'tt.nombre as tipo_nombre',
                DB::raw('tt.signo as tipo_signo'),
                'pv.codigo as puntoventa_codigo',
                'e.nombre as nombreempresa',
                'cl.codigo as cliente_codigo',
                'cl.nombre as cliente_nombre',
                'imp.valor as tasa_iva',
            ])
            ->orderBy('cv.codigo')
            ->orderBy('v.fecha')
            ->orderBy('tt.abreviatura')
            ->orderBy('v.numerocomprobante')
            ->orderBy('ve.id');

        if ($conceptoId > 0) {
            $query->where('ve.concepto_venta_id', $conceptoId);
        }
        if ($tipoId > 0) {
            $query->where('v.tipotransaccion_id', $tipoId);
        }

        $datos = $query->get();
        $this->enriquecerCuentas($datos, $empresaId);

        return $datos;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function aplanarFilas(Collection $datos, array $filtros = []): array
    {
        $porCuenta = VentasPorConceptoListadoFiltros::agrupaPorCuenta($filtros);
        $detalles = [];
        foreach ($datos as $row) {
            $detalles[] = $this->filaDetalle($row);
        }

        usort($detalles, function (array $a, array $b) use ($porCuenta): int {
            if ($porCuenta) {
                $cmp = strnatcasecmp(
                    self::claveOrdenGrupo($a['cuenta_codigo'] ?? ''),
                    self::claveOrdenGrupo($b['cuenta_codigo'] ?? ''),
                );
            } else {
                $cmp = strnatcasecmp(
                    (string) ($a['concepto_codigo'] ?? ''),
                    (string) ($b['concepto_codigo'] ?? ''),
                );
            }
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmpFecha = strcmp((string) ($a['fecha_ymd'] ?? ''), (string) ($b['fecha_ymd'] ?? ''));
            if ($cmpFecha !== 0) {
                return $cmpFecha;
            }
            $cmpTipo = strcmp((string) ($a['tipo'] ?? ''), (string) ($b['tipo'] ?? ''));
            if ($cmpTipo !== 0) {
                return $cmpTipo;
            }

            return strcmp((string) ($a['comprobante'] ?? ''), (string) ($b['comprobante'] ?? ''));
        });

        $filas = [];
        $grupoClave = null;
        $grupoMeta = [];
        $subtotal = self::totalesVacios();
        $totalFinal = self::totalesVacios();

        foreach ($detalles as $detalle) {
            $clave = $porCuenta
                ? self::claveGrupoCuenta($detalle)
                : (string) ($detalle['concepto_codigo'] ?? '');

            if ($grupoClave !== null && $clave !== $grupoClave) {
                $filas[] = self::filaSubtotalGrupo($porCuenta, $grupoMeta, $subtotal);
                $subtotal = self::totalesVacios();
            }

            if ($clave !== $grupoClave) {
                $grupoClave = $clave;
                $grupoMeta = $detalle;
            }

            $filas[] = $detalle;
            self::acumularTotales($subtotal, $detalle);
            self::acumularTotales($totalFinal, $detalle);
        }

        if ($grupoClave !== null) {
            $filas[] = self::filaSubtotalGrupo($porCuenta, $grupoMeta, $subtotal);
        }

        if ($detalles !== []) {
            $filas[] = self::filaTotalFinal($totalFinal);
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(10, min(200, $perPage));
        $page = max(1, $page);
        $total = count($filas);
        $offset = ($page - 1) * $perPage;

        return new LengthAwarePaginator(
            array_slice($filas, $offset, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float|int>
     */
    public function totalesGenerales(array $filas): array
    {
        $totales = self::totalesVacios();
        $cantidadDetalle = 0;

        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? '') !== 'detalle') {
                continue;
            }
            $cantidadDetalle++;
            self::acumularTotales($totales, $fila);
        }

        $totales['cantidad_detalle'] = $cantidadDetalle;

        return $totales;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function armarSubtitulo(array $filtros, string $empresaTexto, string $tipoTexto, string $conceptoTexto): string
    {
        $partes = [];
        if ($empresaTexto !== '') {
            $partes[] = 'Empresa: '.$empresaTexto;
        }
        $periodo = VentasPorConceptoListadoFiltros::formatearPeriodoTexto($filtros);
        if ($periodo !== '') {
            $partes[] = 'Período: '.$periodo;
        }
        if ($conceptoTexto !== '') {
            $partes[] = 'Concepto: '.$conceptoTexto;
        }
        if ($tipoTexto !== '') {
            $partes[] = 'Tipo: '.$tipoTexto;
        }
        $partes[] = VentasPorConceptoListadoFiltros::agrupaPorCuenta($filtros)
            ? 'Agrupado por cuenta'
            : 'Agrupado por concepto';

        return implode(' · ', $partes);
    }

    /**
     * @return array<string, mixed>
     */
    private function filaDetalle(object $row): array
    {
        $importes = $this->importesRenglon($row);
        $fecha = (string) ($row->fecha ?? '');
        $fechaTxt = $fecha !== '' ? date('d/m/Y', strtotime($fecha)) : '';

        return [
            'tipo_fila' => 'detalle',
            'nombreempresa' => (string) ($row->nombreempresa ?? ''),
            'fecha' => $fechaTxt,
            'fecha_ymd' => $fecha !== '' ? substr($fecha, 0, 10) : '',
            'tipo' => (string) ($row->tipo_abreviatura ?? ''),
            'comprobante' => $this->textoComprobante($row),
            'venta_id' => (int) ($row->venta_id ?? 0),
            'cliente_id' => (int) ($row->cliente_id ?? 0),
            'cliente' => trim((string) ($row->cliente_codigo ?? '').' '.(string) ($row->cliente_nombre ?? '')),
            'concepto_venta_id' => (int) ($row->concepto_venta_id ?? 0),
            'concepto_codigo' => (string) ($row->concepto_codigo ?? ''),
            'concepto_nombre' => (string) ($row->concepto_nombre ?? ''),
            'cuentacontable_id' => (int) ($row->cuentacontable_id ?? 0) ?: null,
            'cuenta_codigo' => (string) ($row->cuenta_codigo ?? ''),
            'cuenta_nombre' => (string) ($row->cuenta_nombre ?? ''),
            'centrocosto_codigo' => (string) ($row->centrocosto_codigo ?? ''),
            'descripcion' => trim((string) ($row->detalle ?? '')) !== ''
                ? (string) $row->detalle
                : (string) ($row->concepto_nombre ?? ''),
            'cantidad' => $importes['cantidad'],
            'precio' => $importes['precio'],
            'neto' => $importes['neto'],
            'iva' => $importes['iva'],
            'total' => $importes['total'],
        ];
    }

    /**
     * @param  array<string, mixed>  $grupo
     * @param  array<string, float|int>  $totales
     * @return array<string, mixed>
     */
    private static function filaSubtotalGrupo(bool $porCuenta, array $grupo, array $totales): array
    {
        $base = array_merge($totales, [
            'concepto_venta_id' => (int) ($grupo['concepto_venta_id'] ?? 0),
            'concepto_codigo' => (string) ($grupo['concepto_codigo'] ?? ''),
            'concepto_nombre' => (string) ($grupo['concepto_nombre'] ?? ''),
            'cuentacontable_id' => $grupo['cuentacontable_id'] ?? null,
            'cuenta_codigo' => (string) ($grupo['cuenta_codigo'] ?? ''),
            'cuenta_nombre' => (string) ($grupo['cuenta_nombre'] ?? ''),
            'centrocosto_codigo' => (string) ($grupo['centrocosto_codigo'] ?? ''),
            'nombreempresa' => '',
            'fecha' => '',
            'tipo' => '',
            'comprobante' => '',
            'venta_id' => 0,
            'cliente_id' => 0,
            'cliente' => '',
            'precio' => null,
        ]);

        if ($porCuenta) {
            $base['tipo_fila'] = 'subtotal_cuenta';
            $base['descripcion'] = 'Subtotal cuenta';
        } else {
            $base['tipo_fila'] = 'subtotal_concepto';
            $base['descripcion'] = 'Subtotal concepto';
        }

        return $base;
    }

    /**
     * @param  array<string, float|int>  $totales
     * @return array<string, mixed>
     */
    private static function filaTotalFinal(array $totales): array
    {
        return array_merge($totales, [
            'tipo_fila' => 'total_final',
            'concepto_venta_id' => 0,
            'concepto_codigo' => '',
            'concepto_nombre' => '',
            'cuentacontable_id' => null,
            'cuenta_codigo' => '',
            'cuenta_nombre' => '',
            'centrocosto_codigo' => '',
            'descripcion' => 'TOTAL FINAL',
            'nombreempresa' => '',
            'fecha' => '',
            'tipo' => '',
            'comprobante' => '',
            'venta_id' => 0,
            'cliente_id' => 0,
            'cliente' => '',
            'precio' => null,
        ]);
    }

    /**
     * Cuenta / CC de la matriz vigente (mismo criterio que al facturar).
     */
    private function enriquecerCuentas(Collection $datos, int $empresaId): void
    {
        if ($datos->isEmpty()) {
            return;
        }

        $conceptoIds = $datos->pluck('concepto_venta_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $conceptos = $conceptoIds === []
            ? collect()
            : Concepto_Venta::query()
                ->with('cuentas')
                ->whereIn('id', $conceptoIds)
                ->get()
                ->keyBy('id');

        $cuentaIds = [];
        $ccIds = [];
        foreach ($datos as $row) {
            $concepto = $conceptos->get((int) ($row->concepto_venta_id ?? 0));
            if ($concepto === null) {
                $row->cuentacontable_id = null;
                $row->centrocosto_id_concepto = null;
                continue;
            }
            $fecha = substr((string) ($row->fecha ?? ''), 0, 10);
            $tipoId = (int) ($row->tipotransaccion_id ?? 0);
            $res = ConceptoVentaMatrizSupport::resolverCuenta(
                $concepto,
                $empresaId > 0 ? $empresaId : (int) ($row->empresa_id ?? 0),
                $tipoId > 0 ? $tipoId : null,
                $fecha !== '' ? $fecha : null,
            );
            $row->cuentacontable_id = $res['cuentacontable_id'];
            $row->centrocosto_id_concepto = $res['centrocosto_id'];
            if ($res['cuentacontable_id']) {
                $cuentaIds[] = (int) $res['cuentacontable_id'];
            }
            if ($res['centrocosto_id']) {
                $ccIds[] = (int) $res['centrocosto_id'];
            }
        }

        $cuentas = $cuentaIds === []
            ? collect()
            : Cuentacontable::query()
                ->whereIn('id', array_values(array_unique($cuentaIds)))
                ->get(['id', 'codigo', 'nombre'])
                ->keyBy('id');

        $centros = $ccIds === []
            ? collect()
            : DB::table('centrocosto')
                ->whereIn('id', array_values(array_unique($ccIds)))
                ->get(['id', 'codigo'])
                ->keyBy('id');

        foreach ($datos as $row) {
            $cta = $cuentas->get((int) ($row->cuentacontable_id ?? 0));
            $row->cuenta_codigo = (string) ($cta?->codigo ?? '');
            $row->cuenta_nombre = (string) ($cta?->nombre ?? '');
            $cc = $centros->get((int) ($row->centrocosto_id_concepto ?? 0));
            $row->centrocosto_codigo = (string) ($cc?->codigo ?? '');
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private static function claveGrupoCuenta(array $fila): string
    {
        $id = (int) ($fila['cuentacontable_id'] ?? 0);
        if ($id > 0) {
            return 'c:'.$id;
        }

        return 's:'.trim((string) ($fila['cuenta_codigo'] ?? ''));
    }

    private static function claveOrdenGrupo(string $codigo): string
    {
        $codigo = trim($codigo);

        return $codigo !== '' ? $codigo : 'ÿ';
    }

    /**
     * @return array{cantidad: float, neto: float, iva: float, total: float, cantidad_detalle: int}
     */
    private static function totalesVacios(): array
    {
        return [
            'cantidad' => 0.0,
            'neto' => 0.0,
            'iva' => 0.0,
            'total' => 0.0,
            'cantidad_detalle' => 0,
        ];
    }

    /**
     * @param  array<string, float|int>  $totales
     * @param  array<string, mixed>  $fila
     */
    private static function acumularTotales(array &$totales, array $fila): void
    {
        $totales['cantidad'] = VentaImporteDosDecimalesSupport::redondear(
            (float) $totales['cantidad'] + (float) ($fila['cantidad'] ?? 0)
        );
        $totales['neto'] = VentaImporteDosDecimalesSupport::redondear(
            (float) $totales['neto'] + (float) ($fila['neto'] ?? 0)
        );
        $totales['iva'] = VentaImporteDosDecimalesSupport::redondear(
            (float) $totales['iva'] + (float) ($fila['iva'] ?? 0)
        );
        $totales['total'] = VentaImporteDosDecimalesSupport::redondear(
            (float) $totales['total'] + (float) ($fila['total'] ?? 0)
        );
    }

    /**
     * @return array{cantidad: float, precio: float, neto: float, iva: float, total: float}
     */
    private function importesRenglon(object $row): array
    {
        $cantidad = (float) ($row->cantidad ?? 0);
        $precio = (float) ($row->precio ?? 0);
        $tasa = (float) ($row->tasa_iva ?? 0);
        $incluye = (string) ($row->incluyeimpuesto ?? 'N');
        $incluyeIva = $incluye !== 'N' && $incluye !== '2';
        $dtoPct = (float) ($row->descuento ?? 0);

        $bruto = $cantidad * $precio;
        if ($incluyeIva && $tasa > 0) {
            $neto = $bruto / (1.0 + ($tasa / 100.0));
        } else {
            $neto = $bruto;
        }

        if ($dtoPct > 0 && $dtoPct < 100) {
            $neto *= (1.0 - ($dtoPct / 100.0));
        }

        $neto = VentaImporteDosDecimalesSupport::redondear($neto);
        $iva = $tasa > 0
            ? VentaImporteDosDecimalesSupport::redondear($neto * $tasa / 100.0)
            : 0.0;
        $total = VentaImporteDosDecimalesSupport::redondear($neto + $iva);

        $signo = ((int) ($row->tipo_signo ?? 1)) < 0 ? -1.0 : 1.0;

        return [
            'cantidad' => VentaImporteDosDecimalesSupport::redondear($cantidad * $signo),
            'precio' => VentaImporteDosDecimalesSupport::redondear($precio),
            'neto' => VentaImporteDosDecimalesSupport::redondear($neto * $signo),
            'iva' => VentaImporteDosDecimalesSupport::redondear($iva * $signo),
            'total' => VentaImporteDosDecimalesSupport::redondear($total * $signo),
        ];
    }

    private function textoComprobante(object $row): string
    {
        $codigo = trim((string) ($row->venta_codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }

        $tipo = trim((string) ($row->tipo_abreviatura ?? ''));
        $pv = trim((string) ($row->puntoventa_codigo ?? ''));
        $nro = (int) ($row->numerocomprobante ?? 0);

        return trim($tipo.' '.$pv.($nro > 0 ? '-'.str_pad((string) $nro, 8, '0', STR_PAD_LEFT) : ''));
    }
}
