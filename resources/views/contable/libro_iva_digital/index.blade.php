@extends("theme.$theme.layout")
@section('titulo')
    Libro IVA Digital
@endsection

@section('contenido')
@php
    use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalArchivosSupport;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Presentación Libro IVA Digital — cierre de mes</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Genera los archivos ASCII/CSV exigidos por ARCA (RG 4597) para importar en el Portal IVA:
                    ventas y compras (cabecera, alícuotas y anulados), importaciones de bienes/servicios,
                    e importes agregados por <strong>actividad ARCA</strong> para IVA Simple / DJ
                    (<code>;</code> decimal <code>,</code>). Codificación ANSI ISO-8859-1 / Windows-1252.
                </p>

                <form method="get" action="{{ route('libro_iva_digital') }}" class="mb-4" id="form-libro-iva-digital">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => true,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-3',
                    ])

                    @php
                        $mesPeriodo = (int) old('mes', $filtros['mes'] ?? (int) date('n', strtotime('first day of last month')));
                        $anioPeriodo = (int) old('anio', $filtros['anio'] ?? (int) date('Y', strtotime('first day of last month')));
                        $mesesPeriodo = [
                            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                        ];
                    @endphp
                    <div class="form-group row">
                        <label class="col-lg-2 control-label requerido" for="mes">Per&iacute;odo</label>
                        <div class="col-lg-5">
                            <div class="row">
                                <div class="col-md-6 mb-2 mb-md-0">
                                    <select name="mes" id="mes" class="form-control" required title="Mes" aria-label="Mes del per&iacute;odo">
                                        @foreach ($mesesPeriodo as $num => $nombre)
                                            <option value="{{ $num }}" @selected($mesPeriodo === $num)>
                                                {{ str_pad((string) $num, 2, '0', STR_PAD_LEFT) }} — {{ $nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="number" name="anio" id="anio" class="form-control"
                                           min="2000" max="2100" step="1" required
                                           title="A&ntilde;o" aria-label="A&ntilde;o del per&iacute;odo"
                                           value="{{ $anioPeriodo }}"
                                           placeholder="AAAA">
                                </div>
                            </div>
                            <small class="form-text text-muted">Mes y a&ntilde;o a presentar (ej. Jun/2026).</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <span class="col-lg-2 control-label pt-2">Fecha de ventas</span>
                        <div class="col-lg-8">
                            <input type="hidden" name="por_fecha_jornada" value="0">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="por_fecha_jornada"
                                       id="por_fecha_jornada" value="1"
                                       @checked(! empty($filtros['por_fecha_jornada']))>
                                <label class="form-check-label" for="por_fecha_jornada">
                                    Usar fecha de jornada (optativo)
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                Alinea el per&iacute;odo con IVA Ventas y con Anita (<code>ven_fecha_vto</code>):
                                gastronom&iacute;a, estacionamiento y vending. Si el comprobante no tiene jornada,
                                se usa la fecha del movimiento. Sin marcar, se usa la fecha del comprobante/CAE.
                            </small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <span class="col-lg-2 control-label pt-2">Compras</span>
                        <div class="col-lg-8">
                            <input type="hidden" name="prorrateo_cf_global" value="0">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="prorrateo_cf_global"
                                       id="prorrateo_cf_global" value="1"
                                       @checked(! empty($filtros['prorrateo_cf_global']))>
                                <label class="form-check-label" for="prorrateo_cf_global">
                                    Prorrateo CF por asignaci&oacute;n global (CF computable = 0)
                                </label>
                            </div>
                            <small class="form-text text-muted mb-2 d-block">
                                Usar si en ARCA eligi&oacute; prorrateo global: el archivo pone cr&eacute;dito fiscal
                                computable en cero y el CF se carga en la solapa <em>CF Computable Global</em>.
                            </small>

                            <input type="hidden" name="completar_compras_anita" value="0">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="completar_compras_anita"
                                       id="completar_compras_anita" value="1"
                                       @checked(! empty($filtros['completar_compras_anita']))>
                                <label class="form-check-label" for="completar_compras_anita">
                                    Priorizar compras Anita (libro / Portal)
                                </label>
                            </div>
                            <small class="form-text text-muted mb-2 d-block">
                                Toma el libro de Anita (<code>compra</code> + <code>concmov</code> por
                                <code>com_fecha_iva</code>) y s&oacute;lo agrega comprobantes del ERP que no est&eacute;n
                                ya ah&iacute; (misma clave proveedor/tipo/letra/PV/nro o <code>anita_nro_interno</code>).
                                As&iacute; no se pierde gravado ni NC cuando el ERP tiene el comprobante sin conceptos G/I.
                            </small>

                            <input type="hidden" name="completar_fsl_anita" value="0">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="completar_fsl_anita"
                                       id="completar_fsl_anita" value="1"
                                       @checked(! empty($filtros['completar_fsl_anita']))>
                                <label class="form-check-label" for="completar_fsl_anita">
                                    Completar FSL m&aacute;quinas desde Anita (sin solapar ERP)
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                Mientras el cierre de m&aacute;quinas siga en Informix, lee <code>venta</code> tipo FSL
                                del per&iacute;odo y las suma al Libro IVA / IVA Simple (actividad apuestas 920009).
                                No duplica si ya hay FSL en ERP con la misma sucursal+n&uacute;mero.
                            </small>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar-libro-iva-digital">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            @if (can('exportar-libro-iva-digital', false) && !empty($resultado))
                                <a href="{{ route('exportar_libro_iva_digital', $filtros) }}"
                                   class="btn btn-success btn-sm ml-1" id="btn-exportar-libro-iva-digital">
                                    <i class="fa fa-download"></i> Descargar ZIP completo
                                </a>
                                <a href="{{ route('exportar_iva_simple_libro_iva_digital', $filtros) }}"
                                   class="btn btn-outline-success btn-sm ml-1" id="btn-exportar-iva-simple">
                                    <i class="fa fa-download"></i> Solo IVA Simple (CSV)
                                </a>
                            @endif
                        </div>
                    </div>
                </form>

                    @if ($consultado && $resultado)
                    <p class="text-muted small mb-2">
                        Ventas filtradas por
                        @if (! empty($resultado['opciones']['por_fecha_jornada']) || ! empty($resultado['ventas']['resumen']['por_fecha_jornada']))
                            fecha de jornada
                        @else
                            fecha del comprobante
                        @endif.
                        Compras:
                        @if (! empty($resultado['opciones']['prorrateo_cf_global']) || ! empty($resultado['compras']['resumen']['prorrateo_cf_global']))
                            prorrateo CF global (computable = 0)
                        @else
                            CF computable = IVA del comprobante
                        @endif
                        @if (! empty($resultado['opciones']['completar_compras_anita']) || ! empty($resultado['compras']['resumen']['completar_compras_anita']))
                            ; prioriza Anita (libro) y agrega ERP que no est&eacute;n
                        @endif
                        @if (! empty($resultado['opciones']['completar_fsl_anita']) || ! empty($resultado['ventas']['resumen']['completar_fsl_anita']))
                            ; FSL m&aacute;quinas completadas desde Anita
                        @endif.
                    </p>
                    @if (!empty($resultado['validaciones']))
                        <div class="alert alert-warning">
                            <strong>Validaciones:</strong>
                            <ul class="mb-0 pl-3">
                                @foreach ($resultado['validaciones'] as $aviso)
                                    <li>{{ $aviso }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm" id="tabla-resumen-lid">
                            <thead style="background-color:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Archivo</th>
                                    <th class="text-right">Registros</th>
                                    <th class="text-right">Importe / detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>{{ LibroIvaDigitalArchivosSupport::VENTAS_CBTE }}</code></td>
                                    <td class="text-right">{{ number_format($resultado['ventas']['resumen']['comprobantes'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-right">${{ number_format($resultado['ventas']['resumen']['importe_total'] ?? 0, 2, ',', '.') }}</td>
                                </tr>
                                @if (($resultado['ventas']['resumen']['ventas_emitidas'] ?? 0) > ($resultado['ventas']['resumen']['comprobantes'] ?? 0))
                                <tr>
                                    <td colspan="3" class="text-muted small py-0">
                                        {{ number_format($resultado['ventas']['resumen']['ventas_emitidas'] ?? 0, 0, ',', '.') }} facturas emitidas;
                                        Facturas B CF an&oacute;nimas agrupadas por d&iacute;a, PV y tipo (desde/hasta).
                                        @if (($resultado['ventas']['resumen']['ventas_b_individuales'] ?? 0) > 0)
                                            {{ number_format($resultado['ventas']['resumen']['ventas_b_individuales'], 0, ',', '.') }} Facturas B informadas individualmente
                                            (&ge; ${{ number_format(\App\Support\Configuracion\ParametroSistemaSupport::topeConsumidorFinal(), 0, ',', '.') }} o comprador identificado).
                                        @endif
                                    </td>
                                </tr>
                                @endif
                                @if (($resultado['ventas']['resumen']['ventas_rmv'] ?? 0) > 0)
                                <tr>
                                    <td colspan="3" class="text-muted small py-0">
                                        {{ number_format($resultado['ventas']['resumen']['ventas_rmv'], 0, ',', '.') }} rendiciones vending RMV
                                        (Factura B tipo 006, documento 89, sucursal &ge; 1000; mismo criterio que Anita p-rg3685).
                                    </td>
                                </tr>
                                @endif
                                @if (($resultado['ventas']['resumen']['ventas_fbi_fsl'] ?? 0) > 0)
                                <tr class="table-warning">
                                    <td colspan="3">
                                        <strong>{{ number_format($resultado['ventas']['resumen']['ventas_fbi_fsl'], 0, ',', '.') }}</strong>
                                        comprobantes bingo/m&aacute;quinas (FBI/FSL) incluidos como
                                        <strong>Factura B tipo 006</strong> (exentas, sin CAE; PV 14/26/39 seg&uacute;n empresa).
                                        @if (($resultado['ventas']['resumen']['ventas_fsl_anita'] ?? 0) > 0)
                                            De ellas,
                                            <strong>{{ number_format($resultado['ventas']['resumen']['ventas_fsl_anita'], 0, ',', '.') }}</strong>
                                            FSL vinieron de Anita.
                                        @endif
                                        En el TXT no figura la abreviatura FBI/FSL: buscar tipo <code>006</code> y el PV de cierre.
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td><code>{{ LibroIvaDigitalArchivosSupport::VENTAS_ALICUOTAS }}</code></td>
                                    <td class="text-right">{{ number_format($resultado['ventas']['resumen']['alicuotas'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">
                                        Factura B CF an&oacute;nima: venta global diaria (doc. 99) con rango desde/hasta.
                                        B &ge; umbral RG 5700 o con DNI/CUIT: comprobante individual.
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>{{ LibroIvaDigitalArchivosSupport::VENTAS_ANULADOS }}</code></td>
                                    <td class="text-right">{{ number_format($resultado['anulados']['resumen']['ventas'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Ventas con CAE eliminadas en el período</td>
                                </tr>
                                <tr>
                                    <td><code>{{ LibroIvaDigitalArchivosSupport::COMPRAS_CBTE }}</code></td>
                                    <td class="text-right">{{ number_format($resultado['compras']['resumen']['comprobantes'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-right">${{ number_format($resultado['compras']['resumen']['importe_total'] ?? 0, 2, ',', '.') }}</td>
                                </tr>
                                @if (($resultado['compras']['resumen']['comprobantes_anita'] ?? 0) > 0)
                                <tr>
                                    <td colspan="3" class="text-muted small py-0">
                                        {{ number_format($resultado['compras']['resumen']['comprobantes_anita'], 0, ',', '.') }}
                                        comprobantes solo en Anita (no estaban en ERP).
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td><code>{{ LibroIvaDigitalArchivosSupport::COMPRAS_ALICUOTAS }}</code></td>
                                    <td class="text-right">{{ number_format($resultado['compras']['resumen']['alicuotas'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">
                                        Neto Portal ${{ number_format($resultado['compras']['resumen']['neto_portal'] ?? 0, 2, ',', '.') }}
                                        (facturas ${{ number_format($resultado['compras']['resumen']['neto_facturas'] ?? 0, 2, ',', '.') }}
                                        − NC ${{ number_format($resultado['compras']['resumen']['neto_nc'] ?? 0, 2, ',', '.') }});
                                        IVA Portal ${{ number_format($resultado['compras']['resumen']['iva_portal'] ?? 0, 2, ',', '.') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>{{ LibroIvaDigitalArchivosSupport::COMPRAS_ANULADOS }}</code></td>
                                    <td class="text-right">{{ number_format($resultado['anulados']['resumen']['compras'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Compras anuladas en el período</td>
                                </tr>
                                <tr>
                                    <td><code>{{ LibroIvaDigitalArchivosSupport::IMPORTACION_BIENES_ALICUOTA }}</code></td>
                                    <td class="text-right">{{ number_format($resultado['importaciones']['resumen']['bienes'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Tabla <code>libro_iva_importacion_bien</code></td>
                                </tr>
                                <tr>
                                    <td><code>{{ LibroIvaDigitalArchivosSupport::IMPORTACION_SERVICIOS_CREDITO_FISCAL }}</code></td>
                                    <td class="text-right">{{ number_format($resultado['importaciones']['resumen']['servicios'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Tabla <code>libro_iva_importacion_servicio</code></td>
                                </tr>
                                <tr>
                                    <td><code>{{ LibroIvaDigitalArchivosSupport::IVA_SIMPLE_DEBITO_FISCAL }}</code></td>
                                    <td class="text-right">{{ number_format($resultado['iva_simple']['resumen']['renglones_debito'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">
                                        {{ number_format($resultado['iva_simple']['resumen']['actividades'] ?? 0, 0, ',', '.') }}
                                        actividad(es) ARCA
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>{{ LibroIvaDigitalArchivosSupport::IVA_SIMPLE_CREDITO_FISCAL }}</code></td>
                                    <td class="text-right">{{ number_format($resultado['iva_simple']['resumen']['renglones_credito'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Compras ERP + Anita (concepto × alícuota, gravado e IVA pareados)</td>
                                </tr>
                                <tr>
                                    <td><code>{{ LibroIvaDigitalArchivosSupport::IVA_SIMPLE_RESTITUCION_DEBITO_FISCAL }}</code></td>
                                    <td class="text-right">{{ number_format($resultado['iva_simple']['resumen']['renglones_restitucion_debito'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Notas de crédito ventas</td>
                                </tr>
                                <tr>
                                    <td><code>{{ LibroIvaDigitalArchivosSupport::IVA_SIMPLE_RESTITUCION_CREDITO_FISCAL }}</code></td>
                                    <td class="text-right">{{ number_format($resultado['iva_simple']['resumen']['renglones_restitucion_credito'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Notas de crédito compras</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @if (!empty($resultado['iva_simple']['resumen_por_actividad']))
                        <h5 class="mb-2">IVA Simple — débito fiscal por actividad ARCA</h5>
                        <p class="text-muted small">
                            Misma fuente que el Libro IVA Digital de ventas. El gravado/IVA sale de las alícuotas
                            del <code>VENTAS_CBTE</code>; el exento/no gravado es el campo
                            <code>operaciones_exentas</code> (tipo operación 3, RG 5705).
                            <strong>Neto gravado</strong> e <strong>IVA débito</strong> son solo facturas;
                            las NC van en <strong>Neto NC</strong> e <strong>IVA restitución</strong>.
                            Para cruzar con Portal: neto gravado − neto NC, IVA débito − IVA restitución.
                            Actividad <code>920009</code> (apuestas): Facturas B tipo <code>006</code> del PV de cierre.
                        </p>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-sm" id="tabla-iva-simple-actividad">
                                <thead style="background-color:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Cód. actividad</th>
                                        <th>Actividad ARCA</th>
                                        <th class="text-right">Rengl. débito</th>
                                        <th class="text-right">Rengl. restitución</th>
                                        <th class="text-right">Neto gravado</th>
                                        <th class="text-right">Neto NC</th>
                                        <th class="text-right">IVA débito</th>
                                        <th class="text-right">Exento / no grav.</th>
                                        <th class="text-right">IVA restitución</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($resultado['iva_simple']['resumen_por_actividad'] as $filaActividad)
                                        <tr @if (($filaActividad['actividad_codigo'] ?? '') === '000000') class="table-warning" @elseif (($filaActividad['actividad_codigo'] ?? '') === '920009') class="table-info" @endif>
                                            <td><code>{{ $filaActividad['actividad_codigo'] ?? '' }}</code></td>
                                            <td>{{ $filaActividad['actividad_nombre'] ?? '—' }}</td>
                                            <td class="text-right">{{ number_format($filaActividad['renglones_debito'] ?? 0, 0, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format($filaActividad['renglones_restitucion'] ?? 0, 0, ',', '.') }}</td>
                                            <td class="text-right">${{ number_format($filaActividad['neto_gravado'] ?? 0, 2, ',', '.') }}</td>
                                            <td class="text-right">${{ number_format($filaActividad['neto_restitucion'] ?? 0, 2, ',', '.') }}</td>
                                            <td class="text-right">${{ number_format($filaActividad['iva_debito'] ?? 0, 2, ',', '.') }}</td>
                                            <td class="text-right">${{ number_format($filaActividad['exento'] ?? 0, 2, ',', '.') }}</td>
                                            <td class="text-right">${{ number_format($filaActividad['iva_restitucion'] ?? 0, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="font-weight-bold">
                                        <td colspan="4">Total período</td>
                                        <td class="text-right">
                                            ${{ number_format(collect($resultado['iva_simple']['resumen_por_actividad'])->sum('neto_gravado'), 2, ',', '.') }}
                                        </td>
                                        <td class="text-right">
                                            ${{ number_format(collect($resultado['iva_simple']['resumen_por_actividad'])->sum('neto_restitucion'), 2, ',', '.') }}
                                        </td>
                                        <td class="text-right">
                                            ${{ number_format($resultado['iva_simple']['resumen']['total_iva_debito'] ?? 0, 2, ',', '.') }}
                                        </td>
                                        <td class="text-right">
                                            ${{ number_format(collect($resultado['iva_simple']['resumen_por_actividad'])->sum('exento'), 2, ',', '.') }}
                                        </td>
                                        <td class="text-right">
                                            ${{ number_format(collect($resultado['iva_simple']['resumen_por_actividad'])->sum('iva_restitucion'), 2, ',', '.') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif

                    @if (!empty($resultado['iva_simple']['resumen_por_concepto']))
                        <h5 class="mb-2">IVA Simple — crédito fiscal por concepto (compras)</h5>
                        <p class="text-muted small">
                            Misma fuente que el Libro IVA Digital de compras (ERP + Anita sin solapar).
                            El CSV de crédito solo admite gravado + IVA. Las NC van en <strong>Neto NC</strong>
                            e <strong>IVA restitución</strong> (Portal = gravado − NC).
                            Las facturas C (monotributo) no se escriben en exento/no gravado del TXT:
                            el tipo 011-016 las identifica. Portal suele sumarlas en «No gravado + exento»;
                            acá van aparte para cruzar con el Libro IVA Compras de Anita.
                        </p>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-sm" id="tabla-iva-simple-concepto">
                                <thead style="background-color:#82E0AA;color:#17202A;">
                                    <tr>
                                        <th>Concepto</th>
                                        <th class="text-right">Rengl. crédito</th>
                                        <th class="text-right">Rengl. restitución</th>
                                        <th class="text-right">Neto gravado</th>
                                        <th class="text-right">Neto NC</th>
                                        <th class="text-right">IVA crédito</th>
                                        <th class="text-right">IVA computable</th>
                                        <th class="text-right">IVA restitución</th>
                                        <th class="text-right">Exento / no grav.</th>
                                        <th class="text-right">Monotributo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($resultado['iva_simple']['resumen_por_concepto'] as $filaConcepto)
                                        <tr>
                                            <td>
                                                <code>{{ $filaConcepto['concepto'] ?? '' }}</code>
                                                {{ $filaConcepto['concepto_nombre'] ?? '' }}
                                            </td>
                                            <td class="text-right">{{ number_format($filaConcepto['renglones_credito'] ?? 0, 0, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format($filaConcepto['renglones_restitucion'] ?? 0, 0, ',', '.') }}</td>
                                            <td class="text-right">${{ number_format($filaConcepto['neto_gravado'] ?? 0, 2, ',', '.') }}</td>
                                            <td class="text-right">${{ number_format($filaConcepto['neto_restitucion'] ?? 0, 2, ',', '.') }}</td>
                                            <td class="text-right">${{ number_format($filaConcepto['iva_credito'] ?? 0, 2, ',', '.') }}</td>
                                            <td class="text-right">${{ number_format($filaConcepto['iva_computable'] ?? 0, 2, ',', '.') }}</td>
                                            <td class="text-right">${{ number_format($filaConcepto['iva_restitucion'] ?? 0, 2, ',', '.') }}</td>
                                            <td class="text-right">—</td>
                                            <td class="text-right">—</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="font-weight-bold">
                                        <td colspan="3">Total período</td>
                                        <td class="text-right">
                                            ${{ number_format(collect($resultado['iva_simple']['resumen_por_concepto'])->sum('neto_gravado'), 2, ',', '.') }}
                                        </td>
                                        <td class="text-right">
                                            ${{ number_format(collect($resultado['iva_simple']['resumen_por_concepto'])->sum('neto_restitucion'), 2, ',', '.') }}
                                        </td>
                                        <td class="text-right">
                                            ${{ number_format($resultado['iva_simple']['resumen']['total_iva_credito'] ?? 0, 2, ',', '.') }}
                                        </td>
                                        <td class="text-right">
                                            ${{ number_format(collect($resultado['iva_simple']['resumen_por_concepto'])->sum('iva_computable'), 2, ',', '.') }}
                                        </td>
                                        <td class="text-right">
                                            ${{ number_format(collect($resultado['iva_simple']['resumen_por_concepto'])->sum('iva_restitucion'), 2, ',', '.') }}
                                        </td>
                                        <td class="text-right">
                                            ${{ number_format(
                                                ($resultado['iva_simple']['resumen']['total_exento_compras'] ?? 0)
                                                + ($resultado['iva_simple']['resumen']['total_no_integra_compras'] ?? 0),
                                                2, ',', '.'
                                            ) }}
                                        </td>
                                        <td class="text-right">
                                            ${{ number_format($resultado['iva_simple']['resumen']['total_monotributo_compras'] ?? 0, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                    <tr class="table-info">
                                        <td colspan="3">A cruzar con Portal (facturas − NC)</td>
                                        <td class="text-right">
                                            ${{ number_format($resultado['iva_simple']['resumen']['total_neto_portal'] ?? 0, 2, ',', '.') }}
                                        </td>
                                        <td class="text-right">—</td>
                                        <td class="text-right">
                                            ${{ number_format($resultado['iva_simple']['resumen']['total_iva_portal'] ?? 0, 2, ',', '.') }}
                                        </td>
                                        <td colspan="4" class="text-muted small">
                                            Incluye importaciones del libro. Portal informa el neto, no el bruto de facturas.
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif

                    <p class="text-muted small mb-0">
                        Período {{ $resultado['periodo']['etiqueta'] ?? '' }}.
                        Importaciones y ajustes manuales DJ se cargan en las tablas auxiliares
                        (<code>libro_iva_importacion_*</code>, <code>libro_iva_ajuste_dj</code>).
                        TurIVA queda fuera de alcance para AGG.
                    </p>
                @elseif ($consultado)
                    <p class="text-muted">No se generaron datos para los filtros indicados.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'libro-iva-digital-procesando-overlay',
    'tituloId' => 'libro-iva-digital-procesando-titulo',
    'subtituloId' => 'libro-iva-digital-procesando-subtitulo',
    'titulo' => 'Generando Libro IVA Digital e IVA Simple…',
    'subtitulo' => 'Armando ventas, compras (ERP y Anita) e importes por actividad/concepto. Puede demorar. No cierre la página.',
])

<script>
    (function () {
        var overlay = document.getElementById('libro-iva-digital-procesando-overlay');
        if (!overlay) {
            return;
        }

        function mostrarProcesoOverlay(titulo, subtitulo) {
            if (titulo) {
                var tituloEl = document.getElementById('libro-iva-digital-procesando-titulo');
                if (tituloEl) {
                    tituloEl.textContent = titulo;
                }
            }
            if (subtitulo) {
                var subEl = document.getElementById('libro-iva-digital-procesando-subtitulo');
                if (subEl) {
                    subEl.textContent = subtitulo;
                }
            }
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
        }

        function ocultarProcesoOverlay() {
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
        }

        var form = document.getElementById('form-libro-iva-digital');
        if (form) {
            form.addEventListener('submit', function () {
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                var btn = document.getElementById('btn-consultar-libro-iva-digital');
                if (btn) {
                    btn.disabled = true;
                }
                mostrarProcesoOverlay(
                    'Generando Libro IVA Digital e IVA Simple…',
                    'Armando ventas, compras (ERP y Anita) e importes por actividad/concepto. Puede demorar. No cierre la página.'
                );
            });
        }

        var btnZip = document.getElementById('btn-exportar-libro-iva-digital');
        if (btnZip) {
            btnZip.addEventListener('click', function () {
                mostrarProcesoOverlay(
                    'Generando ZIP de Libro IVA Digital e IVA Simple…',
                    'Preparando los archivos ASCII/CSV. Puede demorar. No cierre la página.'
                );
            });
        }

        var btnIvaSimple = document.getElementById('btn-exportar-iva-simple');
        if (btnIvaSimple) {
            btnIvaSimple.addEventListener('click', function () {
                mostrarProcesoOverlay(
                    'Generando IVA Simple…',
                    'Armando débito (ventas) y crédito fiscal (compras). Puede demorar. No cierre la página.'
                );
            });
        }

        window.addEventListener('pageshow', ocultarProcesoOverlay);
    })();
</script>
@endsection
