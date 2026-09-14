{{-- Ferli: explorador kardex por combinación + stock por depósito --}}
@once('anita-modal-kardex-combinacion')
<style>
#modalKardexCombinacion .modal-content {
    border: 0;
    border-radius: 1.1rem;
    overflow: hidden;
    box-shadow: 0 1.5rem 3.5rem rgba(15, 23, 42, .32);
}
#modalKardexCombinacion .kx-hero {
    background:
        radial-gradient(circle at 12% 20%, rgba(56, 189, 248, .35), transparent 42%),
        radial-gradient(circle at 88% 10%, rgba(167, 139, 250, .28), transparent 40%),
        linear-gradient(135deg, #0b1220 0%, #172554 55%, #1e293b 100%);
    color: #f8fafc;
    padding: 1.15rem 1.35rem;
}
#modalKardexCombinacion .kx-hero .close { color: #fff; opacity: .85; text-shadow: none; }
#modalKardexCombinacion .kx-sku {
    font-size: .75rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    opacity: .75;
}
#modalKardexCombinacion .kx-body { background: #f8fafc; min-height: 22rem; }
#modalKardexCombinacion .kx-comb-list {
    max-height: 26rem;
    overflow-y: auto;
    padding: .75rem;
}
#modalKardexCombinacion .kx-comb-card {
    border: 1px solid #e2e8f0;
    border-radius: .85rem;
    background: #fff;
    padding: .75rem .9rem;
    margin-bottom: .55rem;
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
#modalKardexCombinacion .kx-comb-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 .4rem 1rem rgba(15, 23, 42, .08);
    border-color: #93c5fd;
}
#modalKardexCombinacion .kx-comb-card.is-active {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
    background: linear-gradient(180deg, #eff6ff, #fff);
}
#modalKardexCombinacion .kx-dep-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(11.5rem, 1fr));
    gap: .65rem;
    padding: .85rem;
}
#modalKardexCombinacion .kx-dep-card {
    border-radius: .9rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    padding: .85rem;
    min-height: 6.2rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: border-color .15s ease, box-shadow .15s ease;
}
#modalKardexCombinacion .kx-dep-card:hover {
    border-color: #67e8f9;
    box-shadow: 0 .5rem 1.2rem rgba(8, 145, 178, .12);
}
#modalKardexCombinacion .kx-dep-saldo {
    font-size: 1.35rem;
    font-weight: 700;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}
#modalKardexCombinacion .kx-dep-saldo.is-zero { color: #94a3b8; }
#modalKardexCombinacion .kx-dep-saldo.is-neg { color: #dc2626; }
#modalKardexCombinacion .kx-empty {
    text-align: center;
    color: #64748b;
    padding: 2.5rem 1rem;
}
#modalKardexCombinacion .btn-kx-open {
    border-radius: .65rem;
    font-weight: 600;
}
</style>
<div class="modal fade" id="modalKardexCombinacion" tabindex="-1" role="dialog" aria-labelledby="modalKardexCombinacionTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="kx-hero d-flex justify-content-between align-items-start">
                <div>
                    <div class="kx-sku" id="modalKardexCombinacionSku">SKU</div>
                    <h5 class="mb-1" id="modalKardexCombinacionTitulo">Kardex por combinación</h5>
                    <div class="small opacity-75" id="modalKardexCombinacionDesc"></div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="kx-body">
                <div id="modalKardexCombinacionLoading" class="kx-empty d-none">
                    <i class="fa fa-spinner fa-spin fa-2x mb-2"></i>
                    <div>Cargando combinaciones y saldos…</div>
                </div>
                <div id="modalKardexCombinacionError" class="alert alert-danger m-3 d-none"></div>
                <div class="row no-gutters" id="modalKardexCombinacionPanel">
                    <div class="col-lg-4 border-right bg-white">
                        <div class="px-3 pt-3 pb-1 d-flex justify-content-between align-items-center">
                            <strong class="small text-uppercase text-muted">Combinaciones activas</strong>
                            <span class="badge badge-primary" id="modalKardexCombinacionCount">0</span>
                        </div>
                        <div class="kx-comb-list" id="modalKardexCombinacionList"></div>
                    </div>
                    <div class="col-lg-8">
                        <div class="px-3 pt-3 pb-1 d-flex flex-wrap justify-content-between align-items-center">
                            <div>
                                <strong id="modalKardexCombinacionSeleccion">Stock por depósito</strong>
                                <div class="small text-muted">Elegí un depósito para abrir el kardex filtrado.</div>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm btn-kx-open" id="modalKardexCombinacionTodos">
                                <i class="fa fa-list-alt"></i> Kardex todos
                            </button>
                        </div>
                        <div class="kx-dep-grid" id="modalKardexCombinacionDeps"></div>
                        <div class="kx-empty d-none" id="modalKardexCombinacionDepsEmpty">
                            Sin movimientos en depósitos para esta combinación.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <div class="mr-auto small text-muted">Total visible: <strong id="modalKardexCombinacionTotal">0</strong></div>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endonce
