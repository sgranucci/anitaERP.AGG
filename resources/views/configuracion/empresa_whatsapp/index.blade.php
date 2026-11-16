@extends("theme.$theme.layout")
@section('titulo')
WhatsApp por empresa
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-whatsapp"></i> Cuentas WhatsApp por empresa</h3>
            </div>
            <div class="card-body p-0">
                <p class="px-3 pt-3 mb-2 text-muted small">
                    El envío WhatsApp de avisos queda activo solo si la empresa tiene una cuenta asociada, activa y con token.
                    Sin cuenta = el canal no opera (mail y campanita siguen).
                </p>
                <table class="table table-striped table-sm mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Empresa</th>
                            <th>Estado</th>
                            <th>Phone number ID</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($empresas as $empresa)
                            @php $cuenta = $cuentas->get($empresa->id); @endphp
                            <tr>
                                <td>{{ $empresa->nombre }}</td>
                                <td>
                                    @if ($cuenta && $cuenta->estaOperativa())
                                        <span class="badge badge-success">Operativa</span>
                                    @elseif ($cuenta && $cuenta->activo)
                                        <span class="badge badge-warning">Activa incompleta</span>
                                    @elseif ($cuenta)
                                        <span class="badge badge-secondary">Inactiva</span>
                                    @else
                                        <span class="badge badge-light">Sin cuenta</span>
                                    @endif
                                </td>
                                <td><code>{{ $cuenta->phone_number_id ?? '—' }}</code></td>
                                <td class="text-right">
                                    @if (can('editar-empresa-whatsapp', false))
                                        <a href="{{ route('editar_empresa_whatsapp', $empresa->id) }}" class="btn btn-sm btn-primary">
                                            <i class="fa fa-edit"></i> Configurar
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Sin empresas asignadas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
