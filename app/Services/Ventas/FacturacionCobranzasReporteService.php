<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Caja\Cobranza;
use App\Models\Ventas\Transporte;
use App\Models\Ventas\Venta;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportClaveSupport;
use App\Support\Ventas\FacturacionCobranzasAnitaBridgeReader;
use App\Support\Ventas\FacturacionCobranzasListadoFiltros;
use App\Support\Ventas\FacturacionCobranzasMediosSupport;
use App\Support\Ventas\IvaVentas\IvaVentasDesgloseSupport;
use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FacturacionCobranzasReporteService
{
    public function __construct(
        private readonly FacturacionCobranzasAnitaBridgeReader $anitaReader,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array<string, float|int>,
     *   errores: list<string>,
     *   avisos: list<string>
     * }
     */
    public function generar(array $filtros): array
    {
        $errores = [];
        $avisos = [];
        $detalles = [];

        $fuente = FacturacionCobranzasListadoFiltros::normalizarFuente($filtros['fuente'] ?? null);
        $incluye = FacturacionCobranzasListadoFiltros::normalizarIncluye($filtros['incluye'] ?? null);

        $clavesErp = [];

        if ($fuente !== FacturacionCobranzasListadoFiltros::FUENTE_ANITA) {
            if ($incluye !== FacturacionCobranzasListadoFiltros::INCLUYE_COBRANZAS) {
                foreach ($this->leerFacturasErp($filtros) as $fila) {
                    $detalles[] = $fila;
                    if (($fila['clave'] ?? '') !== '') {
                        $clavesErp[$fila['clave']] = true;
                    }
                }
            }
            if ($incluye !== FacturacionCobranzasListadoFiltros::INCLUYE_FACTURAS) {
                foreach ($this->leerCobranzasErp($filtros) as $fila) {
                    $detalles[] = $fila;
                    if (($fila['clave'] ?? '') !== '') {
                        $clavesErp[$fila['clave']] = true;
                    }
                }
            }
        }

        if ($fuente !== FacturacionCobranzasListadoFiltros::FUENTE_ERP) {
            if ($incluye !== FacturacionCobranzasListadoFiltros::INCLUYE_COBRANZAS) {
                foreach ($this->leerFacturasAnita($filtros, $clavesErp, $errores) as $fila) {
                    $detalles[] = $fila;
                }
            }
            if ($incluye !== FacturacionCobranzasListadoFiltros::INCLUYE_FACTURAS) {
                foreach ($this->leerCobranzasAnita($filtros, $clavesErp, $errores) as $fila) {
                    $detalles[] = $fila;
                }
            }
        }

        $detalles = $this->filtrarReparto($detalles, $filtros);
        $this->ordenarDetalles($detalles, $filtros);

        $soloTotales = ! empty($filtros['solo_totales']);
        $filas = $this->aplanarConCortes($detalles, $filtros, $soloTotales);
        $totales = $this->totalesDesdeDetalles($detalles);

        if ($errores !== []) {
            $avisos[] = 'Algunas lecturas Anita fallaron; el listado puede estar incompleto.';
        }

        return [
            'filas' => $filas,
            'totales' => $totales,
            'errores' => $errores,
            'avisos' => $avisos,
        ];
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
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function armarSubtitulo(array $filtros, string $empresaTexto): string
    {
        $parts = array_filter([
            $empresaTexto !== '' ? 'Empresa: '.$empresaTexto : null,
            FacturacionCobranzasListadoFiltros::formatearPeriodoTexto($filtros),
            FacturacionCobranzasListadoFiltros::formatearOrdenTexto($filtros),
            FacturacionCobranzasListadoFiltros::formatearFuenteTexto($filtros),
        ]);

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function leerFacturasErp(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        $monedaId = (int) ($filtros['moneda_id'] ?? 1);
        $monedaOrigen = ! empty($filtros['moneda_origen']);

        $ventas = Venta::query()
            ->with([
                'tipotransacciones:id,abreviatura,nombre,signo',
                'clientes:id,codigo,nombre',
                'puntoventas:id,codigo,empresa_id',
                'transportes:id,codigo,nombre',
                'venta_impuestos',
            ])
            ->whereHas('puntoventas', fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->where(function ($q) {
                $q->whereNull('nombre')
                    ->orWhereRaw("UPPER(TRIM(nombre)) NOT LIKE 'ANULADA%'");
            })
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $kilosPorVenta = $this->kilosPorVentaIds($ventas->pluck('id')->all());

        $out = [];
        foreach ($ventas as $venta) {
            if (VentaNumerocomprobanteUnicidadSupport::esEspejoAnitaCedido((string) $venta->codigo)) {
                continue;
            }
            $abrev = strtoupper(trim((string) ($venta->tipotransacciones?->abreviatura ?? '')));
            if ($abrev === 'PRE') {
                continue;
            }
            if ($monedaOrigen && (int) $venta->moneda_id !== $monedaId) {
                continue;
            }

            $fechaYmd = $this->fechaYmd($venta->fecha);
            $coef = $this->coeficienteMoneda(
                $monedaId,
                (int) ($venta->moneda_id ?? 1),
                (float) ($venta->cotizacion ?? 0),
                $fechaYmd,
                $monedaOrigen,
            );
            if ($coef === null) {
                continue;
            }

            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta((string) $venta->codigo)
                ?? ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                    $abrev,
                    IvaVentasDesgloseSupport::letra($venta),
                    (int) preg_replace('/\D/', '', (string) ($venta->puntoventas?->codigo ?? '0')),
                    (int) $venta->numerocomprobante,
                );

            $montos = IvaVentasDesgloseSupport::columnasDesdeVenta($venta, $coef);
            $signo = IvaVentasDesgloseSupport::signoVenta($venta);
            $abastos = $signo * $coef * $this->logisticaDesdeVenta($venta);
            $gravado = (float) ($montos['neto_gravado'] ?? 0);
            $total = (float) ($montos['total'] ?? 0);
            $varios = round($total - $abastos - $gravado, 2);

            $out[] = $this->filaBase([
                'tipo_fila' => 'factura',
                'tip' => $abrev !== '' ? $abrev : 'FAC',
                'numero' => $this->formatearNumeroDesdeCodigo((string) $venta->codigo, (int) $venta->numerocomprobante),
                'fecha' => $fechaYmd !== '' ? date('d/m/Y', strtotime($fechaYmd)) : '',
                'fecha_ymd' => $fechaYmd,
                'cliente_id' => (int) ($venta->cliente_id ?? 0),
                'cliente_codigo' => (string) ($venta->clientes?->codigo ?? ''),
                'cliente_nombre' => (string) ($venta->clientes?->nombre ?? $venta->nombre ?? ''),
                'kilos' => round((float) ($kilosPorVenta[(int) $venta->id] ?? 0) * $signo, 2),
                'abastos' => round($abastos, 2),
                'gravado' => round($gravado, 2),
                'varios_comp' => $varios,
                'total_comp' => round($total, 2),
                'venta_id' => (int) $venta->id,
                'transporte_codigo' => (string) ($venta->transportes?->codigo ?? ''),
                'transporte_nombre' => (string) ($venta->transportes?->nombre ?? ''),
                'transporte_orden' => (int) ($venta->transportes?->codigo ?? 0),
                'clave' => $clave,
                'fuente' => 'erp',
                'nombreempresa' => (string) ($venta->puntoventas?->empresas?->nombre ?? ''),
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function leerCobranzasErp(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        $monedaId = (int) ($filtros['moneda_id'] ?? 1);
        $monedaOrigen = ! empty($filtros['moneda_origen']);

        $cobranzas = Cobranza::query()
            ->with([
                'clientes:id,codigo,nombre',
                'tipotransaccioncajas:id,abreviatura,nombre',
                'cheques',
                'caja_movimientos.caja_movimiento_cuentacajas.cuentacajas',
                'cobranza_retenciones',
            ])
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($cobranzas as $cobranza) {
            if ($monedaOrigen && (int) $cobranza->moneda_id !== $monedaId) {
                continue;
            }
            $fechaYmd = $this->fechaYmd($cobranza->fecha);
            $coef = $this->coeficienteMoneda(
                $monedaId,
                (int) ($cobranza->moneda_id ?? 1),
                (float) ($cobranza->cotizacion ?? 0),
                $fechaYmd,
                $monedaOrigen,
            );
            if ($coef === null) {
                continue;
            }

            $tip = strtoupper(trim((string) ($cobranza->tipotransaccioncajas?->abreviatura ?? 'COB')));
            if (! in_array($tip, ['COB', 'COC', 'COA'], true)) {
                $tip = 'COB';
            }
            $nro = (int) ($cobranza->numerotransaccion ?? $cobranza->id);
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento($tip, ' ', 0, $nro);
            $medios = $this->mediosDesdeCobranzaErp($cobranza, $coef);

            $out[] = $this->filaBase([
                'tipo_fila' => 'cobranza',
                'tip' => $tip,
                'numero' => (string) $nro,
                'fecha' => $fechaYmd !== '' ? date('d/m/Y', strtotime($fechaYmd)) : '',
                'fecha_ymd' => $fechaYmd,
                'cliente_id' => (int) ($cobranza->cliente_id ?? 0),
                'cliente_codigo' => (string) ($cobranza->clientes?->codigo ?? ''),
                'cliente_nombre' => (string) ($cobranza->clientes?->nombre ?? ''),
                'efectivo' => $medios['efectivo'],
                'cheques' => $medios['cheques'],
                'valores' => $medios['valores'],
                'varios_cobro' => $medios['varios'],
                'total_cobro' => $medios['total'],
                'cobranza_id' => (int) $cobranza->id,
                'transporte_codigo' => '',
                'transporte_nombre' => '',
                'transporte_orden' => 0,
                'clave' => $clave,
                'fuente' => 'erp',
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, true>  $clavesErp
     * @param  list<string>  $errores
     * @return list<array<string, mixed>>
     */
    private function leerFacturasAnita(array $filtros, array $clavesErp, array &$errores): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $monedaId = (int) ($filtros['moneda_id'] ?? 1);
        $monedaOrigen = ! empty($filtros['moneda_origen']);
        $mapaTransporte = $this->mapaTransporteCodigoNombre();

        $filas = $this->anitaReader->listarVentas(
            $empresaId,
            (string) $filtros['fecha_desde'],
            (string) $filtros['fecha_hasta'],
            $errores,
        );

        $out = [];
        foreach ($filas as $fila) {
            $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['ven_tipo'] ?? ''));
            $letra = ClienteCuentacorrienteAnitaImportClaveSupport::letra((string) ($fila['ven_letra'] ?? ''));
            $suc = (int) ($fila['ven_sucursal'] ?? 0);
            $nro = (int) ($fila['ven_nro'] ?? 0);
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento($tipo, $letra, $suc, $nro);
            if (isset($clavesErp[$clave])) {
                continue;
            }

            $codMonAnita = (int) preg_replace('/\D/', '', (string) ($fila['ven_cod_mon'] ?? '1'));
            if ($codMonAnita <= 0) {
                $codMonAnita = 1;
            }
            if ($monedaOrigen && $codMonAnita !== $monedaId) {
                continue;
            }

            $fechaIso = ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita($fila['ven_fecha'] ?? '');
            $coef = $this->coeficienteMoneda(
                $monedaId,
                $codMonAnita,
                (float) ($fila['ven_cotizacion'] ?? 0),
                $fechaIso,
                $monedaOrigen,
            );
            if ($coef === null) {
                continue;
            }

            $signo = $this->signoTipoAnita($tipo);
            $abastos = $signo * $coef * (float) ($fila['ven_tot_abasto'] ?? 0);
            $gravado = $signo * $coef * ((float) ($fila['ven_gravado'] ?? 0) + (float) ($fila['ven_gravado_ot'] ?? 0));
            $total = $signo * $coef * (float) ($fila['ven_monto'] ?? 0);
            $varios = round($total - $abastos - $gravado, 2);
            $trCodigo = (string) ((int) ($fila['ven_transporte'] ?? 0));

            $out[] = $this->filaBase([
                'tipo_fila' => 'factura',
                'tip' => $tipo,
                'numero' => $this->formatearNumeroAnita($letra, $suc, $nro),
                'fecha' => $fechaIso !== '' ? date('d/m/Y', strtotime($fechaIso)) : '',
                'fecha_ymd' => $fechaIso,
                'cliente_codigo' => ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoErp((string) ($fila['ven_cliente'] ?? '')),
                'cliente_nombre' => trim((string) ($fila['ven_nombre_cliente'] ?? '')),
                'abastos' => round($abastos, 2),
                'gravado' => round($gravado, 2),
                'varios_comp' => $varios,
                'total_comp' => round($total, 2),
                'transporte_codigo' => $trCodigo === '0' ? '' : $trCodigo,
                'transporte_nombre' => $mapaTransporte[(int) $trCodigo] ?? '',
                'transporte_orden' => (int) $trCodigo,
                'clave' => $clave,
                'fuente' => 'anita',
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, true>  $clavesErp
     * @param  list<string>  $errores
     * @return list<array<string, mixed>>
     */
    private function leerCobranzasAnita(array $filtros, array $clavesErp, array &$errores): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $monedaId = (int) ($filtros['moneda_id'] ?? 1);
        $monedaOrigen = ! empty($filtros['moneda_origen']);
        $mapaTransporte = $this->mapaTransporteCodigoNombre();

        $filas = $this->anitaReader->listarCobranzas(
            $empresaId,
            (string) $filtros['fecha_desde'],
            (string) $filtros['fecha_hasta'],
            $errores,
        );

        $out = [];
        foreach ($filas as $fila) {
            $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['pag_tipo'] ?? ''));
            $letra = ClienteCuentacorrienteAnitaImportClaveSupport::letra((string) ($fila['pag_letra'] ?? ' '));
            $suc = (int) ($fila['pag_sucursal'] ?? 0);
            $nro = (int) ($fila['pag_rec'] ?? 0);
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento($tipo, $letra, $suc, $nro);
            if (isset($clavesErp[$clave])) {
                continue;
            }

            $fechaIso = ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita($fila['pag_fecha'] ?? '');
            $coef = $this->coeficienteMoneda(
                $monedaId,
                1,
                (float) ($fila['pag_cotizacion'] ?? 0),
                $fechaIso,
                $monedaOrigen,
            );
            if ($coef === null) {
                continue;
            }

            $mediosRaw = $fila['_medios'] ?? FacturacionCobranzasMediosSupport::vacios();
            $medios = [
                'efectivo' => round((float) ($mediosRaw['efectivo'] ?? 0) * $coef, 2),
                'cheques' => round((float) ($mediosRaw['cheques'] ?? 0) * $coef, 2),
                'valores' => round((float) ($mediosRaw['valores'] ?? 0) * $coef, 2),
                'varios' => round((float) ($mediosRaw['varios'] ?? 0) * $coef, 2),
                'total' => round((float) ($mediosRaw['total'] ?? 0) * $coef, 2),
            ];
            $trCodigo = (string) ((int) ($fila['pag_transporte'] ?? 0));

            $out[] = $this->filaBase([
                'tipo_fila' => 'cobranza',
                'tip' => $tipo,
                'numero' => $this->formatearNumeroAnita($letra, $suc, $nro),
                'fecha' => $fechaIso !== '' ? date('d/m/Y', strtotime($fechaIso)) : '',
                'fecha_ymd' => $fechaIso,
                'cliente_codigo' => ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoErp((string) ($fila['pag_pro'] ?? '')),
                'cliente_nombre' => trim((string) ($fila['pag_entregado_a'] ?? '')),
                'efectivo' => $medios['efectivo'],
                'cheques' => $medios['cheques'],
                'valores' => $medios['valores'],
                'varios_cobro' => $medios['varios'],
                'total_cobro' => $medios['total'],
                'transporte_codigo' => $trCodigo === '0' ? '' : $trCodigo,
                'transporte_nombre' => $mapaTransporte[(int) $trCodigo] ?? '',
                'transporte_orden' => (int) $trCodigo,
                'clave' => $clave,
                'fuente' => 'anita',
            ]);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $detalles
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function filtrarReparto(array $detalles, array $filtros): array
    {
        [$desde, $hasta] = FacturacionCobranzasListadoFiltros::rangoRepartoCodigos($filtros);
        if ($desde <= 0 && $hasta >= 999999) {
            return $detalles;
        }

        return array_values(array_filter($detalles, static function (array $f) use ($desde, $hasta): bool {
            $cod = (int) ($f['transporte_orden'] ?? 0);
            if ($cod <= 0) {
                // Sin reparto: incluir solo si el rango abarca «todos»
                return $desde <= 0;
            }

            return $cod >= $desde && $cod <= $hasta;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $detalles
     * @param  array<string, mixed>  $filtros
     */
    private function ordenarDetalles(array &$detalles, array $filtros): void
    {
        $porReparto = FacturacionCobranzasListadoFiltros::normalizarOrden($filtros['orden'] ?? null)
            === FacturacionCobranzasListadoFiltros::ORDEN_REPARTO;

        usort($detalles, static function (array $a, array $b) use ($porReparto): int {
            if ($porReparto) {
                $cmp = ((int) ($a['transporte_orden'] ?? 0)) <=> ((int) ($b['transporte_orden'] ?? 0));
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
            $cmp = strcmp((string) ($a['fecha_ymd'] ?? ''), (string) ($b['fecha_ymd'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp((string) ($a['tip'] ?? ''), (string) ($b['tip'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['numero'] ?? ''), (string) ($b['numero'] ?? ''));
        });
    }

    /**
     * @param  list<array<string, mixed>>  $detalles
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function aplanarConCortes(array $detalles, array $filtros, bool $soloTotales): array
    {
        $porReparto = FacturacionCobranzasListadoFiltros::normalizarOrden($filtros['orden'] ?? null)
            === FacturacionCobranzasListadoFiltros::ORDEN_REPARTO;

        $filas = [];
        $sub = self::totalesVacios();
        $total = self::totalesVacios();
        $grupoOrden = null;
        $grupoMeta = [];

        foreach ($detalles as $detalle) {
            $claveGrupo = $porReparto ? (string) ((int) ($detalle['transporte_orden'] ?? 0)) : null;

            if ($porReparto && $grupoOrden !== null && $claveGrupo !== $grupoOrden) {
                $filas[] = $this->filaSubtotalReparto($grupoMeta, $sub);
                $sub = self::totalesVacios();
            }

            if ($porReparto && $claveGrupo !== $grupoOrden) {
                $grupoOrden = $claveGrupo;
                $grupoMeta = $detalle;
            }

            if (! $soloTotales) {
                $filas[] = $detalle;
            }
            self::acumular($sub, $detalle);
            self::acumular($total, $detalle);
        }

        if ($porReparto && $grupoOrden !== null) {
            $filas[] = $this->filaSubtotalReparto($grupoMeta, $sub);
        }

        if ($detalles !== []) {
            $filas[] = $this->filaTotalGeneral($total);
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $detalles
     * @return array<string, float|int>
     */
    private function totalesDesdeDetalles(array $detalles): array
    {
        $t = self::totalesVacios();
        foreach ($detalles as $d) {
            self::acumular($t, $d);
        }
        $t['cantidad_detalle'] = count($detalles);

        return $t;
    }

    /**
     * @return array<string, float|int>
     */
    private static function totalesVacios(): array
    {
        return [
            'kilos' => 0.0,
            'abastos' => 0.0,
            'gravado' => 0.0,
            'varios_comp' => 0.0,
            'total_comp' => 0.0,
            'efectivo' => 0.0,
            'cheques' => 0.0,
            'valores' => 0.0,
            'varios_cobro' => 0.0,
            'total_cobro' => 0.0,
            'cantidad_detalle' => 0,
        ];
    }

    /**
     * @param  array<string, float|int>  $acc
     * @param  array<string, mixed>  $fila
     */
    private static function acumular(array &$acc, array $fila): void
    {
        foreach ([
            'kilos', 'abastos', 'gravado', 'varios_comp', 'total_comp',
            'efectivo', 'cheques', 'valores', 'varios_cobro', 'total_cobro',
        ] as $k) {
            $acc[$k] = round((float) ($acc[$k] ?? 0) + (float) ($fila[$k] ?? 0), 2);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, float|int>  $totales
     * @return array<string, mixed>
     */
    private function filaSubtotalReparto(array $meta, array $totales): array
    {
        $codigo = (string) ($meta['transporte_codigo'] ?? '');
        $nombre = (string) ($meta['transporte_nombre'] ?? '');
        $label = trim('Total reparto: '.$codigo.($nombre !== '' ? ' '.$nombre : ''));

        return $this->filaBase(array_merge($totales, [
            'tipo_fila' => 'subtotal_reparto',
            'tip' => '',
            'numero' => '',
            'fecha' => '',
            'cliente_codigo' => '',
            'cliente_nombre' => $label,
            'fuente' => '',
        ]));
    }

    /**
     * @param  array<string, float|int>  $totales
     * @return array<string, mixed>
     */
    private function filaTotalGeneral(array $totales): array
    {
        return $this->filaBase(array_merge($totales, [
            'tipo_fila' => 'total_final',
            'tip' => '',
            'numero' => '',
            'fecha' => '',
            'cliente_codigo' => '',
            'cliente_nombre' => 'Total general',
            'fuente' => '',
        ]));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function filaBase(array $extra): array
    {
        return array_merge([
            'tipo_fila' => 'factura',
            'tip' => '',
            'numero' => '',
            'fecha' => '',
            'fecha_ymd' => '',
            'cliente_id' => 0,
            'cliente_codigo' => '',
            'cliente_nombre' => '',
            'kilos' => 0.0,
            'abastos' => 0.0,
            'gravado' => 0.0,
            'varios_comp' => 0.0,
            'total_comp' => 0.0,
            'efectivo' => 0.0,
            'cheques' => 0.0,
            'valores' => 0.0,
            'varios_cobro' => 0.0,
            'total_cobro' => 0.0,
            'venta_id' => 0,
            'cobranza_id' => 0,
            'transporte_codigo' => '',
            'transporte_nombre' => '',
            'transporte_orden' => 0,
            'clave' => '',
            'fuente' => '',
            'nombreempresa' => '',
        ], $extra);
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, float>
     */
    private function kilosPorVentaIds(array $ventaIds): array
    {
        if ($ventaIds === []) {
            return [];
        }

        return DB::table('venta_emision')
            ->whereIn('venta_id', $ventaIds)
            ->groupBy('venta_id')
            ->selectRaw('venta_id, COALESCE(SUM(cantidad), 0) as kilos')
            ->pluck('kilos', 'venta_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    private function logisticaDesdeVenta(Venta $venta): float
    {
        foreach ($venta->venta_impuestos ?? [] as $imp) {
            $concepto = (string) ($imp->concepto ?? '');
            if (stripos($concepto, 'Logistica') !== false || stripos($concepto, 'Logística') !== false) {
                return (float) ($imp->importe ?? 0);
            }
            if (stripos($concepto, 'Abasto') !== false) {
                return (float) ($imp->importe ?? 0);
            }
        }

        return 0.0;
    }

    private function mediosDesdeCobranzaErp(Cobranza $cobranza, float $coef): array
    {
        $medios = FacturacionCobranzasMediosSupport::vacios();

        foreach ($cobranza->cheques ?? [] as $cheque) {
            if (strtoupper(trim((string) ($cheque->estado ?? ''))) === 'A') {
                continue;
            }
            $medios['cheques'] += (float) ($cheque->monto ?? 0) * $coef;
        }

        foreach ($cobranza->caja_movimientos ?? [] as $mov) {
            foreach ($mov->caja_movimiento_cuentacajas ?? [] as $linea) {
                $cuenta = $linea->cuentacajas;
                $bucket = FacturacionCobranzasMediosSupport::bucketDesdeCuentacaja(
                    (string) ($cuenta->codigo ?? ''),
                    (string) ($cuenta->nombre ?? ''),
                );
                $medios[$bucket] += (float) ($linea->monto ?? 0) * $coef;
            }
        }

        foreach ($cobranza->cobranza_retenciones ?? [] as $ret) {
            $medios['varios'] += (float) ($ret->monto ?? 0) * $coef;
        }

        return FacturacionCobranzasMediosSupport::cerrarConTotalRecibo(
            $medios,
            (float) ($cobranza->monto ?? 0) * $coef,
        );
    }

    private function coeficienteMoneda(
        int $monedaDestino,
        int $monedaOrigen,
        float $cotizacionDoc,
        string $fechaYmd,
        bool $soloOrigen,
    ): ?float {
        if ($soloOrigen) {
            return $monedaOrigen === $monedaDestino ? 1.0 : null;
        }
        if ($monedaOrigen === $monedaDestino || $monedaOrigen <= 1 && $monedaDestino <= 1) {
            return 1.0;
        }

        $cot = $cotizacionDoc > 0.0001
            ? $cotizacionDoc
            : CotizacionVigenteSupport::ventaValor($fechaYmd !== '' ? $fechaYmd : date('Y-m-d'), $monedaOrigen);

        if ($monedaDestino <= 1) {
            return $cot > 0.0001 ? $cot : null;
        }
        if ($monedaOrigen <= 1) {
            $cotDest = CotizacionVigenteSupport::ventaValor($fechaYmd !== '' ? $fechaYmd : date('Y-m-d'), $monedaDestino);

            return $cotDest > 0.0001 ? (1.0 / $cotDest) : null;
        }

        $cotDest = CotizacionVigenteSupport::ventaValor($fechaYmd !== '' ? $fechaYmd : date('Y-m-d'), $monedaDestino);
        if ($cot <= 0.0001 || $cotDest <= 0.0001) {
            return null;
        }

        return $cot / $cotDest;
    }

    private function signoTipoAnita(string $tipo): float
    {
        return in_array($tipo, ['NCR', 'NCA', 'NCB', 'NCC', 'NDA'], true) ? -1.0 : 1.0;
    }

    private function formatearNumeroDesdeCodigo(string $codigo, int $fallback): string
    {
        $codigo = trim($codigo);
        if ($codigo !== '') {
            if (preg_match('/^[A-Z]{2,5}\s+(.+)$/', strtoupper($codigo), $m)) {
                return trim($m[1]);
            }

            return $codigo;
        }

        return (string) $fallback;
    }

    /** Número sin tip (el tip va en columna aparte): A-00001-00722126 */
    private function formatearNumeroAnita(string $letra, int $sucursal, int $numero): string
    {
        $letra = trim(ClienteCuentacorrienteAnitaImportClaveSupport::letra($letra));
        if ($letra === '') {
            return sprintf('%05d-%08d', $sucursal, $numero);
        }

        return sprintf('%s-%05d-%08d', $letra, $sucursal, $numero);
    }

    private function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $raw = trim((string) $fecha);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }
        $ts = strtotime($raw);

        return $ts ? date('Y-m-d', $ts) : '';
    }

    /**
     * @return array<int, string>
     */
    private function mapaTransporteCodigoNombre(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = Transporte::query()
            ->get(['codigo', 'nombre'])
            ->mapWithKeys(fn ($t) => [(int) $t->codigo => (string) $t->nombre])
            ->all();

        return $cache;
    }
}
