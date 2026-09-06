<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>

@php
    $modoActual = old('aprobacion_modo', $data->aprobacion_modo ?? 'default');
    $arbolActual = old('arbolaprobacion_id', $data->arbolaprobacion_id ?? '');
@endphp

<div class="form-group row">
    <label for="aprobacion_modo" class="col-lg-3 col-form-label">Aprobación alta (si circuito activo)</label>
    <div class="col-lg-8">
        <select name="aprobacion_modo" id="aprobacion_modo" class="form-control">
            <option value="default" @selected($modoActual === 'default')>Default — árbol Artículos genérico (p.ej. Contaduría)</option>
            <option value="auto" @selected($modoActual === 'auto')>Auto-aprobar (nace ACTIVO)</option>
            <option value="arbol" @selected($modoActual === 'arbol')>Árbol específico</option>
        </select>
        <small class="form-text text-muted">Solo aplica con ARTICULO_APROBACION_ALTA=true. Sin eso, el alta sigue como siempre.</small>
    </div>
</div>

<div class="form-group row">
    <label for="arbolaprobacion_id" class="col-lg-3 col-form-label">Árbol específico</label>
    <div class="col-lg-8">
        <select name="arbolaprobacion_id" id="arbolaprobacion_id" class="form-control">
            <option value="">—</option>
            @foreach (($arbolesArticulo ?? []) as $arbol)
                <option value="{{ $arbol->id }}" @selected((string) $arbolActual === (string) $arbol->id)>
                    {{ $arbol->nombre }} ({{ $arbol->estado }})
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Obligatorio si el modo es “Árbol específico”. Crear árboles tipo Artículos en Configuración → Árbol de aprobación.</small>
    </div>
</div>
