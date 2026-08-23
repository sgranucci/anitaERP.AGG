@include('includes.ventas.modalconsultatransporte')
@include('includes.configuracion.modalconsultaprovincia')

<div class="modal fade" id="programa-consulta-empresa-modal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Empresas</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group row mb-2">
                    <label class="col-form-label pr-2">Buscar:</label>
                    <div class="col">
                        <input type="text" id="programa-consulta-empresa-texto" class="form-control" autocomplete="off">
                    </div>
                </div>
                <table class="table table-sm table-striped table-bordered table-hover mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>C&oacute;digo</th>
                            <th>Nombre</th>
                            <th class="width80"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($empresas as $empresa)
                            <tr class="programa-empresa-fila"
                                data-id="{{ $empresa->id }}"
                                data-codigo="{{ $empresa->codigo }}"
                                data-nombre="{{ $empresa->nombre }}">
                                <td>{{ $empresa->codigo }}</td>
                                <td>{{ $empresa->nombre }}</td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm programa-elige-empresa">Elegir</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
            </div>
        </div>
    </div>
</div>
