@extends("theme.$theme.layout")
@section('titulo')
    Informe flash
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'informe-flash-overlay',
    'tituloId' => 'informe-flash-titulo',
    'subtituloId' => 'informe-flash-subtitulo',
    'titulo' => 'Armando el informe…',
    'subtitulo' => 'El mes acumulado recorre cada día. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title mb-0">Informe flash</h3>
                <div class="card-tools">
                    <a href="{{ route('flash_crown') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Flash diario
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form id="form-informe-flash" method="get" action="{{ route('flash_reporte_crown') }}" class="form-horizontal" autocomplete="off">
                    @csrf
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2" for="fecha">Día</label>
                        <div class="col-lg-2">
                            <input type="date" name="fecha" id="fecha" class="form-control form-control-sm" required value="{{ $fecha }}">
                        </div>
                        <label class="col-lg-2 control-label text-right pr-2" for="formato">Formato</label>
                        <div class="col-lg-3">
                            <select name="formato" id="formato" class="form-control form-control-sm">
                                @foreach ($formatos as $clave => $etiqueta)
                                    <option value="{{ $clave }}" {{ $formato === $clave ? 'selected' : '' }}>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2" for="destinatarios">Destinatarios</label>
                        <div class="col-lg-8">
                            <textarea name="destinatarios" id="destinatarios" class="form-control form-control-sm" rows="6">{{ $destinatarios }}</textarea>
                            <small class="text-muted">Un mail por línea. La lista es propia de cada formato (Flash completo, Gaming revenues, Daily Flash). Enviar / Guardar destinatarios aplica al formato elegido.</small>
                        </div>
                    </div>
                    <div class="form-group row mb-0">
                        <div class="col-lg-8 offset-lg-2">
                            <button type="submit" class="btn btn-primary btn-sm">Vista previa</button>
                            @if (can('exportar-flash-reporte-crown', false))
                                <button type="submit"
                                        class="btn btn-success btn-sm"
                                        id="btn-informe-flash-excel"
                                        formaction="{{ route('exportar_informe_flash_reporte_crown') }}">
                                    <i class="fa fa-file-excel-o"></i> Generar Excel
                                </button>
                            @endif
                            @if ($puedeEnviar)
                                <button type="submit"
                                        class="btn btn-outline-success btn-sm"
                                        form="form-informe-flash"
                                        formaction="{{ route('enviar_flash_reporte_crown') }}"
                                        formmethod="post"
                                        onclick="return confirm('¿Enviar este informe a todos los destinatarios?');">
                                    Enviar
                                </button>
                                <button type="submit"
                                        class="btn btn-outline-secondary btn-sm"
                                        form="form-informe-flash"
                                        formaction="{{ route('guardar_destinatarios_flash_reporte_crown') }}"
                                        formmethod="post">
                                    Guardar destinatarios
                                </button>
                            @endif
                        </div>
                    </div>
                </form>

                <p class="text-muted small mt-3 mb-0">
                    Gaming revenues: día pedido, mismo día de la semana del año anterior, acumulado del mes y del mismo mes del año anterior (mesas + slots + A&amp;B).
                    Daily Flash: MTD del 1 al día pedido (Resumen + cada sala), estilo Excel Crown / l-flashcasino, con Combined año anterior y variación.
                </p>

                @if ($informe)
                    <hr>
                    <h5 class="mb-2">
                        @if ($formato === 'gaming')
                            Gaming revenues {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                            <small class="text-muted">
                                comparativo {{ $informe['fecha_comparable']->format('d/m/Y') }}
                                ({{ $informe['columnas'][1]['titulo'] }})
                            </small>
                        @elseif ($formato === 'daily')
                            Daily Flash Report {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                            <small class="text-muted">
                                MTD {{ $informe['mtd_desde']->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                            </small>
                        @else
                            Flash completo {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                        @endif
                    </h5>
                    <div class="table-responsive">
                        @if ($formato === 'gaming')
                            @include('caja.flash_crown.reporte.partials.tabla_gaming', ['informe' => $informe])
                        @elseif ($formato === 'daily')
                            @include('caja.flash_crown.reporte.partials.tabla_daily', [
                                'informe' => $informe,
                                'con_solapas' => true,
                            ])
                        @else
                            @include('caja.flash_crown.reporte.partials.tabla_completo', ['informe' => $informe])
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var form = document.getElementById('form-informe-flash');
    var overlay = document.getElementById('informe-flash-overlay');
    var exportAbort = null;
    if (!form || !overlay) {
        return;
    }
    var selectFormato = document.getElementById('formato');
    var textareaDest = document.getElementById('destinatarios');
    var destinatariosPorFormato = @json($destinatariosPorFormato ?? []);
    var formatoActual = selectFormato ? selectFormato.value : '';
    if (selectFormato && textareaDest) {
        selectFormato.addEventListener('change', function () {
            if (formatoActual) {
                destinatariosPorFormato[formatoActual] = textareaDest.value;
            }
            formatoActual = selectFormato.value;
            textareaDest.value = destinatariosPorFormato[formatoActual] || '';
        });
    }
    function mostrar(titulo, subtitulo) {
        var nodo = document.getElementById('informe-flash-titulo');
        if (nodo && titulo) {
            nodo.textContent = titulo;
        }
        var sub = document.getElementById('informe-flash-subtitulo');
        if (sub && subtitulo) {
            sub.textContent = subtitulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultar() {
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    function nombreDesdeDisposition(disposition, fallback) {
        if (!disposition) {
            return fallback;
        }
        var match = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"|filename=([^;]+)/i.exec(disposition);
        if (!match) {
            return fallback;
        }
        var raw = (match[1] || match[2] || match[3] || '').trim();
        try {
            return decodeURIComponent(raw.replace(/['"]/g, ''));
        } catch (e) {
            return raw.replace(/['"]/g, '') || fallback;
        }
    }
    function dispararDescargaBlob(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'informe_flash.xlsx';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }
    function descargarExcel(url) {
        if (exportAbort) {
            exportAbort.abort();
        }
        exportAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;
        mostrar('Generando Excel…', 'Armando el informe. Pulse Esc para cerrar este aviso.');
        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: exportAbort ? exportAbort.signal : undefined
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.blob().then(function (blob) {
                    return {
                        blob: blob,
                        filename: nombreDesdeDisposition(res.headers.get('Content-Disposition'), 'informe_flash.xlsx')
                    };
                });
            })
            .then(function (pack) {
                dispararDescargaBlob(pack.blob, pack.filename);
                ocultar();
            })
            .catch(function (err) {
                ocultar();
                if (err && err.name === 'AbortError') {
                    return;
                }
                alert((err && err.message) || 'No se pudo generar el Excel');
            });
    }
    form.addEventListener('submit', function (e) {
        if (!form.checkValidity()) {
            return;
        }
        var boton = e.submitter;
        var formaction = boton && boton.getAttribute('formaction') ? boton.getAttribute('formaction') : '';
        if (formaction.indexOf('/excel') !== -1 || (boton && boton.id === 'btn-informe-flash-excel')) {
            e.preventDefault();
            var params = new URLSearchParams(new FormData(form));
            params.delete('_token');
            params.delete('consultar');
            params.delete('destinatarios');
            var sep = formaction.indexOf('?') === -1 ? '?' : '&';
            descargarExcel(formaction + sep + params.toString());
            return;
        }
        var accion = formaction ? (boton.textContent || '').trim() : 'Vista previa';
        if (accion.indexOf('Guardar') === 0) {
            return;
        }
        mostrar(
            accion.indexOf('Enviar') === 0 ? 'Enviando el informe…' : 'Armando la vista previa…',
            'El mes acumulado recorre cada día. No cierre la página.'
        );
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && exportAbort) {
            exportAbort.abort();
            ocultar();
        }
    });
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection
