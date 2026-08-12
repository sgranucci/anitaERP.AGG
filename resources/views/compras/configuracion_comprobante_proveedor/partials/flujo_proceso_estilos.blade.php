<style>
.cp-flujo-proceso { --cp-oc: #1B4F72; --cp-com: #117A65; --cp-fac: #2471A3; --cp-far: #B9770E; --cp-line: #85929E; }
.cp-flujo-card {
    width: 100%;
    text-align: left;
    border: 2px solid #d5d8dc;
    border-radius: 10px;
    background: #fff;
    padding: 14px 16px 16px;
    transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    cursor: pointer;
    min-height: 100%;
}
.cp-flujo-card:hover {
    border-color: #85C1E9;
    box-shadow: 0 4px 14px rgba(36, 113, 163, .12);
}
.cp-flujo-card.is-selected {
    border-color: #2471A3;
    box-shadow: 0 0 0 3px rgba(133, 193, 233, .45);
    background: linear-gradient(180deg, #f4faff 0%, #ffffff 42%);
}
.cp-flujo-card:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(36, 113, 163, .35);
}
.cp-flujo-card__header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 14px;
}
.cp-flujo-card__radio {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid #85929E;
    margin-top: 3px;
    flex: 0 0 18px;
    position: relative;
    background: #fff;
}
.cp-flujo-card.is-selected .cp-flujo-card__radio {
    border-color: #2471A3;
}
.cp-flujo-card.is-selected .cp-flujo-card__radio::after {
    content: '';
    position: absolute;
    inset: 3px;
    border-radius: 50%;
    background: #2471A3;
}
.cp-flujo-card__subtitle {
    color: #5d6d7e;
    font-size: 12px;
    margin-top: 2px;
}
.cp-flujo-card__tag {
    margin-left: auto;
    white-space: nowrap;
}
.cp-flujo-diagrama {
    background: #f8f9f9;
    border: 1px solid #e5e7e9;
    border-radius: 8px;
    padding: 12px 10px 10px;
    margin-bottom: 12px;
}
.cp-flujo-track {
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    gap: 4px;
    flex-wrap: nowrap;
}
.cp-flujo-track--rama {
    margin-top: 4px;
}
.cp-flujo-nodo {
    flex: 1 1 0;
    min-width: 0;
    border-radius: 8px;
    padding: 8px 6px;
    text-align: center;
    color: #fff;
    box-shadow: inset 0 -2px 0 rgba(0,0,0,.08);
}
.cp-flujo-nodo--oc { background: var(--cp-oc); }
.cp-flujo-nodo--com { background: var(--cp-com); }
.cp-flujo-nodo--fac { background: var(--cp-fac); }
.cp-flujo-nodo--far { background: var(--cp-far); }
.cp-flujo-nodo--obligatorio {
    outline: 2px solid #f4d03f;
    outline-offset: 1px;
}
.cp-flujo-nodo--suave {
    opacity: .92;
    background-image: repeating-linear-gradient(
        -45deg,
        rgba(255,255,255,.08),
        rgba(255,255,255,.08) 4px,
        transparent 4px,
        transparent 8px
    );
}
.cp-flujo-nodo__code {
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
    opacity: .9;
}
.cp-flujo-nodo__label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.25;
    margin-top: 2px;
}
.cp-flujo-nodo__hint {
    display: block;
    font-size: 10px;
    opacity: .9;
    margin-top: 3px;
}
.cp-flujo-flecha {
    flex: 0 0 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.cp-flujo-flecha span {
    display: block;
    width: 100%;
    height: 2px;
    background: var(--cp-line);
    position: relative;
}
.cp-flujo-flecha span::after {
    content: '';
    position: absolute;
    right: -1px;
    top: -3px;
    border: 4px solid transparent;
    border-left-color: var(--cp-line);
}
.cp-flujo-flecha--dashed span {
    background: transparent;
    border-top: 2px dashed var(--cp-line);
    height: 0;
}
.cp-flujo-flecha--dashed span::after {
    top: -5px;
}
.cp-flujo-rama {
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px dashed #ccd1d1;
}
.cp-flujo-rama__label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #7f8c8d;
    margin-bottom: 6px;
    font-weight: 700;
}
.cp-flujo-card__bullets {
    padding-left: 18px;
    color: #34495e;
    font-size: 12px;
}
.cp-flujo-card__bullets li { margin-bottom: 2px; }
.cp-flujo-asiento-mini {
    margin-top: 10px;
    padding: 8px 10px;
    background: #fff;
    border: 1px dashed #d5d8dc;
    border-radius: 6px;
    font-size: 11px;
    color: #2c3e50;
    line-height: 1.45;
}
.cp-flujo-leyenda {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    margin-top: 4px;
    align-items: center;
}
.cp-flujo-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
    margin-right: 4px;
    vertical-align: middle;
}
.cp-flujo-dot--oc { background: var(--cp-oc); }
.cp-flujo-dot--com { background: var(--cp-com); }
.cp-flujo-dot--fac { background: var(--cp-fac); }
.cp-flujo-dot--far { background: var(--cp-far); }

@media (max-width: 575.98px) {
    .cp-flujo-track { flex-direction: column; align-items: stretch; }
    .cp-flujo-flecha {
        flex-basis: 14px;
        transform: rotate(90deg);
        margin: 2px auto;
        width: 18px;
    }
}
</style>
