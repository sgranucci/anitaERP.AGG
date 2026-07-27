<style>
    .portal-nav-modulos {
        border-bottom: 2px solid #d6eaf8;
        margin-bottom: 1rem;
    }
    .portal-nav-modulos .nav-link {
        color: #1B4F72;
        font-weight: 500;
        border: none;
        border-radius: 0;
        padding: .65rem 1.1rem;
    }
    .portal-nav-modulos .nav-link.active {
        color: #1B4F72;
        background: #D6EAF8;
        border-top: 3px solid #2471A3;
        font-weight: 600;
    }
    .portal-nav-modulos .nav-link:hover:not(.active) {
        background: #f4f9fc;
    }
    .portal-kpi {
        border: 1px solid #d5e8f5;
        border-radius: 4px;
        background: #f8fbfd;
        padding: .85rem 1rem;
        height: 100%;
    }
    .portal-kpi .kpi-label {
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #5d6d7e;
        margin-bottom: .25rem;
    }
    .portal-kpi .kpi-valor {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1B4F72;
        line-height: 1.2;
    }
    .portal-kpi .kpi-ayuda {
        font-size: .75rem;
        color: #7f8c8d;
        margin-top: .2rem;
    }
    .portal-estado-confirmada { background: #d5f5e3; color: #145a32; }
    .portal-estado-revertida { background: #fdebd0; color: #7e5109; }
    .portal-estado-baja { background: #fadbd8; color: #78281f; }
    .portal-empty {
        text-align: center;
        padding: 2.5rem 1rem;
        color: #7f8c8d;
    }
    .portal-empty i {
        font-size: 2.2rem;
        color: #85C1E9;
        margin-bottom: .75rem;
        display: block;
    }
</style>
