@extends("theme.$theme.layout")
@section('titulo')
    Libro IVA Digital
@endsection

@section('contenido')
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

                    <div class="form-group row">
                        <label for="periodo" class="col-lg-2 control-label requerido">Período</label>
                        <div class="col-lg-3">
                            <input type="month" name="periodo" id="periodo" class="form-control required"
                                   value="{{ old('periodo', $filtros['periodo'] ?? '') }}" required>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            @if (can('exportar-libro-iva-digital', false) && !empty($resultado))
                                <a href="{{ route('exportar_libro_iva_digital', $filtros) }}"
                                   class="btn btn-success btn-sm ml-1">
                                    <i class="fa fa-download"></i> Descargar ZIP
                                </a>
                            @endif
                        </div>
                    </div>
                </form>

                @if ($consultado && $resultado)
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

                    <div class="table-responsive">
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
                                    <td><code>LIBRO_IVA_DIGITAL_VENTAS_CBTE</code></td>
                                    <td class="text-right">{{ number_format($resultado['ventas']['resumen']['comprobantes'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-right">${{ number_format($resultado['ventas']['resumen']['importe_total'] ?? 0, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>LIBRO_IVA_DIGITAL_VENTAS_ALICUOTAS</code></td>
                                    <td class="text-right">{{ number_format($resultado['ventas']['resumen']['alicuotas'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Facturas A/M con IVA discriminado</td>
                                </tr>
                                <tr>
                                    <td><code>LIBRO_IVA_DIGITAL_VENTAS_ANULADOS</code></td>
                                    <td class="text-right">{{ number_format($resultado['anulados']['resumen']['ventas'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Ventas con CAE eliminadas en el período</td>
                                </tr>
                                <tr>
                                    <td><code>LIBRO_IVA_DIGITAL_COMPRAS_CBTE</code></td>
                                    <td class="text-right">{{ number_format($resultado['compras']['resumen']['comprobantes'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-right">${{ number_format($resultado['compras']['resumen']['importe_total'] ?? 0, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>LIBRO_IVA_DIGITAL_COMPRAS_ALICUOTAS</code></td>
                                    <td class="text-right">{{ number_format($resultado['compras']['resumen']['alicuotas'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Mismo orden que cabeceras compras</td>
                                </tr>
                                <tr>
                                    <td><code>LIBRO_IVA_DIGITAL_COMPRAS_ANULADOS</code></td>
                                    <td class="text-right">{{ number_format($resultado['anulados']['resumen']['compras'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Compras anuladas en el período</td>
                                </tr>
                                <tr>
                                    <td><code>LIBRO_IVA_DIGITAL_IMPORTACION_BIENES_ALICUOTAS</code></td>
                                    <td class="text-right">{{ number_format($resultado['importaciones']['resumen']['bienes'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Tabla <code>libro_iva_importacion_bien</code></td>
                                </tr>
                                <tr>
                                    <td><code>LIBRO_IVA_DIGITAL_IMPORTACION_SERVICIOS</code></td>
                                    <td class="text-right">{{ number_format($resultado['importaciones']['resumen']['servicios'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Tabla <code>libro_iva_importacion_servicio</code></td>
                                </tr>
                                <tr>
                                    <td><code>IVA_SIMPLE_DEBITO_FISCAL.csv</code></td>
                                    <td class="text-right">{{ number_format($resultado['iva_simple']['resumen']['renglones_debito'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Por actividad ARCA / tipo sujeto / alícuota</td>
                                </tr>
                                <tr>
                                    <td><code>IVA_SIMPLE_CREDITO_FISCAL.csv</code></td>
                                    <td class="text-right">{{ number_format($resultado['iva_simple']['resumen']['renglones_credito'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Compras por concepto (tipoconcepto G/I)</td>
                                </tr>
                                <tr>
                                    <td><code>IVA_SIMPLE_RESTITUCION_DEBITO_FISCAL.csv</code></td>
                                    <td class="text-right">{{ number_format($resultado['iva_simple']['resumen']['renglones_restitucion_debito'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Notas de crédito ventas</td>
                                </tr>
                                <tr>
                                    <td><code>IVA_SIMPLE_RESTITUCION_CREDITO_FISCAL.csv</code></td>
                                    <td class="text-right">{{ number_format($resultado['iva_simple']['resumen']['renglones_restitucion_credito'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-muted">Notas de crédito compras</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
@endsection
