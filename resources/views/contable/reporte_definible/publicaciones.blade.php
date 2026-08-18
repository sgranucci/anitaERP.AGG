@extends("theme.$theme.layout")
@section('titulo')
    Resultados publicados
@endsection

@section('contenido')
<div class="row">
    <div class="col-md-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Resultados publicados — {{ $reporte->titulo1 ?: $reporte->nombre }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('ejecutar_reporte_definible', ['id' => $reporte->id]) }}"
                       class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a ejecutar
                    </a>
                </div>
            </div>
            <div class="card-body">
                @include('includes.mensaje')

                <div class="alert alert-info py-2">
                    Cada publicación guarda los números <strong>tal como se presentaron</strong>, con los filtros usados
                    y una huella (hash) del contenido. Se reimprimen sin recalcular, así el balance presentado en su
                    momento sigue saliendo igual aunque después cambie la definición o se reprocesen asientos.
                </div>

                <div class="table-responsive">
                    <table id="tabla-paginada" class="table table-sm table-hover">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:130px">Publicado</th>
                                <th>Nombre</th>
                                <th style="width:150px">Período</th>
                                <th style="width:110px">Empresas</th>
                                <th class="text-right" style="width:70px">Filas</th>
                                <th style="width:100px">Definición</th>
                                <th>Usuario</th>
                                <th style="width:130px">Huella</th>
                                <th class="text-center" style="width:110px">Reimprimir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($publicaciones as $pub)
                                @php $empresas = $pub->filtros['empresa_ids'] ?? []; @endphp
                                <tr>
                                    <td>{{ $pub->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        {{ $pub->nombre }}
                                        @if ($pub->observacion)
                                            <div class="small text-muted">{{ $pub->observacion }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $pub->periodo_texto }}</td>
                                    <td>{{ is_array($empresas) ? implode(', ', $empresas) : '' }}</td>
                                    <td class="text-right">{{ $pub->filas }}</td>
                                    <td>v{{ $pub->definicion_version }}</td>
                                    <td>{{ $pub->usuario->nombre ?? '' }}</td>
                                    <td><code class="small">{{ substr((string) $pub->hash, 0, 12) }}</code></td>
                                    <td class="text-center">
                                        <a href="{{ \App\Support\Navegacion\ModoConsultaUrlSupport::route('ver_publicacion_reporte_definible', ['id' => $reporte->id, 'publicacionId' => $pub->id]) }}"
                                           class="btn-accion-tabla tooltipsC" title="Ver el documento congelado"
                                           target="_blank" rel="noopener">
                                            <i class="fa fa-eye text-primary"></i>
                                        </a>
                                        <a href="{{ route('ver_publicacion_reporte_definible', ['id' => $reporte->id, 'publicacionId' => $pub->id, 'formato' => 'PDF']) }}"
                                           class="btn-accion-tabla tooltipsC" title="PDF idéntico al presentado"
                                           target="_blank" rel="noopener">
                                            <i class="fa fa-file-pdf text-danger"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">
                                        Todavía no se publicó ningún resultado de este informe.
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
@endsection
