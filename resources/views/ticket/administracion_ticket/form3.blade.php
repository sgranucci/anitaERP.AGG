<div class="card form3" style="display: none">
    <h3>Historia</h3>
    <div class="card-body">
        <table class="table" id="ticket-articulo-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Fecha</th>
                    <th>Estado</th>
                    <th>Usuario</th=>
                    <th>Observación</th>
                </tr>
            </thead>
            <tbody id="tbody-ticket-historia-table" class="container-historia">
            </tbody>
        </table>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />

