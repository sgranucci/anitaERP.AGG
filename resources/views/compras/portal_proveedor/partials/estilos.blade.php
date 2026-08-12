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
    .portal-estado-oc-pendiente { background: #fdebd0; color: #7e5109; }
    .portal-estado-oc-aprobada { background: #d6eaf8; color: #1a5276; }
    .portal-estado-oc-cumplida { background: #d5f5e3; color: #145a32; }
    .portal-estado-oc-suspendida { background: #fadbd8; color: #78281f; }
    .portal-estado-oc-cerrada { background: #e5e8e8; color: #424949; }
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
    .portal-circuito {
        border: 1px solid #d5e8f5;
        border-radius: 4px;
        background: #f8fbfd;
        padding: 1rem 1.1rem;
    }
    .portal-circuito-steps {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem 0;
    }
    .portal-circuito-paso {
        position: relative;
        flex: 1 1 140px;
        min-width: 120px;
        padding-right: .5rem;
    }
    .portal-circuito-paso .paso-nodo {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: .45rem;
        border: 2px solid #aeb6bf;
        background: #fff;
        color: #7f8c8d;
        z-index: 1;
        position: relative;
    }
    .portal-circuito-paso.paso-completo .paso-nodo {
        background: #145a32;
        border-color: #145a32;
        color: #fff;
    }
    .portal-circuito-paso.paso-en-curso .paso-nodo {
        background: #2471A3;
        border-color: #2471A3;
        color: #fff;
    }
    .portal-circuito-paso .paso-linea {
        position: absolute;
        top: 16px;
        left: 34px;
        right: 0;
        height: 3px;
        background: #d5d8dc;
        z-index: 0;
    }
    .portal-circuito-paso.paso-completo .paso-linea {
        background: #27ae60;
    }
    .portal-circuito-paso.paso-en-curso .paso-linea {
        background: linear-gradient(90deg, #2471A3 0%, #d5d8dc 100%);
    }
    .portal-circuito-paso .paso-titulo {
        font-weight: 600;
        color: #1B4F72;
        font-size: .9rem;
    }
    .portal-circuito-paso .paso-detalle {
        font-size: .75rem;
        color: #5d6d7e;
        line-height: 1.3;
    }
    .portal-aviso-doc-vencido {
        border-left: 4px solid #c0392b;
    }
    .portal-preview-frame {
        width: 100%;
        min-height: 70vh;
        border: 0;
        background: #ecf0f1;
    }
</style>
