@php
    $filasIntegrante = old('integrantes');
    if (! is_array($filasIntegrante)) {
        $filasIntegrante = [];
        foreach (($data->proveedor_integrantes ?? []) as $integrante) {
            $filasIntegrante[] = [
                'nombre' => $integrante->nombre,
                'cuit' => $integrante->cuit,
                'porcentaje' => $integrante->porcentaje,
                'inscripto' => $integrante->inscripto,
            ];
        }
    }
    $mostrarIntegrantes = old('condicionganancia', $data->condicionganancia ?? '') === 'C';
@endphp

<div id="proveedor-integrantes" class="mt-3" style="{{ $mostrarIntegrantes ? '' : 'display:none' }}">
    <div class="card card-outline card-info">
        <div class="card-header">
            <h3 class="card-title">Integrantes del condominio</h3>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2">
                La retención de Ganancias se practica a cada persona, con su porcentaje y su propio mínimo no sujeto.
                Ingresos brutos, IVA y SUSS siguen a nombre del proveedor.
            </p>
            @if ($errors->has('integrantes'))
                <div class="alert alert-danger py-2">{{ $errors->first('integrantes') }}</div>
            @endif
            @if (! $soloLectura)
                <input type="hidden" name="integrantes_presentes" value="1">
            @endif
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-2">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Nombre</th>
                            <th style="width:180px;">CUIT</th>
                            <th style="width:110px;">%</th>
                            <th style="width:140px;">Ganancias</th>
                            @if (! $soloLectura)
                                <th style="width:70px;">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="tbody-proveedor-integrantes">
                        @foreach ($filasIntegrante as $idx => $fila)
                            <tr>
                                <td>
                                    <input type="text" name="integrantes[{{ $idx }}][nombre]" maxlength="60" class="form-control"
                                        value="{{ $fila['nombre'] ?? '' }}" {{ $soloLectura ? 'readonly' : '' }}>
                                </td>
                                <td>
                                    <input type="text" name="integrantes[{{ $idx }}][cuit]" maxlength="13" class="form-control integrante-cuit"
                                        value="{{ $fila['cuit'] ?? '' }}" {{ $soloLectura ? 'readonly' : '' }}
                                        @if (! $soloLectura)
                                            oninput="formatarCUIT(this)"
                                        @endif>
                                </td>
                                <td>
                                    <input type="text" name="integrantes[{{ $idx }}][porcentaje]" class="form-control text-right"
                                        value="{{ $fila['porcentaje'] ?? '' }}" {{ $soloLectura ? 'readonly' : '' }}>
                                </td>
                                <td>
                                    <select name="integrantes[{{ $idx }}][inscripto]" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                                        <option value="S" {{ ($fila['inscripto'] ?? 'S') !== 'N' ? 'selected' : '' }}>Inscripto</option>
                                        <option value="N" {{ ($fila['inscripto'] ?? '') === 'N' ? 'selected' : '' }}>No inscripto</option>
                                    </select>
                                </td>
                                @if (! $soloLectura)
                                    <td class="text-center">
                                        <button type="button" class="btn-accion-tabla quitar-integrante" title="Quitar">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (! $soloLectura)
                <button type="button" class="btn btn-outline-primary btn-sm" id="agrega-integrante">+ Agrega integrante</button>
            @endif
        </div>
    </div>
</div>

@if (! $soloLectura)
    <template id="template-proveedor-integrante">
        <tr>
            <td><input type="text" data-name="nombre" maxlength="60" class="form-control"></td>
            <td><input type="text" data-name="cuit" maxlength="13" class="form-control integrante-cuit" oninput="formatarCUIT(this)"></td>
            <td><input type="text" data-name="porcentaje" class="form-control text-right"></td>
            <td>
                <select data-name="inscripto" class="form-control">
                    <option value="S" selected>Inscripto</option>
                    <option value="N">No inscripto</option>
                </select>
            </td>
            <td class="text-center">
                <button type="button" class="btn-accion-tabla quitar-integrante" title="Quitar">
                    <i class="fa fa-times-circle text-danger"></i>
                </button>
            </td>
        </tr>
    </template>
    <script>
    (function () {
        var tbody = document.getElementById('tbody-proveedor-integrantes');
        var tpl = document.getElementById('template-proveedor-integrante');
        var box = document.getElementById('proveedor-integrantes');
        var condicion = document.getElementById('condicionganancia');

        function renumerar() {
            if (!tbody) return;
            Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr, idx) {
                tr.querySelectorAll('[data-name], [name^="integrantes["]').forEach(function (el) {
                    var campo = el.getAttribute('data-name');
                    if (!campo && el.name) {
                        var m = el.name.match(/\[([^\]]+)\]$/);
                        campo = m ? m[1] : '';
                    }
                    if (campo) {
                        el.name = 'integrantes[' + idx + '][' + campo + ']';
                    }
                });
            });
        }

        function mostrar() {
            if (!box || !condicion) return;
            box.style.display = condicion.value === 'C' ? '' : 'none';
        }

        document.getElementById('agrega-integrante')?.addEventListener('click', function () {
            if (!tpl || !tbody) return;
            tbody.appendChild(tpl.content.cloneNode(true));
            renumerar();
        });

        tbody?.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.quitar-integrante');
            if (!btn) return;
            var tr = btn.closest('tr');
            if (tr) tr.remove();
            renumerar();
        });

        condicion?.addEventListener('change', mostrar);
        renumerar();
        mostrar();
    })();
    </script>
@endif
