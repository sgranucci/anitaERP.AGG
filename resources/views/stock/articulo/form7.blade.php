<div id="tab7" class="card form7 tab-content" style="display: none">
    <div class="card-body">
        <h5 class="mb-3"><i class="fa fa-history"></i> Historia</h5>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped" id="ordenventa-historia-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width: 15%;">Fecha</th>
                        <th>Estado</th>
                        <th>Usuario</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody id="tbody-ordenventa-historia-table" class="container-historia">
                </tbody>
            </table>
        </div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
