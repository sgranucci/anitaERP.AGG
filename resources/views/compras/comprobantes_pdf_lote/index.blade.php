@extends("theme.$theme.layout")
@section('titulo')
    Comprobantes en PDF
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.COMPROBANTES_PDF_LOTE = {
        leerProveedorUrlBase: @json(url('compras/leerproveedorporcodigo'))
    };
</script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/comprobantes_pdf_lote/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/comprobantes_pdf_lote/filtro.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Comprobantes en PDF</h3>
                <div class="card-tools">
                    <a href="{{ route('comprobantes_pdf_lote') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('comprobantes_pdf_lote') }}" id="form-comprobantes-pdf-lote" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Lista, para una empresa, uno o más proveedores y un rango de fechas, las facturas y los documentos que marque:
                        OC, COM, OPP y OPA. Puede tildar uno o varios: el listado y el ZIP los juntan.
                        El PDF de la factura sale de la precarga, de Facturas_scan o del escaneo histórico.
                        OC, COM, OPP y OPA se arman al verlos o al bajar el lote.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                        $empresasDisponibles = collect($empresa_query ?? []);
                    @endphp

                    <div class="form-group row">
                        <label for="empresa_id" class="{{ $colLabel }} requerido">Empresa</label>
                        <div class="{{ $colInput }}">
                            @if ($empresasDisponibles->count() > 1)
                                <select name="empresa_id" id="empresa_id" class="form-control" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($empresasDisponibles as $emp)
                                        <option value="{{ $emp->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id)>
                                            {{ $emp->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif ($empresasDisponibles->count() === 1)
                                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ (int) $empresasDisponibles->first()->id }}">
                                <span class="form-control-plaintext">{{ $empresasDisponibles->first()->nombre }}</span>
                            @else
                                <p class="text-danger small mb-0">Sin empresas asignadas.</p>
                            @endif
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? date('Y-m-01') }}" required>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }} requerido">Hasta</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? date('Y-m-d') }}" required>
                        </div>
                    </div>

                    @include('compras.comprobantes_pdf_lote.partials.selector_proveedores', [
                        'proveedoresIniciales' => $proveedores_iniciales ?? [],
                    ])

                    <div class="form-group row">
                        <label class="{{ $colLabel }} requerido">Incluir</label>
                        <div class="col-lg-10 pt-1">
                            <input type="hidden" name="tipos_enviados" value="1">
                            @foreach (\App\Support\Compras\ComprobantesPdfLoteFiltros::TIPOS as $tipoClave => $tipoNombre)
                                <label class="mr-3 mb-1" style="font-weight: normal;">
                                    <input type="checkbox" name="tipos[]" value="{{ $tipoClave }}"
                                        @checked(in_array($tipoClave, $filtros['tipos'] ?? ['fac'], true))>
                                    {{ $tipoNombre }}
                                </label>
                            @endforeach
                            <p class="small text-muted mb-0">Las tildes se acumulan: puede marcar solo facturas, solo órdenes de pago, o todas juntas.</p>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="{{ $colLabel }} d-none d-lg-block"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @include('includes.proceso_overlay_aviso', [
                'overlayId' => 'comprobantes-pdf-lote-overlay',
                'tituloId' => 'comprobantes-pdf-lote-titulo',
                'subtituloId' => 'comprobantes-pdf-lote-subtitulo',
                'titulo' => 'Consultando comprobantes…',
                'subtitulo' => 'Puede demorar si hay que buscar el PDF en el escaneo. No cierre la página.',
            ])

            @if ($consultado ?? false)
                <div class="card-body p-0 border-top">
                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="small mb-1 mb-md-0">
                            <span class="text-muted">Documentos:</span>
                            <strong>{{ $filas ? $filas->total() : 0 }}</strong>
                            · {{ \Carbon\Carbon::parse($filtros['fecha_desde'])->format('d/m/Y') }}
                            al {{ \Carbon\Carbon::parse($filtros['fecha_hasta'])->format('d/m/Y') }}
                        </div>
                        @if ($filas && $filas->total() > 0)
                            <div class="d-flex flex-wrap" style="gap: 6px;">
                                <button type="submit" form="form-zip-marcados" class="btn btn-success btn-sm"
                                    title="ZIP con los documentos tildados de esta página">
                                    <i class="fa fa-check-square"></i> Descargar marcados
                                </button>
                                <a href="{{ route('comprobantes_pdf_lote_zip', $filtrosQuery ?? []) }}"
                                   class="btn btn-outline-success btn-sm js-descargar-zip"
                                   title="ZIP con todos los documentos de los tipos tildados, no solo esta página">
                                    <i class="fa fa-file-archive"></i> Descargar todo el lote
                                </a>
                            </div>
                        @endif
                    </div>

                    @if ($filas && $filas->total() > 0)
                        <form method="get" action="{{ route('comprobantes_pdf_lote_zip') }}" id="form-zip-marcados" class="d-none">
                            <input type="hidden" name="empresa_id" value="{{ (int) ($filtros['empresa_id'] ?? 0) }}">
                            <input type="hidden" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
                            <input type="hidden" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                            <input type="hidden" name="proveedor_ids" value="{{ implode(',', $filtros['proveedor_ids'] ?? []) }}">
                            <input type="hidden" name="tipos_enviados" value="1">
                            @foreach ($filtros['tipos'] ?? [] as $tipoMarcado)
                                <input type="hidden" name="tipos[]" value="{{ $tipoMarcado }}">
                            @endforeach
                        </form>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 0.85rem;">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th class="text-center" style="width: 36px;" title="Marcar los de esta página">
                                        <input type="checkbox" id="marcar-todos-pdf-lote">
                                    </th>
                                    <th>Fecha</th>
                                    <th>Proveedor</th>
                                    <th>Tipo</th>
                                    <th>Comprobante</th>
                                    <th class="text-right">Total</th>
                                    <th>PDF</th>
                                    <th class="text-center" style="width: 110px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filas ?? [] as $fila)
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="js-clave-pdf-lote" value="{{ $fila->clave }}"
                                                title="Incluir en el ZIP de marcados">
                                        </td>
                                        <td>{{ $fila->fecha }}</td>
                                        <td>{{ $fila->proveedor }}</td>
                                        <td>{{ $fila->tipoEtiqueta }}</td>
                                        <td>{{ $fila->numero }}</td>
                                        <td class="text-right">{{ number_format((float) $fila->total, 2, ',', '.') }}</td>
                                        <td>
                                            @if ($fila->tienePdf)
                                                <span class="badge badge-info">{{ $fila->pdfEtiqueta }}</span>
                                            @else
                                                <span class="badge badge-secondary">Sin PDF</span>
                                            @endif
                                        </td>
                                        <td class="text-center text-nowrap">
                                            @if (($puede_editar ?? false) && $fila->editarUrl)
                                                <a class="btn-accion-tabla tooltipsC" title="Editar"
                                                   href="{{ $fila->editarUrl }}"
                                                   target="_blank" rel="noopener">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                            @endif
                                            @if ($fila->tienePdf && $fila->pdfUrl)
                                                <a class="btn-accion-tabla tooltipsC" title="Ver PDF"
                                                   href="{{ $fila->pdfUrl }}"
                                                   target="_blank" rel="noopener">
                                                    <i class="fa fa-file-pdf"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-3">No hay documentos para esos filtros.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $filas->total() > 0)
                        <div class="px-3 py-2 d-flex flex-wrap align-items-center justify-content-between border-top">
                            <div class="small text-muted mb-1">
                                Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                            </div>
                            <div>{{ $filas->links() }}</div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@endsection
