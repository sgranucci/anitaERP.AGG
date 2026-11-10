<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa la ayuda de permisos Crown del escritorio de jornada y tablas,
 * y asigna los grupos del anexo II a quien ya puede listar anexos
 * (hoy el selector del II quedaba vacío para auxiliares/ops).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ANEXO2 = [
        'listar-tesoreria-crown-anexo2-mesas',
        'listar-tesoreria-crown-anexo2-maquinas',
        'listar-tesoreria-crown-anexo2-gastronomia',
        'listar-tesoreria-crown-anexo2-todo',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('permiso')) {
            return;
        }

        $ahora = now();
        $menuJornada = DB::table('menu')->where('url', 'caja/tesoreria-crown/jornada')->value('id');
        $menuAnexo = DB::table('menu')->where('url', 'caja/tesoreria-crown/anexo')->value('id');
        $menuFlash = DB::table('menu')->where('url', 'caja/flash-crown')->value('id')
            ?: DB::table('menu')->where('url', 'like', '%flash-crown%')->value('id');
        $menuCajero = DB::table('menu')->where('url', 'caja/tesoreria-crown/cajero')->value('id');
        $menuTipo = DB::table('menu')->where('url', 'caja/tesoreria-crown/boveda/tipos')->value('id');
        $menuSubtipo = DB::table('menu')->where('url', 'caja/tesoreria-crown/boveda/subtipos')->value('id');

        foreach ($this->ayudas() as $slug => $ayuda) {
            $fila = DB::table('permiso')->where('slug', $slug)->first(['id', 'menu_id']);
            if ($fila === null) {
                continue;
            }
            $update = [
                'ayuda' => $ayuda,
                'updated_at' => $ahora,
            ];
            if (empty($fila->menu_id)) {
                $menuId = match (true) {
                    str_starts_with($slug, 'listar-tesoreria-crown-anexo2-') => $menuAnexo,
                    in_array($slug, ['auditar-tesoreria-crown-caja', 'reabrir-tesoreria-crown-caja', 'ajustar-tesoreria-crown-caja'], true) => $menuJornada,
                    str_contains($slug, 'flash-crown') => $menuFlash,
                    str_contains($slug, 'tesoreria-crown-cajero') => $menuCajero,
                    str_contains($slug, 'boveda-tipo') => $menuTipo,
                    str_contains($slug, 'boveda-subtipo') => $menuSubtipo,
                    default => null,
                };
                if ($menuId) {
                    $update['menu_id'] = $menuId;
                }
            }
            DB::table('permiso')->where('id', $fila->id)->update($update);
        }

        $this->asignarAnexo2AQuienesListanAnexo();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('permiso')) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::ANEXO2)->pluck('id');
        if ($permisoIds->isEmpty()) {
            return;
        }

        $rolIds = DB::table('permiso_rol as pr')
            ->join('permiso as p', 'p.id', '=', 'pr.permiso_id')
            ->where('p.slug', 'listar-tesoreria-crown-anexo')
            ->pluck('pr.rol_id')
            ->unique()
            ->all();
        if ($rolIds !== []) {
            DB::table('permiso_rol')
                ->whereIn('permiso_id', $permisoIds)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    private function asignarAnexo2AQuienesListanAnexo(): void
    {
        $anexoListarId = DB::table('permiso')->where('slug', 'listar-tesoreria-crown-anexo')->value('id');
        if (! $anexoListarId) {
            return;
        }
        $rolIds = DB::table('permiso_rol')
            ->where('permiso_id', $anexoListarId)
            ->pluck('rol_id');
        $permisoIds = DB::table('permiso')->whereIn('slug', self::ANEXO2)->pluck('id', 'slug');
        foreach ($rolIds as $rolId) {
            foreach ($permisoIds as $permisoId) {
                $existe = DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /**
     * Texto que ve Sistemas en Permiso - Rol (?).
     * Qué habilita + qué no, para que un auxiliar no lleve de más.
     *
     * @return array<string, string>
     */
    private function ayudas(): array
    {
        return [
            'listar-tesoreria-crown-jornada' => 'Ver el listado de jornadas y el escritorio del día. La solapa Cajeros (habilitaciones, rendiciones y cierres) entra con este permiso. Mesas, Bóveda, Slots, Bingo, Stock de fichas, Kardex y Anexos piden cada uno su permiso de listar. No abre la jornada, no asigna cajero, no cierra el día y no reabre cajas.',
            'crear-tesoreria-crown-jornada' => 'Abrir la jornada del día y asignar un cajero a una caja (Abrir caja). No cierra la jornada, no quita una asignación con movimientos y no reabre una caja.',
            'editar-tesoreria-crown-jornada' => 'Cerrar la jornada cuando cada caja con movimientos tiene un cierre terminado (conforme, revisada o cierre de auditoría), quitar una asignación que todavía no tiene comprobantes y guardar datos del anexo (clientes). Si quedan cajas pendientes, el escritorio muestra el panel «Resolver para cerrar la jornada». No reabre una caja ni la jornada.',

            'listar-tesoreria-crown-movimiento' => 'Ver una habilitación o una rendición, incluso si la caja ya está cerrada (queda en consulta). No carga, no firma, no revierte y no cierra la caja.',
            'crear-tesoreria-crown-movimiento' => 'Cargar una habilitación o una rendición y editar el borrador, con la caja y la jornada abiertas. No firma, no marca no conforme, no revierte y no cierra la caja.',
            'editar-tesoreria-crown-movimiento' => 'Firmar por tesorería, marcar no conforme y revertir un comprobante conforme mientras la caja sigue abierta. También cierra la caja, la marca revisada y la manda a auditoría. En el panel «Resolver para cerrar la jornada» muestra el formulario de cierre de auditorías; la firma del auditor pide además auditar-tesoreria-crown-caja. No reabre una caja ya cerrada: eso es el permiso de reabrir.',

            'auditar-tesoreria-crown-caja' => 'Permiso especial de auditoría, aparte de tesorería operativa. Se tilda en el rol de quien firma cierres de auditoría (por ejemplo Enc-contaduría). Firma el cierre de auditoría de una caja o, desde el panel «Resolver para cerrar la jornada», cierra varias auditorías pendientes con una sola firma: no puede ser quien cerró la caja ni quien la mandó a auditoría. También resuelve comprobantes no conformes y, junto con reabrir, permite reabrir una caja que está en auditoría. El administrador entra igual.',
            'reabrir-tesoreria-crown-caja' => 'Permiso especial, aparte de tesorería. No viene asignado a ningún rol: se tilda solo en quien pueda corregir una caja ya cerrada. En el cierre, Reabrir caja pide usuario, clave y motivo. No borra el cierre: lo deja en la historia y la caja vuelve a operativa. Si la jornada estaba cerrada, también vuelve a abierta, salvo que la sala ya tenga otro día abierto. El administrador entra igual.',
            'ajustar-tesoreria-crown-caja' => 'Permiso especial, aparte de tesorería. No viene asignado a ningún rol: se tilda en quien audita una caja ya cerrada. Ajuste de caja graba un comprobante nuevo (motivo e importe) con doble firma, sin reescribir habilitación, rendición ni cierre original, y sin reabrir la caja. Si la diferencia vigente supera la tolerancia, el caso vuelve a auditoría. El administrador entra igual.',

            'listar-tesoreria-crown-mesa' => 'Ver en el escritorio la solapa Mesas: piso del día y detalle de un movimiento. No carga, no confirma y no anula.',
            'crear-tesoreria-crown-mesa' => 'Cargar un movimiento de mesa en borrador (habilitación, reposición, crédito, estado final, drop o propina). El borrador no entra al stock.',
            'editar-tesoreria-crown-mesa' => 'Confirmar un movimiento de mesa y anularlo o revertirlo. Al confirmar guarda la vigencia de la banca de esa fecha.',
            'eliminar-borrador-tesoreria-crown-mesa' => 'Permiso especial. Se tilda en quien pueda descartar un borrador de mesa sin reverso. No se asigna a ningún rol al instalarlo.',

            'listar-tesoreria-crown-boveda' => 'Ver en el escritorio la solapa Bóveda: movimientos y saldo del día. No carga, no confirma y no anula.',
            'crear-tesoreria-crown-boveda' => 'Cargar un movimiento de bóveda en borrador. El borrador no mueve el saldo. No confirma y no anula.',
            'editar-tesoreria-crown-boveda' => 'Confirmar un movimiento de bóveda (entra al saldo) y anularlo con la jornada abierta. Con la jornada cerrada el movimiento queda en consulta.',
            'imprimir-tesoreria-crown-boveda' => 'Permiso especial de bóveda. Se tilda en quien puede emitir el comprobante PDF del movimiento. No confirma ni anula.',
            'listar-tesoreria-crown-boveda-arqueo' => 'Ver los arqueos de cambio de turno de la bóveda.',
            'crear-tesoreria-crown-boveda-arqueo' => 'Generar un arqueo de cambio de turno con Inicial, Ingresos, Egresos y Actual.',
            'firmar-tesoreria-crown-boveda-arqueo' => 'Firmar entrega o recepción del arqueo de turno (doble firma de tesorería).',

            'listar-tesoreria-crown-slot' => 'Ver en el escritorio la solapa Slots: drop de máquinas y recuento de stacker del día, con billetaje. No carga ni modifica.',
            'crear-tesoreria-crown-slot' => 'Abrir un recuento nuevo (contadora, manual o corrección) en la jornada abierta y guardar el borrador con el billetaje.',
            'editar-tesoreria-crown-slot' => 'Modificar el borrador y confirmar el recuento con firma de tesorería. El drop importado de Anita queda solo en consulta.',
            'eliminar-borrador-tesoreria-crown-slot' => 'Borrar un borrador de recuento sin reverso. No se asigna a ningún rol al instalarlo.',

            'listar-tesoreria-crown-bingo' => 'Ver en el escritorio la solapa Bingo: cartones, premios, RSE y resultado del día.',
            'crear-tesoreria-crown-bingo' => 'Cargar el movimiento de bingo de un día.',
            'editar-tesoreria-crown-bingo' => 'Abrir el bingo de un día para modificarlo.',
            'actualizar-tesoreria-crown-bingo' => 'Grabar el bingo del día. Los anexos I y II toman el resultado.',
            'eliminar-tesoreria-crown-bingo' => 'Borrar el movimiento de bingo de un día.',

            'listar-tesoreria-crown-poder-publico' => 'Ver en el escritorio Stock de fichas (poder del público): reserva, conversión e importe del día (entra al anexo). No carga ni modifica.',
            'listar-tesoreria-crown-kardex-valores' => 'Ver en el escritorio el kardex unificado de valores entre bóveda, pre-depósito y caja chica.',
            'listar-tesoreria-crown-anexo' => 'Ver y exportar en el escritorio los anexos del día (I, resumen de cajeros, III, VII, VIII). El anexo II pide además un permiso de grupo (mesas, máquinas, gastronomía o todo). No graba en Anita.',
            'listar-tesoreria-crown-anexo2-mesas' => 'En el anexo II del escritorio, ver y exportar solo el bloque de mesas (créditos, drop, resultado por juego). No graba en Anita. Se puede tildar aparte del resto de grupos.',
            'listar-tesoreria-crown-anexo2-maquinas' => 'En el anexo II del escritorio, ver y exportar solo el bloque de slots (ventas cashless, validadoras, pagos, resultado). No graba en Anita. Se puede tildar aparte del resto de grupos.',
            'listar-tesoreria-crown-anexo2-gastronomia' => 'En el anexo II del escritorio, ver y exportar solo el bloque gastro/hotel (ventas Z, rendiciones, tarjetas). No graba en Anita. Se puede tildar aparte del resto de grupos.',
            'listar-tesoreria-crown-anexo2-todo' => 'En el anexo II del escritorio, ver y exportar el informe completo (mesas + máquinas + gastronomía). No graba en Anita. Se puede tildar aparte de los grupos parciales.',

            'listar-tesoreria-crown-aviso' => 'Ver la bandeja de avisos de auditoría (diferencias, no conformes, reversas, reaperturas, firmas, diferencias contra Anita) y exportarla. Revisar o descartar un aviso pide además auditar-tesoreria-crown-caja.',

            'listar-tesoreria-crown-cajero' => 'Ver el ABM de cajeros Crown (código, legajo, sala, activo). No da de alta ni modifica. La asignación del día se hace en la jornada con crear-tesoreria-crown-jornada.',
            'crear-tesoreria-crown-cajero' => 'Dar de alta un cajero Crown en el ABM de tablas. No asigna el cajero a una caja del día: eso es Abrir caja en la jornada.',
            'editar-tesoreria-crown-cajero' => 'Abrir la ficha de un cajero Crown para modificarla.',
            'actualizar-tesoreria-crown-cajero' => 'Grabar los cambios de un cajero Crown.',
            'eliminar-tesoreria-crown-cajero' => 'Borrar un cajero Crown del ABM. No quita una asignación ya hecha en una jornada.',

            'listar-tesoreria-crown-boveda-tipo' => 'Ver el catálogo de tipos de movimiento de bóveda Crown. No crea ni modifica.',
            'crear-tesoreria-crown-boveda-tipo' => 'Dar de alta un tipo de movimiento de bóveda Crown.',
            'editar-tesoreria-crown-boveda-tipo' => 'Abrir un tipo de movimiento de bóveda para modificarlo.',
            'actualizar-tesoreria-crown-boveda-tipo' => 'Grabar los cambios de un tipo de movimiento de bóveda.',
            'eliminar-tesoreria-crown-boveda-tipo' => 'Borrar un tipo de movimiento de bóveda Crown.',

            'listar-tesoreria-crown-boveda-subtipo' => 'Ver el catálogo de subtipos de movimiento de bóveda Crown. No crea ni modifica.',
            'crear-tesoreria-crown-boveda-subtipo' => 'Dar de alta un subtipo de movimiento de bóveda Crown.',
            'editar-tesoreria-crown-boveda-subtipo' => 'Abrir un subtipo de movimiento de bóveda para modificarlo.',
            'actualizar-tesoreria-crown-boveda-subtipo' => 'Grabar los cambios de un subtipo de movimiento de bóveda.',
            'eliminar-tesoreria-crown-boveda-subtipo' => 'Borrar un subtipo de movimiento de bóveda Crown.',

            'listar-flash-crown' => 'Ver el listado Flash Crown del día (tablero de resultados). No recalcula ni valida.',
            'crear-flash-crown' => 'Alta de un flash Crown cuando el circuito lo pide. En la práctica el día se arma con Recalcular.',
            'editar-flash-crown' => 'Recalcular el flash del día desde anexos y resultado online. No valida ni congela el reporte.',
            'actualizar-flash-crown' => 'Grabar cambios del flash Crown cuando hay pantalla de edición.',
            'eliminar-flash-crown' => 'Borrar un flash Crown del listado.',
            'exportar-flash-crown' => 'Exportar el Flash Crown (PDF/Excel/CSV o reportes programados).',
            'validar-flash-crown' => 'Validar o quitar la validación del flash del día: congela o libera el reporte para envíos.',
        ];
    }
};
