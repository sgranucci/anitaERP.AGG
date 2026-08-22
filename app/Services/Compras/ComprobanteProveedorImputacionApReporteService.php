<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorFechaContableSupport;
use App\Support\Compras\ComprobanteProveedorImputacionApCuentasSupport;
use App\Support\Compras\ComprobanteProveedorImputacionApReporteFiltros;
use App\Support\Compras\ComprobanteProveedorImputacionApSupport;
use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Compras\ProveedorAnticipoCuentaContableSupport;
use App\Support\Compras\ProveedorCuentaContableMonedaSupport;
use App\Support\Compras\RequisicionReporteCriteriosSupport;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ComprobanteProveedorImputacionApReporteService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, totales: array<string, float|int>}
     */
    public function generar(array $filtros): array
    {
        $catalogo = ComprobanteProveedorImputacionApCuentasSupport::armar(
            array_map('intval', $filtros['empresa_ids'] ?? [])
        );
        $tolerancia = (float) ($filtros['tolerancia'] ?? ComprobanteProveedorImputacionApSupport::TOLERANCIA);

        $filas = [];
        if (! empty($filtros['incluir_comprobantes'])) {
            $filas = array_merge($filas, $this->filasComprobantes($filtros, $catalogo, $tolerancia));
        }
        if (! empty($filtros['incluir_opa'])) {
            $filas = array_merge($filas, $this->filasOpa($filtros, $catalogo, $tolerancia));
        }
        if (! empty($filtros['incluir_aplicaciones'])) {
            $filas = array_merge($filas, $this->filasAplicaciones($filtros, $catalogo, $tolerancia));
        }

        if (! empty($filtros['solo_diferencias'])) {
            $filas = array_values(array_filter(
                $filas,
                static fn (array $f) => empty($f['ok'])
            ));
        }

        usort($filas, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp((string) ($a['nombreempresa'] ?? ''), (string) ($b['nombreempresa'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return [
            'filas' => $filas,
            'totales' => $this->totalesDesdeFilas($filas),
        ];
    }

    public function paginarFilas(array $filas, int $perPage, int $page): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = max(10, min(500, $perPage));
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
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>|null  $empresaQuery
     */
    public function subtituloFiltros(array $filtros, $empresaQuery = null): string
    {
        $partes = [];

        $ids = $filtros['empresa_ids'] ?? [];
        if ($ids !== [] && $empresaQuery !== null) {
            $nombres = collect($empresaQuery)
                ->whereIn('id', $ids)
                ->pluck('nombre')
                ->filter()
                ->values()
                ->all();
            if ($nombres !== []) {
                $txt = 'Empresas: '.implode(', ', $nombres);
                if (count($ids) > 1 && ! empty($filtros['consolidar_empresas'])) {
                    $txt .= ' (consolidado)';
                }
                $partes[] = $txt;
            }
        }

        $partes[] = 'Período: '.ComprobanteProveedorImputacionApReporteFiltros::formatearPeriodoTexto($filtros);

        $tipos = [];
        if (! empty($filtros['incluir_comprobantes'])) {
            $tipos[] = 'comprobantes';
        }
        if (! empty($filtros['incluir_opa'])) {
            $tipos[] = 'OPA';
        }
        if (! empty($filtros['incluir_aplicaciones'])) {
            $tipos[] = 'aplicaciones';
        }
        if ($tipos !== []) {
            $partes[] = 'Incluye: '.implode(', ', $tipos);
        }

        $prov = OrdencompraReporteCriteriosSupport::subtituloProveedores($filtros);
        if ($prov !== null) {
            $partes[] = $prov;
        }
        if (! empty($filtros['solo_diferencias'])) {
            $partes[] = 'Solo con distorsión';
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array{mn: array<int, true>, me: array<int, true>, anticipo: array<int, true>, anticipo_por_empresa: array<int, int>}  $catalogo
     * @return list<array<string, mixed>>
     */
    private function filasComprobantes(array $filtros, array $catalogo, float $tolerancia): array
    {
        $query = Comprobante_Proveedor::query()
            ->with([
                'proveedores:id,codigo,nombre,cuentacontable_id,cuentacontableme_id,cuentacontablecompra_id',
                'tipotransaccion_compras:id,abreviatura,signo',
                'monedas:id,abreviatura,nombre',
                'empresas:id,nombre',
                'ordencompras.ordencompra_articulos',
            ])
            ->whereNotIn('estado', [
                ComprobanteProveedorEstados::ANULADO,
                ComprobanteProveedorEstados::PRECARGA,
            ]);

        $this->aplicarEmpresaYProveedor($query, $filtros, 'empresa_id', 'proveedor_id');
        $this->aplicarFechaContableComprobante($query, $filtros);

        $comprobantes = $query->orderBy('id')->get();
        $asientos = $this->cargarAsientos(
            $comprobantes->pluck('asiento_id')->filter(fn ($id) => (int) $id > 0)->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        $filas = [];
        foreach ($comprobantes as $comp) {
            $fecha = ComprobanteProveedorFechaContableSupport::fechaYmd($comp);
            $esNc = ComprobanteProveedorImputacionApSupport::esNotaCredito(
                (string) ($comp->tipotransaccion_compras?->signo ?? 'S')
            );
            $monedaId = (int) ($comp->moneda_id ?: 1);
            $cotizacion = $comp->cotizacion;
            $total = (float) ($comp->total ?? 0);
            $contexto = 'comprobante #'.$comp->id;

            $esperado = ComprobanteProveedorImputacionApSupport::esperadoHaberNetoComprobante(
                $total,
                $monedaId,
                $cotizacion,
                $fecha,
                $esNc,
                $contexto
            );

            $cuentaEsperadaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorDesdeComprobante($comp);
            $cubetaEsperada = ComprobanteProveedorImputacionApCuentasSupport::cubetaEsperadaComprobante(
                $cuentaEsperadaId,
                $catalogo
            );
            if ($cubetaEsperada === null) {
                $cubetaEsperada = ProveedorCuentaContableMonedaSupport::esMonedaExtranjera(
                    ProveedorCuentaContableMonedaSupport::monedaIdParaCuentaProveedor($comp)
                )
                    ? ComprobanteProveedorImputacionApSupport::CUBETA_ME
                    : ComprobanteProveedorImputacionApSupport::CUBETA_MN;
            }

            $asientoId = (int) ($comp->asiento_id ?? 0);
            $asiento = $asientos->get($asientoId);
            $rechazado = $asiento !== null
                && ($asiento->estado_aprobacion ?? '') === Asiento::ESTADO_APROBACION_RECHAZADO;

            $imputado = ComprobanteProveedorImputacionApSupport::imputacionTrio(
                $this->movimientosDeAsiento($asiento, $fecha),
                $catalogo,
                $contexto
            );
            $eval = ComprobanteProveedorImputacionApSupport::evaluar(
                $esperado,
                $imputado['trio'],
                $cubetaEsperada,
                $imputado['cubeta'],
                $asientoId > 0 && $asiento !== null,
                $rechazado,
                ComprobanteProveedorImputacionApSupport::TIPO_COMPROBANTE,
                $tolerancia
            );

            $abrev = (string) ($comp->tipotransaccion_compras?->abreviatura ?? 'FAC');
            $etiqueta = trim(sprintf(
                '%s %s %s-%s',
                $abrev,
                (string) ($comp->letra ?? ''),
                str_pad((string) ((int) ($comp->sucursal ?? 0)), 4, '0', STR_PAD_LEFT),
                (string) ($comp->numerocomprobante ?? '')
            ));

            $filas[] = $this->filaBase(
                ComprobanteProveedorImputacionApSupport::TIPO_COMPROBANTE,
                $comp->id,
                $fecha,
                $comp,
                $asientoId,
                $asiento,
                $etiqueta,
                (string) ($comp->estado ?? ''),
                $monedaId,
                $cotizacion,
                $total,
                $esperado,
                $imputado,
                $eval,
                $cubetaEsperada
            );
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array{mn: array<int, true>, me: array<int, true>, anticipo: array<int, true>, anticipo_por_empresa: array<int, int>}  $catalogo
     * @return list<array<string, mixed>>
     */
    private function filasOpa(array $filtros, array $catalogo, float $tolerancia): array
    {
        $query = Proveedor_Cuentacorriente::query()
            ->with([
                'proveedores:id,codigo,nombre',
                'monedas:id,abreviatura,nombre',
                'empresas:id,nombre',
                'pagoproveedores:id,asiento_id,tipocomprobante,letra,sucursal,numerotransaccion,estado,fecha,monto,moneda_id,cotizacion,empresa_id,proveedor_id',
            ])
            ->where('total', '<', 0)
            ->where('pagoproveedor_id', '>', 0)
            ->where(function ($q) {
                $q->whereNull('comprobante_proveedor_id')
                    ->orWhere('comprobante_proveedor_id', 0);
            });

        $this->aplicarEmpresaYProveedor($query, $filtros, 'empresa_id', 'proveedor_id');
        $this->aplicarRangoFecha($query, $filtros, 'fecha');

        $creditos = $query->orderBy('id')->get()->filter(
            static fn (Proveedor_Cuentacorriente $cc) => ProveedorAnticipoCuentaContableSupport::esCreditoAnticipo($cc)
        );

        $asientoIds = $creditos
            ->map(fn (Proveedor_Cuentacorriente $cc) => (int) ($cc->pagoproveedores?->asiento_id ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $asientos = $this->cargarAsientos($asientoIds);

        $filas = [];
        foreach ($creditos as $cc) {
            $pago = $cc->pagoproveedores;
            if ($pago && in_array((string) $pago->estado, ['REVERTIDA', 'BAJA'], true)) {
                continue;
            }

            $fecha = $this->fechaYmd($cc->fecha ?? $pago?->fecha);
            $monedaId = (int) ($cc->moneda_id ?: ($pago->moneda_id ?? 1));
            $cotizacion = $cc->cotizacion ?? $pago?->cotizacion;
            $total = abs((float) ($cc->total ?? 0));
            $contexto = 'OPA CC #'.$cc->id;

            $esperado = ComprobanteProveedorImputacionApSupport::esperadoHaberNetoOpa(
                $total,
                $monedaId,
                $cotizacion,
                $fecha,
                $contexto
            );
            $cubetaEsperada = ComprobanteProveedorImputacionApCuentasSupport::cubetaEsperadaOpa(
                (int) $cc->empresa_id,
                $catalogo
            );

            $asientoId = (int) ($pago?->asiento_id ?? 0);
            $asiento = $asientos->get($asientoId);
            $rechazado = $asiento !== null
                && ($asiento->estado_aprobacion ?? '') === Asiento::ESTADO_APROBACION_RECHAZADO;

            $imputado = ComprobanteProveedorImputacionApSupport::imputacionTrio(
                $this->movimientosDeAsiento($asiento, $fecha),
                $catalogo,
                $contexto
            );
            $eval = ComprobanteProveedorImputacionApSupport::evaluar(
                $esperado,
                $imputado['trio'],
                $cubetaEsperada,
                $imputado['cubeta'],
                $asientoId > 0 && $asiento !== null,
                $rechazado,
                ComprobanteProveedorImputacionApSupport::TIPO_OPA,
                $tolerancia
            );

            $etiqueta = $pago
                ? $pago->etiquetaComprobante()
                : 'OPA CC #'.$cc->id;

            $fila = $this->filaBase(
                ComprobanteProveedorImputacionApSupport::TIPO_OPA,
                (int) $cc->id,
                $fecha,
                $cc,
                $asientoId,
                $asiento,
                $etiqueta,
                (string) ($pago?->estado ?? ''),
                $monedaId,
                $cotizacion,
                $total,
                $esperado,
                $imputado,
                $eval,
                $cubetaEsperada
            );
            $fila['pagoproveedor_id'] = (int) ($cc->pagoproveedor_id ?? 0);
            $filas[] = $fila;
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array{mn: array<int, true>, me: array<int, true>, anticipo: array<int, true>, anticipo_por_empresa: array<int, int>}  $catalogo
     * @return list<array<string, mixed>>
     */
    private function filasAplicaciones(array $filtros, array $catalogo, float $tolerancia): array
    {
        $query = Proveedor_Cuentacorriente_Aplicacion::query()
            ->with([
                'empresas:id,nombre',
                'monedas:id,abreviatura,nombre',
                'proveedor_cuentacorrientes.proveedores:id,codigo,nombre',
                'proveedor_cuentacorrientes.pagoproveedores:id,tipocomprobante,letra,sucursal,numerotransaccion',
                'comprobante_proveedor_aplicados:id,letra,sucursal,numerocomprobante,tipotransaccion_compra_id',
                'comprobante_proveedor_aplicados.tipotransaccion_compras:id,abreviatura',
            ])
            ->where('asiento_id', '>', 0);

        $this->aplicarEmpresaYProveedorViaCc($query, $filtros);
        $this->aplicarRangoFecha($query, $filtros, 'fecha');

        $aplicaciones = $query->orderBy('id')->get();
        $asientos = $this->cargarAsientos(
            $aplicaciones->pluck('asiento_id')->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values()->all()
        );

        $filas = [];
        foreach ($aplicaciones as $apl) {
            $fecha = $this->fechaYmd($apl->fecha);
            $monedaId = (int) ($apl->moneda_id ?: 1);
            $cotizacion = $apl->cotizacion;
            $total = abs((float) ($apl->total ?? 0));
            $contexto = 'aplicación CC #'.$apl->id;

            $esperado = ComprobanteProveedorImputacionApSupport::esperadoHaberNetoAplicacion();
            $asientoId = (int) ($apl->asiento_id ?? 0);
            $asiento = $asientos->get($asientoId);
            $rechazado = $asiento !== null
                && ($asiento->estado_aprobacion ?? '') === Asiento::ESTADO_APROBACION_RECHAZADO;

            $imputado = ComprobanteProveedorImputacionApSupport::imputacionTrio(
                $this->movimientosDeAsiento($asiento, $fecha),
                $catalogo,
                $contexto
            );
            $eval = ComprobanteProveedorImputacionApSupport::evaluar(
                $esperado,
                $imputado['trio'],
                null,
                $imputado['cubeta'],
                $asientoId > 0 && $asiento !== null,
                $rechazado,
                ComprobanteProveedorImputacionApSupport::TIPO_APLICACION,
                $tolerancia
            );

            $cc = $apl->proveedor_cuentacorrientes;
            $comp = $apl->comprobante_proveedor_aplicados;
            $abrevComp = (string) ($comp?->tipotransaccion_compras?->abreviatura ?? '');
            $etiquetaComp = $comp
                ? trim($abrevComp.' '.$comp->letra.' '.str_pad((string) ((int) $comp->sucursal), 4, '0', STR_PAD_LEFT).'-'.$comp->numerocomprobante)
                : '';
            $etiquetaPago = $cc?->pagoproveedores?->etiquetaComprobante() ?? '';
            $etiqueta = trim('Aplicación '.($etiquetaPago !== '' ? $etiquetaPago.' → ' : '').$etiquetaComp);

            $fila = $this->filaBase(
                ComprobanteProveedorImputacionApSupport::TIPO_APLICACION,
                (int) $apl->id,
                $fecha,
                $cc ?? $apl,
                $asientoId,
                $asiento,
                $etiqueta !== 'Aplicación' ? $etiqueta : 'Aplicación #'.$apl->id,
                '',
                $monedaId,
                $cotizacion,
                $total,
                $esperado,
                $imputado,
                $eval,
                null
            );
            $fila['aplicacion_id'] = (int) $apl->id;
            $fila['comprobante_id'] = (int) ($apl->comprobante_proveedor_aplicado_id ?? 0);
            $fila['pagoproveedor_id'] = (int) ($apl->pagoproveedor_id ?? $cc?->pagoproveedor_id ?? 0);
            $filas[] = $fila;
        }

        return $filas;
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Asiento>
     */
    private function cargarAsientos(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return collect();
        }

        return Asiento::query()
            ->with(['asiento_movimientos.cuentacontables', 'asiento_movimientos.monedas'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * @return list<array{cuentacontable_id:int, monto:float, moneda_id:int, cotizacion:mixed, fecha:?string}>
     */
    private function movimientosDeAsiento(?Asiento $asiento, string $fechaDocumento): array
    {
        if ($asiento === null) {
            return [];
        }

        $fechaAsiento = $this->fechaYmd($asiento->fecha) ?: $fechaDocumento;
        $out = [];
        foreach ($asiento->asiento_movimientos ?? [] as $mov) {
            /** @var Asiento_Movimiento $mov */
            $out[] = [
                'cuentacontable_id' => (int) ($mov->cuentacontable_id ?? 0),
                'monto' => (float) ($mov->monto ?? 0),
                'moneda_id' => (int) ($mov->moneda_id ?: 1),
                'cotizacion' => $mov->cotizacion,
                'fecha' => $fechaAsiento,
            ];
        }

        return $out;
    }

    /**
     * @param  array{ap_mn: float, ap_me: float, anticipo: float, trio: float, cubeta: string}  $imputado
     * @param  array{diferencia: float, ok: bool, alertas: list<string>}  $eval
     * @return array<string, mixed>
     */
    private function filaBase(
        string $tipo,
        int $id,
        string $fecha,
        object $origen,
        int $asientoId,
        ?Asiento $asiento,
        string $etiqueta,
        string $estado,
        int $monedaId,
        mixed $cotizacion,
        float $totalOrigen,
        float $esperado,
        array $imputado,
        array $eval,
        ?string $cubetaEsperada,
    ): array {
        $empresa = $origen->empresas ?? null;
        $proveedor = $origen->proveedores ?? null;
        $moneda = $origen->monedas ?? null;
        $totalArs = ComprobanteProveedorImputacionApSupport::aPesosTolerante(
            abs($totalOrigen),
            $monedaId,
            $cotizacion,
            $fecha,
            'fila '.$tipo.' #'.$id
        );

        return [
            'id' => $id,
            'tipo' => $tipo,
            'tipo_etiqueta' => ComprobanteProveedorImputacionApSupport::etiquetaTipo($tipo),
            'fecha' => $fecha,
            'empresa_id' => (int) ($origen->empresa_id ?? 0),
            'nombreempresa' => (string) ($empresa?->nombre ?? ''),
            'proveedor_id' => (int) ($origen->proveedor_id ?? $proveedor?->id ?? 0),
            'codigo_proveedor' => (string) ($proveedor?->codigo ?? ''),
            'nombre_proveedor' => (string) ($proveedor?->nombre ?? ''),
            'comprobante_id' => $tipo === ComprobanteProveedorImputacionApSupport::TIPO_COMPROBANTE ? $id : 0,
            'pagoproveedor_id' => 0,
            'aplicacion_id' => 0,
            'asiento_id' => $asientoId,
            'numeroasiento' => (string) ($asiento?->numeroasiento ?? ''),
            'comprobante_etiqueta' => $etiqueta,
            'estado' => $estado,
            'moneda_id' => $monedaId,
            'moneda' => (string) ($moneda?->abreviatura ?? $moneda?->nombre ?? ''),
            'cotizacion' => (float) ($cotizacion ?: 0),
            'total_origen' => round($totalOrigen, 2),
            'total_ars' => $totalArs,
            'esperado_ars' => $esperado,
            'ap_mn_ars' => $imputado['ap_mn'],
            'ap_me_ars' => $imputado['ap_me'],
            'anticipo_ars' => $imputado['anticipo'],
            'imputado_ars' => $imputado['trio'],
            'diferencia' => $eval['diferencia'],
            'ok' => $eval['ok'],
            'alertas' => $eval['alertas'],
            'alertas_texto' => implode(' · ', $eval['alertas']),
            'cubeta_esperada' => $cubetaEsperada,
            'cubeta_esperada_etiqueta' => ComprobanteProveedorImputacionApSupport::etiquetaCubeta($cubetaEsperada),
            'cubeta_imputada' => $imputado['cubeta'],
            'cubeta_imputada_etiqueta' => ComprobanteProveedorImputacionApSupport::etiquetaCubeta($imputado['cubeta']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float|int>
     */
    private function totalesDesdeFilas(array $filas): array
    {
        $totales = [
            'total_filas' => count($filas),
            'con_distorsion' => 0,
            'sin_asiento' => 0,
            'comprobantes' => 0,
            'opa' => 0,
            'aplicaciones' => 0,
            'esperado_ars' => 0.0,
            'imputado_ars' => 0.0,
            'ap_mn_ars' => 0.0,
            'ap_me_ars' => 0.0,
            'anticipo_ars' => 0.0,
            'diferencia_ars' => 0.0,
        ];

        foreach ($filas as $f) {
            if (empty($f['ok'])) {
                $totales['con_distorsion']++;
            }
            if (in_array('Sin asiento', $f['alertas'] ?? [], true)) {
                $totales['sin_asiento']++;
            }
            if (($f['tipo'] ?? '') === ComprobanteProveedorImputacionApSupport::TIPO_COMPROBANTE) {
                $totales['comprobantes']++;
            } elseif (($f['tipo'] ?? '') === ComprobanteProveedorImputacionApSupport::TIPO_OPA) {
                $totales['opa']++;
            } else {
                $totales['aplicaciones']++;
            }
            $totales['esperado_ars'] += (float) ($f['esperado_ars'] ?? 0);
            $totales['imputado_ars'] += (float) ($f['imputado_ars'] ?? 0);
            $totales['ap_mn_ars'] += (float) ($f['ap_mn_ars'] ?? 0);
            $totales['ap_me_ars'] += (float) ($f['ap_me_ars'] ?? 0);
            $totales['anticipo_ars'] += (float) ($f['anticipo_ars'] ?? 0);
            $totales['diferencia_ars'] += (float) ($f['diferencia'] ?? 0);
        }

        foreach (['esperado_ars', 'imputado_ars', 'ap_mn_ars', 'ap_me_ars', 'anticipo_ars', 'diferencia_ars'] as $k) {
            $totales[$k] = round((float) $totales[$k], 2);
        }

        return $totales;
    }

    private function aplicarEmpresaYProveedor($query, array $filtros, string $colEmpresa, string $colProveedor): void
    {
        $empresaIds = array_map('intval', $filtros['empresa_ids'] ?? []);
        if ($empresaIds !== []) {
            $query->whereIn($colEmpresa, $empresaIds);
        }

        $codigos = RequisicionReporteCriteriosSupport::parseListaCodigos((string) ($filtros['proveedores'] ?? ''));
        if ($codigos !== []) {
            $query->whereHas('proveedores', function ($q) use ($codigos) {
                $q->whereIn('codigo', $codigos);
            });
        }
    }

    private function aplicarEmpresaYProveedorViaCc($query, array $filtros): void
    {
        $empresaIds = array_map('intval', $filtros['empresa_ids'] ?? []);
        if ($empresaIds !== []) {
            $query->whereIn('empresa_id', $empresaIds);
        }

        $codigos = RequisicionReporteCriteriosSupport::parseListaCodigos((string) ($filtros['proveedores'] ?? ''));
        if ($codigos !== []) {
            $query->whereHas('proveedor_cuentacorrientes.proveedores', function ($q) use ($codigos) {
                $q->whereIn('codigo', $codigos);
            });
        }
    }

    private function aplicarRangoFecha($query, array $filtros, string $columna): void
    {
        $desde = $filtros['fecha_desde'] ?? null;
        $hasta = $filtros['fecha_hasta'] ?? null;
        if ($desde) {
            $query->whereDate($columna, '>=', $desde);
        }
        if ($hasta) {
            $query->whereDate($columna, '<=', $hasta);
        }
    }

    private function aplicarFechaContableComprobante($query, array $filtros): void
    {
        $desde = $filtros['fecha_desde'] ?? null;
        $hasta = $filtros['fecha_hasta'] ?? null;
        if ($desde) {
            $query->where(function ($q) use ($desde) {
                $q->where(function ($q2) use ($desde) {
                    $q2->whereNotNull('fechaiva')->whereDate('fechaiva', '>=', $desde);
                })->orWhere(function ($q2) use ($desde) {
                    $q2->whereNull('fechaiva')->whereDate('fechacomprobante', '>=', $desde);
                });
            });
        }
        if ($hasta) {
            $query->where(function ($q) use ($hasta) {
                $q->where(function ($q2) use ($hasta) {
                    $q2->whereNotNull('fechaiva')->whereDate('fechaiva', '<=', $hasta);
                })->orWhere(function ($q2) use ($hasta) {
                    $q2->whereNull('fechaiva')->whereDate('fechacomprobante', '<=', $hasta);
                });
            });
        }
    }

    private function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $texto = trim((string) $fecha);

        return $texto !== '' ? substr($texto, 0, 10) : '';
    }
}
