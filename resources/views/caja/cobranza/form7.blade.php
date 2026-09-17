<div class="card form7" style="display: none">
    <div class="card-body">
        <div class="card card-outline card-info mb-3">
            <div class="card-header py-2">
                <h3 class="card-title mb-0">Archivos asociados</h3>
            </div>
            <div class="card-body p-2">
                <table class="table table-sm table-bordered table-hover mb-0" id="archivo-table">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Archivo</th>
                            <th style="width: 8rem;">Vista</th>
                            <th style="width: 5rem;"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-tabla-archivo">
                    @if (($data->cobranza_archivos ?? null) && $data->cobranza_archivos->count())
                        @foreach ($data->cobranza_archivos as $archivo)
                            <tr class="item-archivo">
                                <td>
                                    <input type="file" name="nombrearchivos[]" class="form-control nombrearchivos"
                                        onchange="actualizaArchivo(this)"
                                        data-initial-preview="{{ isset($archivo->nombrearchivo) ? \App\Support\Archivos\ArchivoAdjuntoCacheSupport::urlStoragePublico('archivos/cobranzas/'.$data->id.'/'.$archivo->nombrearchivo) : '' }}">
                                    <input type="hidden" name="nombresanteriores[]" class="form-control nombresanteriores"
                                        value="{{ $archivo->nombrearchivo ?? '' }}" />
                                    @if ($archivo->nombrearchivo ?? '')
                                        <small class="text-muted">{{ $archivo->nombrearchivo }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if ($archivo->nombrearchivo ?? '')
                                        @if (substr($archivo->nombrearchivo ?? '', -3) == 'pdf')
                                            <img height="40" width="100" class="img-fluid rounded" src="{{ asset('storage/imagenes/pdf.png') }}" alt="PDF" />
                                        @else
                                            <img height="80" width="80" class="img-fluid rounded" src="{{ \App\Support\Archivos\ArchivoAdjuntoCacheSupport::urlStoragePublico('archivos/cobranzas/'.$data->id.'/'.$archivo->nombrearchivo) }}" alt="image">
                                        @endif
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    @if ($archivo->nombrearchivo ?? '')
                                        <a download="{{ $archivo->nombrearchivo }}" href="{{ \App\Support\Archivos\ArchivoAdjuntoCacheSupport::urlStoragePublico('archivos/cobranzas/'.$data->id.'/'.$archivo->nombrearchivo) }}" title="Descargar" class="btn-accion-tabla"><i class="fa fa-download"></i></a>
                                        <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminararchivo tooltipsC">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
        @include('caja.cobranza.template7')
        <button type="button" id="agrega_renglon_archivo" class="btn btn-outline-primary btn-sm">+ Agrega rengl&oacute;n</button>
    </div>
</div>
