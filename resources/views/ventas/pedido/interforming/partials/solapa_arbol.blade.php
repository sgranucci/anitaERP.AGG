<div id="pedido-if-arbol-panel">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <h5 class="mb-0">
            <i class="fa fa-sitemap"></i> Historia del árbol de aprobación
        </h5>
        <div>
            @include('ventas.pedido.interforming.partials.badge_aprobacion', ['pedido' => $pedido])
        </div>
    </div>

    @if (empty($pedido->id))
        <p class="text-muted mb-0">
            Guarde el pedido primero. Al grabar se dispara el árbol y aquí verá el historial de firmas.
        </p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered" id="pedido-if-arbol-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width: 14%;">Fecha envío</th>
                        <th>Usuario envío</th>
                        <th style="width: 8%;">Nivel</th>
                        <th style="width: 12%;">Estado</th>
                        <th style="width: 14%;">Fecha proceso</th>
                        <th>Usuario destinatario</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody id="tbody-pedido-if-arbol" class="container-arbol-pedido-if">
                    <tr>
                        <td colspan="7" class="text-center text-muted">Abrí esta solapa para cargar el historial…</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</div>
