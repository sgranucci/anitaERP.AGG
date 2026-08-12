@php
    $bloqueado = ($data->estado ?? '') === \App\Support\Compras\ComprobanteProveedorEstados::CONTABILIZADO;
    $archivos = collect($data->comprobante_proveedor_archivos ?? []);
    $archivoFactura = $archivos->firstWhere('tipo', \App\Support\Compras\ComprobanteProveedorArchivoTipos::ORIGEN_IA);
    $archivosSubidos = $archivos->filter(fn ($a) => in_array($a->tipo, \App\Support\Compras\ComprobanteProveedorArchivoTipos::subibles(), true));
    $tieneFacturaExterna = $archivoFactura || filled($ruta_factura_pdf ?? null);
    $precargaId = $data->precarga_comprobante_proveedor_id ?? null;
@endphp

<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h5 class="card-title mb-0">Archivos asociados</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2">
            Facturas de precarga o PDF+IA (enlace a <code>Facturas_scan</code>) y documentos propios del comprobante
            (remitos, adjuntos, respaldos contables).
        </p>

        <table class="table table-striped table-bordered mb-3" id="cp-tabla-archivos-asociados">
            <thead style="background-color:#85C1E9;color:#17202A;">
                <tr>
                    <th style="width: 160px;">Tipo</th>
                    <th>Archivo</th>
                    <th style="width: 140px;">Origen</th>
                    <th style="width: 120px;"></th>
                </tr>
            </thead>
            <tbody>
                @if ($tieneFacturaExterna)
                    @php
                        $nombreFactura = $archivoFactura?->nombrearchivo
                            ?? ($ruta_factura_pdf ? basename($ruta_factura_pdf) : 'factura.pdf');
                        $urlFactura = route('comprobante_proveedor_factura_pdf', ['id' => $data->id, 'inline' => 1]);
                    @endphp
                    <tr>
                        <td>{{ \App\Support\Compras\ComprobanteProveedorArchivoTipos::etiqueta(\App\Support\Compras\ComprobanteProveedorArchivoTipos::ORIGEN_IA) }}</td>
                        <td><small class="text-monospace">{{ $nombreFactura }}</small></td>
                        <td><small>Facturas_scan</small></td>
                        <td>
                            <a href="{{ $urlFactura }}" class="text-primary" target="_blank" rel="noopener">Ver PDF</a>
                        </td>
                    </tr>
                @endif

                @foreach ($archivosSubidos as $arch)
                    @php
                        $urlArchivo = route('comprobante_proveedor_archivo', ['id' => $data->id, 'archivo' => $arch->id]);
                        $urlInline = $urlArchivo.'?inline=1';
                    @endphp
                    <tr>
                        <td>{{ \App\Support\Compras\ComprobanteProveedorArchivoTipos::etiqueta($arch->tipo) }}</td>
                        <td><small>{{ $arch->nombrearchivo }}</small></td>
                        <td><small>ERP</small></td>
                        <td>
                            <a href="{{ $urlInline }}" class="text-primary" target="_blank" rel="noopener">Abrir</a>
                        </td>
                    </tr>
                @endforeach

                @if (! $tieneFacturaExterna && $archivosSubidos->isEmpty())
                    <tr>
                        <td colspan="4" class="text-muted small">Sin archivos asociados.</td>
                    </tr>
                @endif
            </tbody>
        </table>

        @if (filled($ruta_factura_pdf ?? null))
            <p class="small text-muted mb-0">
                Ruta precarga: <code>{{ $ruta_factura_pdf }}</code>
                @if ($precargaId)
                    · <a href="{{ route('precarga_comprobante_proveedor_factura_pdf', ['id' => $precargaId, 'inline' => 1]) }}"
                         class="text-primary" target="_blank" rel="noopener">Ver desde precarga</a>
                @endif
            </p>
        @endif

        @if ($archivosSubidos->isNotEmpty())
            <h5 class="mt-3">Vista previa adjuntos ERP</h5>
            @include('compras.comprobante_proveedor.partials.archivos_adjuntos', ['data' => $data])
        @endif
    </div>
</div>

@if (! $bloqueado)
<div class="card card-outline card-primary mb-4">
    <div class="card-header py-2">
        <h5 class="card-title mb-0">Agregar archivos nuevos</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2">
            Puede adjuntar remitos (p. ej. páginas extraídas del PDF), otros comprobantes o respaldos.
            Use <strong>+ Agrega renglón</strong> para varios archivos. Guarde el comprobante para persistir.
        </p>
        <table class="table table-sm table-bordered" id="cp-archivo-table">
            <thead style="background-color:#85C1E9;color:#17202A;">
                <tr>
                    <th>Archivo</th>
                    <th style="width: 160px;">Tipo</th>
                    <th style="width: 90px;"></th>
                </tr>
            </thead>
            <tbody id="cp-tbody-tabla-archivo">
            </tbody>
        </table>
        <div class="row">
            <div class="col-md-12">
                <button id="cp-agrega-renglon-archivo" type="button" class="btn btn-outline-primary btn-sm pull-right">+ Agrega renglón</button>
            </div>
        </div>
    </div>
</div>
@endif
