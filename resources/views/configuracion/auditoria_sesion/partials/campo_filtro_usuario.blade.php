@php
    $campoId = $campoId ?? 'usuario_id';
    $campoCodigoId = $campoCodigoId ?? 'usuario_codigo';
    $campoNombreId = $campoNombreId ?? 'nombreusuario';
    $label = $label ?? 'Usuario';
    $colClass = $colClass ?? 'col-md-3';
    $usuarioIdVal = $usuarioIdVal ?? '';
    $usuarioCodigoVal = $usuarioCodigoVal ?? '';
    $usuarioNombreVal = $usuarioNombreVal ?? '';
    $omitirFiltroEmpresa = $omitirFiltroEmpresa ?? true;
@endphp
<div class="form-group {{ $colClass }} tm-usuario-campo">
    <label for="{{ $campoCodigoId }}">{{ $label }}</label>
    <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
        <input type="hidden" name="usuario_id" id="{{ $campoId }}" class="usuario_id"
               value="{{ $usuarioIdVal }}">
        <input type="text"
               id="{{ $campoCodigoId }}"
               class="usuario_codigo_arbol form-control"
               style="flex: 0 0 110px; width: 110px;"
               value="{{ $usuarioCodigoVal }}"
               placeholder="Código"
               title="Código o ID; Enter/Tab resuelve; F1 abre consulta"
               autocomplete="off"
               autocapitalize="off"
               spellcheck="false">
        <button type="button"
                class="btn-accion-tabla consultausuario tooltipsC flex-shrink-0"
                title="Consultar usuario (F1)"
                data-ptrusuario_id="#{{ $campoId }}"
                data-ptrnombre="#{{ $campoNombreId }}"
                data-ptrusuario_codigo="#{{ $campoCodigoId }}"
                @if ($omitirFiltroEmpresa)
                    data-omitir_filtro_empresa="1"
                @endif
        >
            <i class="fa fa-search text-primary"></i>
        </button>
        <input type="text"
               id="{{ $campoNombreId }}"
               class="nombreusuario form-control"
               style="flex: 1 1 auto; min-width: 0;"
               value="{{ $usuarioNombreVal }}"
               placeholder="Todos los usuarios"
               readonly
               autocomplete="off">
    </div>
</div>
