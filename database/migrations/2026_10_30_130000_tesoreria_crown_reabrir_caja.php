<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reapertura de una caja ya cerrada (Anita a-asigcaja -b, F12).
 * El permiso no se asigna a ningún rol: lo tilda Sistemas en Permiso - Rol.
 * La columna ayuda vale en todas las instalaciones; el permiso y las columnas, solo Crown.
 */
return new class extends Migration
{
    private const PERMISO = 'reabrir-tesoreria-crown-caja';

    public function up(): void
    {
        if (Schema::hasTable('permiso') && ! Schema::hasColumn('permiso', 'ayuda')) {
            Schema::table('permiso', function (Blueprint $table) {
                $table->text('ayuda')->nullable()->after('slug');
            });
        }

        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_cierre')) {
            return;
        }

        if (! Schema::hasColumn('tesoreria_crown_cierre', 'reabierta_at')) {
            Schema::table('tesoreria_crown_cierre', function (Blueprint $table) {
                $table->dateTime('reabierta_at')->nullable()->after('auditor_at');
                $table->foreignId('reabierta_usuario_id')->nullable()->after('reabierta_at')->constrained('usuario');
                $table->string('reabierta_motivo', 500)->nullable()->after('reabierta_usuario_id');
            });
        }

        $tieneIndicePlano = false;
        foreach (Schema::getIndexes('tesoreria_crown_cierre') as $indice) {
            if (! ($indice['unique'] ?? false) && ($indice['columns'] ?? []) === ['asignacion_id']) {
                $tieneIndicePlano = true;
            }
        }
        if (! $tieneIndicePlano) {
            Schema::table('tesoreria_crown_cierre', function (Blueprint $table) {
                $table->index('asignacion_id', 'tc_cierre_asignacion_idx');
            });
        }
        foreach (Schema::getIndexes('tesoreria_crown_cierre') as $indice) {
            if (($indice['unique'] ?? false) && ($indice['columns'] ?? []) === ['asignacion_id']) {
                Schema::table('tesoreria_crown_cierre', function (Blueprint $table) use ($indice) {
                    $table->dropUnique($indice['name']);
                });
            }
        }

        $ahora = now();
        $id = DB::table('permiso')->where('slug', self::PERMISO)->value('id');
        if (! $id) {
            DB::table('permiso')->insert([
                'nombre' => 'Reabrir caja cerrada de tesorería Crown',
                'slug' => self::PERMISO,
                'ayuda' => self::ayudas()[self::PERMISO],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        foreach (self::ayudas() as $slug => $ayuda) {
            DB::table('permiso')->where('slug', $slug)->update([
                'ayuda' => $ayuda,
                'updated_at' => $ahora,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (EntornoEmpresaSupport::esCrown() && Schema::hasTable('tesoreria_crown_cierre')) {
            $permisoId = DB::table('permiso')->where('slug', self::PERMISO)->value('id');
            if ($permisoId) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }

            if (Schema::hasColumn('tesoreria_crown_cierre', 'reabierta_usuario_id')) {
                Schema::table('tesoreria_crown_cierre', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('reabierta_usuario_id');
                });
            }
            if (Schema::hasColumn('tesoreria_crown_cierre', 'reabierta_motivo')) {
                Schema::table('tesoreria_crown_cierre', function (Blueprint $table) {
                    $table->dropColumn('reabierta_motivo');
                });
            }
            if (Schema::hasColumn('tesoreria_crown_cierre', 'reabierta_at')) {
                Schema::table('tesoreria_crown_cierre', function (Blueprint $table) {
                    $table->dropColumn('reabierta_at');
                });
            }

            $duplicada = DB::table('tesoreria_crown_cierre')
                ->select('asignacion_id')
                ->groupBy('asignacion_id')
                ->havingRaw('COUNT(*) > 1')
                ->exists();
            $yaUnica = false;
            foreach (Schema::getIndexes('tesoreria_crown_cierre') as $indice) {
                if (($indice['unique'] ?? false) && ($indice['columns'] ?? []) === ['asignacion_id']) {
                    $yaUnica = true;
                }
            }
            if (! $duplicada && ! $yaUnica) {
                Schema::table('tesoreria_crown_cierre', function (Blueprint $table) {
                    $table->unique('asignacion_id', 'tc_cierre_asignacion_uq');
                });
            }
            if (! $duplicada) {
                foreach (Schema::getIndexes('tesoreria_crown_cierre') as $indice) {
                    if (! ($indice['unique'] ?? false) && ($indice['columns'] ?? []) === ['asignacion_id']) {
                        Schema::table('tesoreria_crown_cierre', function (Blueprint $table) use ($indice) {
                            $table->dropIndex($indice['name']);
                        });
                    }
                }
            }

            SuitecrmPermiso::flushCachePermisos();
        }

        if (Schema::hasColumn('permiso', 'ayuda')) {
            Schema::table('permiso', function (Blueprint $table) {
                $table->dropColumn('ayuda');
            });
        }
    }

    /**
     * Texto que ve Sistemas en Permiso - Rol. No se asigna reabrir a ningún rol.
     *
     * @return array<string, string>
     */
    private static function ayudas(): array
    {
        return [
            self::PERMISO => 'Permiso especial, aparte de tesorería. No viene asignado a ningún rol: se tilda solo en el rol de quien pueda corregir una caja ya cerrada. En el cierre, Reabrir caja pide usuario y clave de alguien que tenga este permiso, y un motivo. No borra el cierre: lo deja en la historia y la caja vuelve a operativa para revertir o cargar otra habilitación o rendición, y cerrarla de nuevo. Si la jornada estaba cerrada, también vuelve a abierta, salvo que la sala ya tenga otro día abierto. El rol administrador entra igual, porque ese rol no consulta los permisos.',
            'listar-tesoreria-crown-jornada' => 'Ver el listado de jornadas y el día de la sala: cajeros, habilitaciones, rendiciones y cierres. No abre la jornada, no asigna cajero, no cierra y no reabre.',
            'crear-tesoreria-crown-jornada' => 'Abrir la jornada del día y asignar un cajero a una caja. No cierra la jornada, no quita una asignación y no reabre una caja.',
            'editar-tesoreria-crown-jornada' => 'Cerrar la jornada cuando cada caja con movimientos tiene un cierre terminado (conforme, revisada o cierre de auditoría), y quitar una asignación que todavía no tiene comprobantes. No reabre una caja ni la jornada.',
            'listar-tesoreria-crown-movimiento' => 'Ver una habilitación o una rendición, incluso si la caja ya está cerrada (queda en consulta). No carga, no firma, no revierte y no cierra la caja.',
            'crear-tesoreria-crown-movimiento' => 'Cargar una habilitación o una rendición y editar el borrador, con la caja y la jornada abiertas. No firma, no marca no conforme, no revierte y no cierra la caja.',
            'editar-tesoreria-crown-movimiento' => 'Firmar por tesorería, marcar no conforme y revertir un comprobante conforme mientras la caja sigue abierta. También cierra la caja, la marca revisada, la manda a auditoría y firma el cierre de auditoría. No reabre una caja ya cerrada: eso es el permiso de reabrir.',
            'listar-tesoreria-crown-boveda' => 'Ver los movimientos y el saldo de la bóveda en la jornada. No carga, no confirma y no anula.',
            'crear-tesoreria-crown-boveda' => 'Cargar un movimiento de bóveda en borrador. El borrador no mueve el saldo. No confirma y no anula.',
            'editar-tesoreria-crown-boveda' => 'Confirmar un movimiento de bóveda (entra al saldo) y anularlo con la jornada abierta. Con la jornada cerrada el movimiento queda en consulta.',
            'listar-tesoreria-crown-mesa' => 'Ver el piso de mesas del día y el detalle de un movimiento. No carga, no confirma y no anula.',
            'crear-tesoreria-crown-mesa' => 'Cargar un movimiento de mesa en borrador (habilitación, reposición, crédito, estado final, drop o propina). El borrador no entra al stock.',
            'editar-tesoreria-crown-mesa' => 'Confirmar un movimiento de mesa y anularlo. Al confirmar guarda la vigencia de la banca de esa fecha.',
            'listar-tesoreria-crown-valor' => 'Ver fichas, billetes y demás valores de la sala. No los crea ni los modifica.',
            'crear-tesoreria-crown-valor' => 'Dar de alta un valor (ficha, billete u otro) de la sala.',
            'editar-tesoreria-crown-valor' => 'Abrir la ficha de un valor para modificarlo.',
            'actualizar-tesoreria-crown-valor' => 'Grabar los cambios de un valor.',
            'eliminar-tesoreria-crown-valor' => 'Borrar un valor de la sala.',
            'listar-tesoreria-crown-deposito' => 'Ver cajas, bóveda, mesas y demás depósitos de la sala. No los crea ni los modifica.',
            'crear-tesoreria-crown-deposito' => 'Dar de alta un depósito de la sala.',
            'editar-tesoreria-crown-deposito' => 'Abrir la ficha de un depósito para modificarlo.',
            'actualizar-tesoreria-crown-deposito' => 'Grabar los cambios de un depósito.',
            'eliminar-tesoreria-crown-deposito' => 'Borrar un depósito de la sala.',
            'listar-tesoreria-crown-cotizacion' => 'Ver las cotizaciones de la jornada. El cierre de caja las usa para el dólar y el euro. No las carga.',
            'crear-tesoreria-crown-cotizacion' => 'Cargar la cotización de una jornada.',
            'editar-tesoreria-crown-cotizacion' => 'Abrir la cotización de una jornada para modificarla.',
            'actualizar-tesoreria-crown-cotizacion' => 'Grabar la cotización de una jornada.',
            'eliminar-tesoreria-crown-cotizacion' => 'Quedó creado con el módulo y la pantalla de cotización no lo usa. Borrar una cotización no sale de acá.',
            'usar-pos-gastronomia-crown' => 'Entrar al punto de venta de gastronomía Crown.',
            'usar-kds-gastronomia-crown' => 'Entrar a la pantalla de cocina (KDS) de gastronomía Crown.',
            'listar-cierre-x-gastronomia-crown' => 'Ver el cierre X por mozo de gastronomía Crown. No arma la rendición.',
            'crear-cierre-x-gastronomia-crown' => 'Armar la rendición del cierre X por mozo de gastronomía Crown.',
            'listar-plano-mesa-gastronomia-crown' => 'Ver los planos de mesas de gastronomía Crown.',
            'crear-plano-mesa-gastronomia-crown' => 'Dar de alta un plano de mesas de gastronomía Crown.',
            'editar-plano-mesa-gastronomia-crown' => 'Abrir un plano de mesas para modificarlo.',
            'actualizar-plano-mesa-gastronomia-crown' => 'Grabar los cambios de un plano de mesas.',
            'eliminar-plano-mesa-gastronomia-crown' => 'Borrar un plano de mesas de gastronomía Crown.',
            'generar-solicitud-retiro-crown' => 'Generar el archivo de solicitudes de retiro para Interbanking y Banco Francés, a partir del CSV de cuentas a pagar. No registra el pago ni mueve la caja.',
        ];
    }
};
