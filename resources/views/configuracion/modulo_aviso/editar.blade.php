@extends("theme.$theme.layout")
@section('titulo')
Configurar aviso — {{ $tipo->nombre }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-envelope-o"></i> {{ $tipo->nombre }}
                    <small class="text-muted">({{ $tipo->modulo }} / {{ $tipo->codigo }})</small>
                </h3>
                <div class="card-tools">
                    <a href="{{ url('configuracion/modulo-aviso') }}" class="btn btn-sm btn-default">Volver al listado</a>
                </div>
            </div>
            <form action="{{ url('configuracion/modulo-aviso/'.$tipo->id) }}" method="POST" id="form-modulo-aviso" autocomplete="off">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" class="form-check-input" name="activo" id="activo" value="1"
                            @if(old('activo', $tipo->activo)) checked @endif>
                        <label class="form-check-label" for="activo">Envío activo</label>
                    </div>

                    <div class="form-group">
                        <label for="mail_asunto">Asunto del correo</label>
                        <input type="text" class="form-control" name="mail_asunto" id="mail_asunto" maxlength="255" required
                            value="{{ old('mail_asunto', $tipo->mail_asunto) }}">
                    </div>
                    <div class="form-group">
                        <label for="mail_texto">Texto del cuerpo</label>
                        <textarea class="form-control" name="mail_texto" id="mail_texto" rows="4" maxlength="8000">{{ old('mail_texto', $tipo->mail_texto) }}</textarea>
                        <small class="text-muted">Placeholders: {{ implode(', ', $placeholders_ayuda) }}</small>
                    </div>
                    <div class="form-group">
                        <label for="mail_remitente">Remitente (From) opcional</label>
                        <input type="email" class="form-control" name="mail_remitente" id="mail_remitente" maxlength="255"
                            value="{{ old('mail_remitente', $tipo->mail_remitente) }}">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="hidden" name="adjuntar_pdf" value="0">
                                <input type="checkbox" class="form-check-input" name="adjuntar_pdf" id="adjuntar_pdf" value="1"
                                    @if(old('adjuntar_pdf', $tipo->adjuntar_pdf)) checked @endif>
                                <label class="form-check-label" for="adjuntar_pdf">Adjuntar PDF de emisión</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="hidden" name="incluir_link_consulta" value="0">
                                <input type="checkbox" class="form-check-input" name="incluir_link_consulta" id="incluir_link_consulta" value="1"
                                    @if(old('incluir_link_consulta', $tipo->incluir_link_consulta)) checked @endif>
                                <label class="form-check-label" for="incluir_link_consulta">Incluir enlace a consulta en el ERP</label>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h5>Destinatarios</h5>
                    <p class="text-muted small">
                        Podés indicar email directo y/o usuario del sistema. Los filtros de empresa y centro de costo
                        limitan el aviso solo a documentos de ese ámbito (vacío = todos).
                    </p>
                    <table class="table table-sm" id="tabla-destinatarios-aviso">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Usuario</th>
                                <th>Empresa filtro</th>
                                <th>CC filtro</th>
                                <th class="text-center">Activo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $filasDest = old('destinatarios');
                                if ($filasDest === null) {
                                    $filasDest = $tipo->destinatarios->map(fn ($d) => [
                                        'id' => $d->id,
                                        'email' => $d->email,
                                        'usuario_id' => $d->usuario_id,
                                        'empresa_id' => $d->empresa_id,
                                        'centrocosto_id' => $d->centrocosto_id,
                                        'activo' => $d->activo,
                                    ])->all();
                                }
                                if ($filasDest === [] || $filasDest === null) {
                                    $filasDest = [['id' => '', 'email' => '', 'usuario_id' => '', 'empresa_id' => '', 'centrocosto_id' => '', 'activo' => true]];
                                }
                            @endphp
                            @foreach ($filasDest as $idx => $fila)
                            <tr class="fila-destinatario-aviso">
                                <td>
                                    <input type="hidden" name="destinatarios[{{ $idx }}][id]" value="{{ $fila['id'] ?? '' }}">
                                    <input type="email" class="form-control form-control-sm" name="destinatarios[{{ $idx }}][email]"
                                        value="{{ $fila['email'] ?? '' }}" placeholder="correo@empresa.com">
                                </td>
                                <td>
                                    <select class="form-control form-control-sm" name="destinatarios[{{ $idx }}][usuario_id]">
                                        <option value="">—</option>
                                        @foreach ($usuario_query as $u)
                                        <option value="{{ $u->id }}" @if((string)($fila['usuario_id'] ?? '') === (string)$u->id) selected @endif>
                                            {{ $u->nombre }} ({{ $u->email }})
                                        </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="form-control form-control-sm" name="destinatarios[{{ $idx }}][empresa_id]">
                                        <option value="">Todas</option>
                                        @foreach ($empresa_query as $emp)
                                        <option value="{{ $emp->id }}" @if((string)($fila['empresa_id'] ?? '') === (string)$emp->id) selected @endif>{{ $emp->nombre }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="form-control form-control-sm" name="destinatarios[{{ $idx }}][centrocosto_id]">
                                        <option value="">Todos</option>
                                        @foreach ($centrocosto_query as $cc)
                                        @if($cc->id > 0)
                                        <option value="{{ $cc->id }}" @if((string)($fila['centrocosto_id'] ?? '') === (string)$cc->id) selected @endif>{{ $cc->codigo }} - {{ $cc->nombre }}</option>
                                        @endif
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-center">
                                    <input type="hidden" name="destinatarios[{{ $idx }}][activo]" value="0">
                                    <input type="checkbox" name="destinatarios[{{ $idx }}][activo]" value="1"
                                        @if(!empty($fila['activo'])) checked @endif>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger btn-quitar-destinatario" title="Quitar fila">&times;</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-agregar-destinatario">
                        <i class="fa fa-plus"></i> Agregar destinatario
                    </button>
                </div>
                <div class="card-footer">
                    @include('includes.boton-form-editar')
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var contador = {{ count($filasDest) }};
    $('#btn-agregar-destinatario').on('click', function () {
        var $tbody = $('#tabla-destinatarios-aviso tbody');
        var $primera = $tbody.find('tr.fila-destinatario-aviso').first().clone();
        $primera.find('input[type=email]').val('');
        $primera.find('input[type=hidden][name*="[id]"]').val('');
        $primera.find('select').prop('selectedIndex', 0);
        $primera.find('input[type=checkbox]').prop('checked', true);
        $primera.find('[name]').each(function () {
            var name = $(this).attr('name');
            if (name) {
                $(this).attr('name', name.replace(/\[\d+\]/, '[' + contador + ']'));
            }
        });
        $tbody.append($primera);
        contador++;
    });
    $(document).on('click', '.btn-quitar-destinatario', function () {
        var $rows = $('#tabla-destinatarios-aviso tbody tr');
        if ($rows.length <= 1) {
            $(this).closest('tr').find('input[type=email]').val('');
            $(this).closest('tr').find('select').prop('selectedIndex', 0);
            return;
        }
        $(this).closest('tr').remove();
    });
})();
</script>
@endsection
