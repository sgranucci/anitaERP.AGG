<div id="tab-avisos" class="tab-pane fade card form11" role="tabpanel">
    <div class="card-body">
        <input type="hidden" name="aviso_en_formulario" value="1">
        <p class="text-muted mb-2">
            Cada renglón queda asociado al cliente. El disparador indica en qué carga se usa.
            Podés mostrar un texto o ejecutar una acción. Si marcás sin vencimiento, el aviso sigue vigente hasta que lo borres o le cargues una fecha hasta.
            La fecha desde vacía significa que ya está vigente.
        </p>
        @php
            $disparadoresAviso = \App\Support\Ventas\ClienteAvisoSupport::disparadores();
            $modosAviso = \App\Support\Ventas\ClienteAvisoSupport::modos();
            $accionesAviso = \App\Support\Ventas\ClienteAvisoSupport::acciones();
            $avisosFilas = [];
            if (old('aviso_en_formulario')) {
                $disparadoresOld = old('aviso_disparadores', []);
                if (! is_array($disparadoresOld)) {
                    $disparadoresOld = [];
                }
                foreach ($disparadoresOld as $i => $disparadorOld) {
                    $sinVencimientoOld = (string) old('aviso_sin_vencimiento.'.$i) === '1';
                    $avisosFilas[] = [
                        'id' => old('aviso_ids.'.$i),
                        'creousuario_id' => old('aviso_creousuario_ids.'.$i, auth()->id()),
                        'disparador' => $disparadorOld,
                        'modo' => old('aviso_modos.'.$i, 'aviso'),
                        'texto' => old('aviso_textos.'.$i),
                        'accion' => old('aviso_acciones.'.$i),
                        'desdefecha' => substr((string) old('aviso_desdefechas.'.$i), 0, 10),
                        'hastafecha' => $sinVencimientoOld ? '' : substr((string) old('aviso_hastafechas.'.$i), 0, 10),
                        'sin_vencimiento' => $sinVencimientoOld,
                    ];
                }
            } else {
                $avisosCargados = isset($data) ? ($data->cliente_avisos ?? collect()) : collect();
                foreach ($avisosCargados as $aviso) {
                    $hasta = $aviso->hastafecha ? $aviso->hastafecha->format('Y-m-d') : '';
                    $avisosFilas[] = [
                        'id' => $aviso->id,
                        'creousuario_id' => $aviso->creousuario_id ?: auth()->id(),
                        'disparador' => $aviso->disparador,
                        'modo' => $aviso->modo,
                        'texto' => $aviso->texto,
                        'accion' => $aviso->accion,
                        'desdefecha' => $aviso->desdefecha ? $aviso->desdefecha->format('Y-m-d') : '',
                        'hastafecha' => $hasta,
                        'sin_vencimiento' => $hasta === '',
                    ];
                }
            }
        @endphp
        <div class="table-responsive">
            <table class="table table-sm table-bordered" id="cliente-aviso-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width: 16%;">Disparador</th>
                        <th style="width: 14%;">Qué hacer</th>
                        <th style="width: 28%;">Aviso</th>
                        <th style="width: 16%;">Acción</th>
                        <th style="width: 11%;">Desde</th>
                        <th style="width: 13%;">Hasta</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbody-tabla-cliente-aviso">
                    @foreach ($avisosFilas as $indiceAviso => $fila)
                        @include('ventas.cliente.partials.renglon_aviso', [
                            'fila' => $fila,
                            'checkId' => 'aviso-sin-vencimiento-'.$indiceAviso,
                            'disparadoresAviso' => $disparadoresAviso,
                            'modosAviso' => $modosAviso,
                            'accionesAviso' => $accionesAviso,
                        ])
                    @endforeach
                </tbody>
            </table>
        </div>
        <template id="template-renglon-cliente-aviso">
            @include('ventas.cliente.partials.renglon_aviso', [
                'fila' => [
                    'id' => '',
                    'creousuario_id' => auth()->id(),
                    'disparador' => '',
                    'modo' => 'aviso',
                    'texto' => '',
                    'accion' => '',
                    'desdefecha' => date('Y-m-d'),
                    'hastafecha' => '',
                    'sin_vencimiento' => true,
                ],
                'checkId' => 'aviso-sin-vencimiento-nuevo',
                'disparadoresAviso' => $disparadoresAviso,
                'modosAviso' => $modosAviso,
                'accionesAviso' => $accionesAviso,
            ])
        </template>
        <div class="row">
            <div class="col-md-12">
                <button type="button" id="agrega_renglon_cliente_aviso" class="pull-right btn btn-outline-primary btn-sm">
                    + Agregar aviso
                </button>
            </div>
        </div>
    </div>
</div>
