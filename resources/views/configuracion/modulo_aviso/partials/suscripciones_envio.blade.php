@php
    $filas = $filas ?? [];
    $titulo = $titulo ?? 'Suscripciones de envío';
    $ayuda = $ayuda ?? 'Horario y destinatarios se editan en el módulo origen. Acá podés pausar y abrir la ficha.';
    $mostrarFamilia = $mostrarFamilia ?? true;
@endphp
<div class="mb-3">
    @if ($titulo !== '')
        <h6 class="mb-1">{{ $titulo }}</h6>
    @endif
    @if ($ayuda !== '')
        <p class="small text-muted mb-2">{{ $ayuda }}</p>
    @endif
    @if (empty($filas))
        <div class="border rounded bg-white p-3 text-center text-muted">
            Sin suscripciones cargadas.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Estado</th>
                        @if ($mostrarFamilia)
                            <th>Origen</th>
                        @endif
                        <th>Nombre</th>
                        <th>Horario</th>
                        <th>Destinatarios</th>
                        <th>Último</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $s)
                        <tr @if (empty($s['activo'])) class="table-secondary" @endif>
                            <td>
                                @if (! empty($s['activo']))
                                    <span class="badge badge-success">Activa</span>
                                @else
                                    <span class="badge badge-secondary">Pausada</span>
                                @endif
                                @if (! empty($s['estado']))
                                    <span class="badge badge-light">{{ $s['estado'] }}</span>
                                @endif
                            </td>
                            @if ($mostrarFamilia)
                                <td><span class="badge badge-info">{{ $s['familia_label'] }}</span></td>
                            @endif
                            <td class="small"><strong>{{ $s['nombre'] }}</strong></td>
                            <td class="text-nowrap small">{{ $s['horario'] }}</td>
                            <td class="small">{{ $s['destinatarios'] }}</td>
                            <td class="text-nowrap small">{{ $s['ultimo'] }}</td>
                            <td class="text-right text-nowrap">
                                @if (function_exists('can') && can('actualizar-modulo-aviso', false))
                                    <button type="button" class="btn btn-outline-primary btn-sm js-editar-suscripcion-programador"
                                            data-familia="{{ $s['familia'] }}" data-id="{{ $s['id'] }}"
                                            title="Editar horario y destinatarios">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                @endif
                                @if (! empty($s['url']))
                                    <a href="{{ $s['url'] }}" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener"
                                       title="Abrir módulo">
                                        <i class="fa fa-external-link"></i>
                                    </a>
                                @endif
                                @if (function_exists('can') && can('actualizar-modulo-aviso', false))
                                    <form method="post" action="{{ route('toggle_suscripcion_hermana_modulo_aviso', [$s['familia'], $s['id']]) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-{{ ! empty($s['activo']) ? 'warning' : 'success' }} btn-sm"
                                                title="{{ ! empty($s['activo']) ? 'Pausar' : 'Reactivar' }}">
                                            <i class="fa fa-{{ ! empty($s['activo']) ? 'pause' : 'play' }}"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
