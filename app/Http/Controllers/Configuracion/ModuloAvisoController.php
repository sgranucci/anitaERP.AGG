<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionModuloAvisoTipo;
use App\Models\Configuracion\ModuloAvisoDestinatario;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ModuloAvisoController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private CentrocostoRepositoryInterface $centrocostoRepository,
        private UsuarioRepositoryInterface $usuarioRepository,
    ) {
    }

    public function index()
    {
        can('listar-modulo-aviso');

        $tipos = ModuloAvisoTipo::query()
            ->withCount(['destinatarios as destinatarios_activos_count' => function ($q) {
                $q->where('activo', true);
            }])
            ->orderBy('modulo')
            ->orderBy('nombre')
            ->get()
            ->groupBy('modulo');

        return view('configuracion.modulo_aviso.index', compact('tipos'));
    }

    public function editar(int $id)
    {
        can('editar-modulo-aviso');

        $tipo = ModuloAvisoTipo::with(['destinatarios.usuarios', 'destinatarios.empresas', 'destinatarios.centrocostos'])
            ->findOrFail($id);

        return view('configuracion.modulo_aviso.editar', [
            'tipo' => $tipo,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'centrocosto_query' => $this->centrocostoRepository->all(),
            'usuario_query' => $this->usuarioRepository->listadoOperativoParaSelector(
                null,
                null,
                ['id', 'nombre', 'email', 'usuario'],
                true
            ),
            'placeholders_ayuda' => $this->placeholdersAyuda($tipo->modulo, $tipo->codigo),
        ]);
    }

    public function actualizar(ValidacionModuloAvisoTipo $request, int $id)
    {
        can('actualizar-modulo-aviso');

        $tipo = ModuloAvisoTipo::findOrFail($id);

        DB::transaction(function () use ($request, $tipo) {
            $tipo->update([
                'activo' => $request->boolean('activo'),
                'mail_asunto' => $request->input('mail_asunto'),
                'mail_texto' => $request->input('mail_texto'),
                'mail_remitente' => $request->input('mail_remitente') ?: null,
                'adjuntar_pdf' => $request->boolean('adjuntar_pdf'),
                'incluir_link_consulta' => $request->boolean('incluir_link_consulta'),
            ]);

            $this->sincronizarDestinatarios($tipo, $request->input('destinatarios', []));
        });

        return redirect('configuracion/modulo-aviso')
            ->with('mensaje', 'Configuración de aviso actualizada.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function sincronizarDestinatarios(ModuloAvisoTipo $tipo, array $filas): void
    {
        $idsConservados = [];

        foreach ($filas as $fila) {
            $email = trim((string) ($fila['email'] ?? ''));
            $usuarioId = ! empty($fila['usuario_id']) ? (int) $fila['usuario_id'] : null;
            if ($email === '' && ! $usuarioId) {
                continue;
            }

            $payload = [
                'modulo_aviso_tipo_id' => $tipo->id,
                'email' => $email !== '' ? $email : null,
                'usuario_id' => $usuarioId,
                'empresa_id' => ! empty($fila['empresa_id']) ? (int) $fila['empresa_id'] : null,
                'centrocosto_id' => ! empty($fila['centrocosto_id']) ? (int) $fila['centrocosto_id'] : null,
                'activo' => ! empty($fila['activo']),
            ];

            $destId = ! empty($fila['id']) ? (int) $fila['id'] : 0;
            if ($destId > 0) {
                $dest = ModuloAvisoDestinatario::where('modulo_aviso_tipo_id', $tipo->id)->find($destId);
                if ($dest) {
                    $dest->update($payload);
                    $idsConservados[] = $dest->id;

                    continue;
                }
            }

            $nuevo = ModuloAvisoDestinatario::create($payload);
            $idsConservados[] = $nuevo->id;
        }

        ModuloAvisoDestinatario::where('modulo_aviso_tipo_id', $tipo->id)
            ->whereNotIn('id', $idsConservados)
            ->delete();
    }

    /**
     * @return list<string>
     */
    private function placeholdersAyuda(string $modulo, string $codigo): array
    {
        $comunes = ['{link_consulta}'];

        if ($modulo === 'sala' && $codigo === 'requisicion_sala_creacion') {
            return array_merge($comunes, [
                '{numero}', '{solicitante}', '{empresa}', '{centro_costo}',
                '{fecha}', '{estado}', '{deposito}', '{zona_sala}', '{prioridad}',
            ]);
        }

        if ($modulo === 'stock' && str_starts_with($codigo, 'prestamo_')) {
            return array_merge($comunes, [
                '{codigo}', '{numero}', '{solicitante}', '{deposito_origen}', '{deposito_destino}',
                '{fecha_prestamo}', '{fecha_devolucion}', '{estado}',
            ]);
        }

        if ($modulo === 'ventas' && $codigo === 'pedido_produccion_alarma') {
            return array_merge($comunes, [
                '{numero}', '{cliente}', '{vendedor}', '{fecha}', '{fecha_entrega}',
                '{estado}', '{usuario}', '{articulos_alarma}',
            ]);
        }

        if ($modulo === 'stock' && str_starts_with($codigo, 'recepcion_proveedor_')) {
            return array_merge($comunes, [
                '{numero_recepcion}', '{numero_oc}', '{proveedor}', '{fecha}', '{estado}', '{com_anita}',
                '{comentario_precio}', '{resumen_diferencias}', '{resumen_rechazos}',
                '{usuario_recepcion}', '{detalle_lineas}',
            ]);
        }

        if ($modulo === 'uif' && $codigo === 'cliente_alta') {
            return array_merge($comunes, [
                '{id}', '{nombre}', '{numerodocumento}', '{tipodocumento}',
                '{cuit}', '{usuario_alta}', '{fecha}',
            ]);
        }

        if ($modulo === 'ticket' && $codigo === 'alta_tecnologia') {
            return array_merge($comunes, [
                '{id}', '{numero}', '{titulo}', '{sala}', '{sector}',
                '{categoria}', '{subcategoria}', '{usuario}', '{cargado_por}',
                '{comentario}', '{fecha}', '{estado}', '{area}',
            ]);
        }

        if ($modulo === 'ticket' && $codigo === 'asignacion_tecnico') {
            return array_merge($comunes, [
                '{id}', '{numero}', '{titulo}', '{tarea}', '{tecnico}', '{turno}',
                '{asignado_por}', '{usuario}', '{sala}', '{sector}',
                '{categoria}', '{subcategoria}', '{comentario}', '{fecha}',
                '{fechaprogramacion}', '{estado}', '{area}',
            ]);
        }

        if ($modulo === 'compras' && $codigo === 'ordencompra_alertas_abiertas') {
            return array_merge($comunes, [
                '{fecha}', '{dias_sin_recepcion}',
                '{cantidad_sin_recepcion}', '{oc_sin_recepcion}',
                '{cantidad_parciales}', '{oc_parcialmente_recibidas}',
                '{cantidad_vencidas}', '{oc_vencidas}',
                '{cantidad_saldos_pendientes}', '{saldos_pendientes}',
            ]);
        }

        if ($modulo === 'compras' && $codigo === 'ordencompra_contrato_sin_com') {
            return array_merge($comunes, [
                '{id}', '{numero}', '{empresa}', '{proveedor}', '{centrocosto}',
                '{detalle}', '{tratamiento}', '{imputacion}', '{cuenta_contable}',
                '{responsable}', '{vigencia_desde}', '{vigencia_hasta}',
                '{usuario_cambio}', '{fecha_cambio}',
            ]);
        }

        if ($modulo === 'compras' && $codigo === 'contrato_validacion_abono_pendiente') {
            return array_merge($comunes, [
                '{numero_oc}', '{proveedor}', '{detalle}', '{periodo}',
                '{origen_etiqueta}', '{origen_numero}', '{estado}',
            ]);
        }

        return array_merge($comunes, ['{numero}', '{solicitante}', '{empresa}', '{centro_costo}', '{fecha}']);
    }
}
