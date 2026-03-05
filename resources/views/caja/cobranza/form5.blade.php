<div class="card form5" style="display: none">
    <h3>Historia</h3>
    <div class="card-body">
        <table class="table" id="cobranza-historia-table">
            <thead>
                <tr>
                    <th style="width: 18%;">Fecha</th>
                    <th>Estado</th>
                    <th>Usuario</th>
                    <th>Observación</th>
                </tr>
            </thead>
            <tbody id="tbody-cobranza-historia-table" class="container-historia">
            </tbody>
        </table>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />

