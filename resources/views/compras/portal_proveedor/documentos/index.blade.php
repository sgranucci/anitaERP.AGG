@extends("theme.$theme.layout")

@section('titulo')
    Documentación fiscal — Portal
@endsection

@section('styles')
    @include('compras.portal_proveedor.partials.estilos')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        @include('compras.portal_proveedor.partials.selector_cuenta', [
            'rutaSelector' => route('portal_proveedores_documentos'),
            'proveedor' => $proveedor,
            'proveedorId' => $proveedorId,
        ])

        @if ($proveedor)
        <div class="card">
            @include('compras.portal_proveedor.partials.cabecera_proveedor', [
                'proveedor' => $proveedor,
                'moduloActivo' => 'documentos',
                'canalMail' => $canalMail ?? [],
                'pdfIaHabilitado' => false,
            ])

            <div class="card-body">
                @include('compras.portal_proveedor.partials.nav_modulos', [
                    'moduloActivo' => 'documentos',
                    'proveedorId' => $proveedorId,
                ])

                @include('compras.portal_proveedor.partials.avisos_documentos_fiscales', [
                    'avisos' => $avisos ?? [],
                ])

                <div class="row">
                    <div class="col-lg-5 mb-3">
                        <div class="card card-outline card-primary h-100 mb-0">
                            <div class="card-header py-2">
                                <h3 class="card-title mb-0"><i class="fa fa-upload"></i> Presentar CUIT / CM05</h3>
                            </div>
                            <div class="card-body">
                                @if (!empty($puedePresentar))
                                    <form action="{{ route('guardar_portal_proveedores_documentos') }}"
                                          method="POST"
                                          enctype="multipart/form-data"
                                          class="form-horizontal">
                                        @csrf
                                        <input type="hidden" name="proveedor_id" value="{{ $proveedorId }}">
                                        <div class="form-group row">
                                            <label class="col-lg-4 control-label text-right pr-2 requerido">Tipo</label>
                                            <div class="col-lg-8">
                                                <select name="tipo" class="form-control" required>
                                                    <option value="CUIT">Constancia CUIT</option>
                                                    <option value="CM05">CM05 anual</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-lg-4 control-label text-right pr-2 requerido">Vencimiento</label>
                                            <div class="col-lg-8">
                                                <input type="date" name="fecha_vencimiento" class="form-control" required
                                                       value="{{ old('fecha_vencimiento') }}">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-lg-4 control-label text-right pr-2">Año CM05</label>
                                            <div class="col-lg-8">
                                                <input type="number" name="anio_ejercicio" class="form-control"
                                                       min="2000" max="2100"
                                                       value="{{ old('anio_ejercicio', date('Y')) }}"
                                                       placeholder="Solo para CM05">
                                                <small class="text-muted">Obligatorio de negocio para CM05 anual.</small>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-lg-4 control-label text-right pr-2 requerido">Archivo</label>
                                            <div class="col-lg-8">
                                                <input type="file" name="archivo" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fa fa-save"></i> Presentar
                                            </button>
                                        </div>
                                    </form>
                                @else
                                    <p class="text-muted mb-0">
                                        No tiene permiso <code>cargar-portal-proveedores</code> para presentar documentación.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7 mb-3">
                        <h5><i class="fa fa-folder-open"></i> Historial presentado</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="tabla-paginada">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Vencimiento</th>
                                        <th>Año</th>
                                        <th>Estado</th>
                                        <th>Origen</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($documentos as $doc)
                                        @php
                                            $est = $doc->estadoVigencia();
                                            $badge = match ($est) {
                                                'vigente' => 'portal-estado-confirmada',
                                                'proximo' => 'portal-estado-oc-pendiente',
                                                'vencido' => 'portal-estado-baja',
                                                default => 'badge-secondary',
                                            };
                                        @endphp
                                        <tr>
                                            <td>{{ $doc->etiquetaTipo() }}</td>
                                            <td>{{ optional($doc->fecha_vencimiento)->format('d/m/Y') ?: '—' }}</td>
                                            <td>{{ $doc->anio_ejercicio ?: '—' }}</td>
                                            <td><span class="badge {{ $badge }}">{{ strtoupper($est) }}</span></td>
                                            <td>{{ $doc->origen }}</td>
                                            <td>
                                                <a href="{{ route('portal_proveedores_documento_archivo', ['id' => $doc->id, 'proveedor_id' => $proveedorId]) }}"
                                                   class="btn-accion-tabla tooltipsC"
                                                   title="Ver / descargar"
                                                   target="_blank" rel="noopener">
                                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">
                                                <div class="portal-empty">
                                                    <i class="fa fa-id-card-o"></i>
                                                    Todavía no hay constancia CUIT ni CM05 presentados.
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @include('includes.compras.modalconsultaproveedor')
    </div>
</div>
@endsection
