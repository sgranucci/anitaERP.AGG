<div class="card form3" style="display: none">
    <h3>Historia</h3>
    <div class="card-body">
        <table class="table" id="ordenventa-historia-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Fecha</th>
                    <th>Estado</th>
                    <th>Usuario</th=>
                    <th>Observación</th>
                </tr>
            </thead>
            <tbody id="tbody-ordenventa-historia-table" class="container-historia">
            </tbody>
        </table>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />

