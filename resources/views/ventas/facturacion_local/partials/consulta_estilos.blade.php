<style>
    .fl-consulta-shell {
        --fl-ink: #0f2740;
        --fl-muted: #5d6d7e;
        --fl-accent: #1a6fa8;
        --fl-accent-soft: #d6eaf8;
        --fl-head: #85C1E9;
        --fl-ok: #14804a;
        --fl-warn: #b9770e;
        --fl-danger: #c0392b;
        --fl-surface: #f4f8fb;
        --fl-card: #ffffff;
        --fl-shadow: 0 10px 28px rgba(15, 39, 64, 0.08);
    }

    .fl-consulta-hero {
        background:
            radial-gradient(1200px 280px at 10% -40%, rgba(133, 193, 233, 0.55), transparent 60%),
            linear-gradient(135deg, #0f2740 0%, #1a5276 55%, #2471a3 100%);
        color: #fff;
        border-radius: 14px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1rem;
        box-shadow: var(--fl-shadow);
        position: relative;
        overflow: hidden;
    }
    .fl-consulta-hero::after {
        content: "";
        position: absolute;
        right: -40px;
        top: -50px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        pointer-events: none;
    }
    .fl-consulta-hero h2 {
        font-size: 1.55rem;
        font-weight: 700;
        margin: 0 0 0.25rem;
        letter-spacing: -0.02em;
    }
    .fl-consulta-hero p {
        margin: 0;
        opacity: 0.88;
        font-size: 0.95rem;
        max-width: 42rem;
    }
    .fl-consulta-hero-actions {
        position: relative;
        z-index: 1;
        margin-left: auto;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 0.35rem;
    }

    .fl-consulta-origen-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem 0.75rem;
        margin: 0 0 1rem;
        padding: 0.55rem 1.15rem 0.65rem;
        background: #f8fbfc;
        border: 1px solid #d5e4ef;
        border-top: 0;
        border-radius: 0 0 12px 12px;
        box-shadow: var(--fl-shadow);
        position: relative;
        z-index: 0;
    }
    .fl-consulta-bar {
        display: grid;
        grid-template-columns: minmax(220px, 320px) 1fr;
        gap: 1rem 1.25rem;
        background: var(--fl-card);
        border: 1px solid #d5e4ef;
        border-radius: 12px 12px 0 0;
        padding: 1rem 1.15rem 0.85rem;
        box-shadow: none;
        margin-bottom: 0;
    }
    .fl-consulta-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--fl-muted);
        margin-bottom: 0.35rem;
    }
    .fl-consulta-lista-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        margin-top: 0.5rem;
        padding: 0.28rem 0.65rem;
        border-radius: 999px;
        background: var(--fl-accent-soft);
        color: #1b4f72;
        font-size: 0.82rem;
        font-weight: 600;
        max-width: 100%;
    }
    .fl-consulta-lista-chip.is-empty {
        background: #f4f6f7;
        color: #7f8c8d;
        font-weight: 500;
    }
    .fl-consulta-lista-chip-txt {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .fl-consulta-hint {
        color: var(--fl-muted);
        font-size: 0.78rem;
    }
    .fl-consulta-hint kbd {
        background: #eef3f7;
        border: 1px solid #cfd8dc;
        border-radius: 4px;
        padding: 0 0.3rem;
        font-size: 0.72rem;
        color: #34495e;
    }
    .fl-consulta-lupa {
        width: 2.6rem;
        height: 2.6rem;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .fl-kpi-row {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.85rem;
        margin-bottom: 1rem;
    }
    .fl-kpi {
        background: var(--fl-card);
        border: 1px solid #d5e4ef;
        border-radius: 12px;
        padding: 1rem 1.1rem;
        box-shadow: var(--fl-shadow);
        min-height: 108px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        animation: flFadeUp 0.35s ease both;
    }
    .fl-kpi-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--fl-muted);
        font-weight: 700;
        margin-bottom: 0.25rem;
    }
    .fl-kpi-value {
        font-size: 1.85rem;
        font-weight: 800;
        color: var(--fl-ink);
        line-height: 1.15;
        letter-spacing: -0.03em;
        word-break: break-word;
    }
    .fl-kpi-value.is-precio { color: var(--fl-ok); }
    .fl-kpi-value.is-saldo-pos { color: var(--fl-accent); }
    .fl-kpi-value.is-saldo-neg { color: var(--fl-danger); }
    .fl-kpi-value.is-saldo-cero { color: #95a5a6; }
    .fl-kpi-sub {
        margin-top: 0.2rem;
        font-size: 0.85rem;
        color: var(--fl-muted);
    }
    .fl-kpi-articulo .fl-kpi-value {
        font-size: 1.15rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }

    .fl-panel {
        background: var(--fl-card);
        border: 1px solid #d5e4ef;
        border-radius: 12px;
        box-shadow: var(--fl-shadow);
        overflow: hidden;
        animation: flFadeUp 0.4s ease 0.05s both;
    }
    .fl-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        background: linear-gradient(180deg, #eaf4fb 0%, #dceef8 100%);
        border-bottom: 1px solid #c5dcec;
    }
    .fl-panel-head h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #1b4f72;
    }
    .fl-panel-body { padding: 0; }
    .fl-panel-body.has-pad { padding: 0.85rem 1rem; }

    .fl-consulta-tabla {
        font-size: 13px;
        margin-bottom: 0 !important;
    }
    .fl-consulta-tabla th,
    .fl-consulta-tabla td {
        white-space: nowrap;
        padding: 0.4rem 0.55rem;
        vertical-align: middle;
    }
    .fl-consulta-tabla thead th {
        background: #85C1E9 !important;
        color: #17202A !important;
        font-weight: 700;
        border-color: #6fb0d8 !important;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .fl-consulta-tabla .cant-cero { color: #adb5bd; }
    .fl-consulta-tabla .cant-neg { color: #c0392b; font-weight: 700; }
    .fl-consulta-tabla .cant-pos { color: #1a5276; font-weight: 600; }
    .fl-consulta-tabla .cant-hot {
        background: #e8f8f0;
        color: #14804a;
        font-weight: 700;
    }
    .fl-consulta-tabla tbody tr:hover { background: #f0f7fc; }
    .fl-consulta-tabla .fl-row-total {
        font-weight: 700;
        background: #f8fbfd;
    }
    .fl-consulta-tabla .lista-activa {
        background: #e8f6ef !important;
        font-weight: 700;
    }
    .fl-consulta-tabla .lista-activa td:first-child::before {
        content: "★ ";
        color: var(--fl-ok);
    }

    .fl-consulta-origen {
        padding: 0.55rem 1rem 0.75rem;
        color: var(--fl-muted);
        font-size: 0.8rem;
    }
    .fl-empty-state {
        border-radius: 12px;
        border: 1px dashed #b0c4d4;
        background: var(--fl-surface);
        padding: 2rem 1.25rem;
        text-align: center;
        color: var(--fl-muted);
    }
    .fl-empty-state i {
        font-size: 2rem;
        color: #85C1E9;
        margin-bottom: 0.5rem;
        display: block;
    }
    .fl-empty-state.is-warn {
        border-color: #f0c36d;
        background: #fff8e8;
        color: #7d6608;
    }
    .fl-empty-state.is-error {
        border-color: #f5b7b1;
        background: #fdedec;
        color: #922b21;
    }

    .fl-matriz-wrap {
        max-height: min(62vh, 640px);
        overflow: auto;
    }

    @keyframes flFadeUp {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 991.98px) {
        .fl-consulta-bar { grid-template-columns: 1fr; }
        .fl-kpi-row { grid-template-columns: 1fr; }
    }
</style>
