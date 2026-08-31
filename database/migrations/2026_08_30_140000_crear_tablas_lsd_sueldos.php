<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsd_concepto_afip_sueldos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 6)->unique();
            $table->string('tipo', 20);
            $table->string('descripcion', 150);
            $table->boolean('pide_cantidad')->default(false);
            $table->boolean('rango_libre')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('concepto_sueldos', function (Blueprint $table) {
            $table->string('codigo_lsd_empleador', 10)->nullable();
            $table->boolean('lsd_repetible')->default(true);
            $table->json('lsd_subsistemas')->nullable();
        });

        Schema::table('empleado_sueldos', function (Blueprint $table) {
            $table->string('actividad_sijp', 3)->nullable();
            $table->string('localidad_afip', 2)->nullable();
            $table->string('cuit_agencia_eventual', 13)->nullable();
            $table->boolean('lsd_legajo_principal')->default(true);
            $table->boolean('lsd_cct')->default(false);
            $table->boolean('lsd_scvo')->default(true);
        });

        Schema::create('lsd_empleado_revista_sueldos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedInteger('periodo')->nullable();
            $table->unsignedTinyInteger('nro')->default(1);
            $table->string('situacion', 2);
            $table->unsignedTinyInteger('dia_inicio')->default(1);
            $table->timestamps();
            $table->index(['empleado_id', 'periodo'], 'lsd_revista_emp_per_idx');
            $table->foreign('empleado_id', 'lsd_revista_emp_fk')
                ->references('id')->on('empleado_sueldos')->onDelete('cascade');
        });

        Schema::create('lsd_presentacion_sueldos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedInteger('periodo');
            $table->unsignedBigInteger('liquidacion_id')->nullable();
            $table->unsignedInteger('nro_liquidacion_afip');
            $table->string('identificacion', 2)->default('SJ');
            $table->string('tipo_liquidacion', 1)->nullable();
            $table->unsignedTinyInteger('dias_base')->default(30);
            $table->date('fecha_pago')->nullable();
            $table->date('fecha_rubrica')->nullable();
            $table->string('estado', 20)->default('generada');
            $table->boolean('es_rectificativa')->default(false);
            $table->unsignedBigInteger('presentacion_orig_id')->nullable();
            $table->unsignedInteger('cantidad_registros_04')->default(0);
            $table->unsignedInteger('cantidad_trabajadores')->default(0);
            $table->string('archivo_hash', 64)->nullable();
            $table->string('archivo_nombre', 120)->nullable();
            $table->unsignedInteger('archivo_bytes')->nullable();
            $table->json('validaciones_json')->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamp('generado_at')->nullable();
            $table->timestamp('presentado_at')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'periodo'], 'lsd_pres_emp_per_idx');
            $table->index(['liquidacion_id'], 'lsd_pres_liq_idx');
            $table->foreign('empresa_id', 'lsd_pres_emp_fk')
                ->references('id')->on('empresa')->onDelete('restrict');
            $table->foreign('liquidacion_id', 'lsd_pres_liq_fk')
                ->references('id')->on('liquidacion_sueldos')->onDelete('set null');
            $table->foreign('presentacion_orig_id', 'lsd_pres_orig_fk')
                ->references('id')->on('lsd_presentacion_sueldos')->onDelete('set null');
        });

        Schema::create('lsd_presentacion_registro_sueldos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('presentacion_id');
            $table->string('tipo_registro', 2);
            $table->unsignedInteger('nro_linea');
            $table->string('cuil', 11)->nullable();
            $table->text('contenido');
            $table->text('contenido_override')->nullable();
            $table->string('estado_linea', 12)->default('ok');
            $table->string('mensaje', 255)->nullable();
            $table->timestamps();
            $table->index(['presentacion_id', 'tipo_registro'], 'lsd_preg_pres_tipo_idx');
            $table->foreign('presentacion_id', 'lsd_preg_pres_fk')
                ->references('id')->on('lsd_presentacion_sueldos')->onDelete('cascade');
        });

        Schema::create('lsd_recibo_base_sueldos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recibo_id')->unique();
            $table->unsignedBigInteger('liquidacion_id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedSmallInteger('dias_tope')->default(0);
            $table->unsignedSmallInteger('dias_trabajados')->default(0);
            $table->unsignedSmallInteger('horas_trabajadas')->default(0);
            $table->decimal('rem_bruta', 15, 2)->default(0);
            $table->decimal('base_1', 15, 2)->default(0);
            $table->decimal('base_2', 15, 2)->default(0);
            $table->decimal('base_3', 15, 2)->default(0);
            $table->decimal('base_4', 15, 2)->default(0);
            $table->decimal('base_5', 15, 2)->default(0);
            $table->decimal('base_6', 15, 2)->default(0);
            $table->decimal('base_7', 15, 2)->default(0);
            $table->decimal('base_8', 15, 2)->default(0);
            $table->decimal('base_9', 15, 2)->default(0);
            $table->decimal('base_10', 15, 2)->default(0);
            $table->decimal('importe_detraer', 15, 2)->default(0);
            $table->string('situacion_1', 2)->nullable();
            $table->unsignedTinyInteger('dia_inicio_1')->nullable();
            $table->string('situacion_2', 2)->nullable();
            $table->unsignedTinyInteger('dia_inicio_2')->nullable();
            $table->string('situacion_3', 2)->nullable();
            $table->unsignedTinyInteger('dia_inicio_3')->nullable();
            $table->timestamps();
            $table->foreign('recibo_id', 'lsd_rbase_rec_fk')
                ->references('id')->on('liquidacion_recibo_sueldos')->onDelete('cascade');
            $table->foreign('liquidacion_id', 'lsd_rbase_liq_fk')
                ->references('id')->on('liquidacion_sueldos')->onDelete('cascade');
        });

        $this->sembrarCatalogo();
    }

    public function down(): void
    {
        Schema::dropIfExists('lsd_recibo_base_sueldos');
        Schema::dropIfExists('lsd_presentacion_registro_sueldos');
        Schema::dropIfExists('lsd_presentacion_sueldos');
        Schema::dropIfExists('lsd_empleado_revista_sueldos');

        Schema::table('empleado_sueldos', function (Blueprint $table) {
            $table->dropColumn([
                'actividad_sijp',
                'localidad_afip',
                'cuit_agencia_eventual',
                'lsd_legajo_principal',
                'lsd_cct',
                'lsd_scvo',
            ]);
        });

        Schema::table('concepto_sueldos', function (Blueprint $table) {
            $table->dropColumn(['codigo_lsd_empleador', 'lsd_repetible', 'lsd_subsistemas']);
        });

        Schema::dropIfExists('lsd_concepto_afip_sueldos');
    }

    private function sembrarCatalogo(): void
    {
        $ahora = now();
        $filas = [];
        foreach ($this->catalogoOficial() as $row) {
            $filas[] = [
                'codigo' => $row[0],
                'tipo' => $row[1],
                'descripcion' => $row[2],
                'pide_cantidad' => $row[3] ? 1 : 0,
                'rango_libre' => 0,
                'activo' => 1,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }
        foreach (array_chunk($filas, 80) as $chunk) {
            DB::table('lsd_concepto_afip_sueldos')->insert($chunk);
        }
    }

    /** @return list<array{0:string,1:string,2:string,3:bool}> */
    private function catalogoOficial(): array
    {
        $r = 'remunerativo';
        $n = 'no_remunerativo';
        $d = 'descuento';

        return [
            ['110000', $r, 'Sueldo', false],
            ['110001', $r, 'Preaviso', false],
            ['110002', $r, 'Remuneraciones en especie', false],
            ['110003', $r, 'Comida', false],
            ['110004', $r, 'Habitación', false],
            ['110005', $r, 'Licencias por estudio', false],
            ['110006', $r, 'Donación de sangre', false],
            ['110007', $r, 'Feriado', false],
            ['110008', $r, 'Prest. dineraria Ley 24577 (primeros 10d)', false],
            ['110009', $r, 'Prest. dineraria Ley 24577 (a cargo de ART)', false],
            ['120000', $r, 'Sueldo anual complementario', false],
            ['120001', $r, 'SAC 1er semestre', false],
            ['120002', $r, 'SAC 2do semestre', false],
            ['120003', $r, 'SAC proporcional', true],
            ['130000', $r, 'Horas extras', true],
            ['130001', $r, 'Horas extras al 50 %', true],
            ['130002', $r, 'Horas extras al 100 %', true],
            ['130003', $r, 'Horas extras al 200 %', true],
            ['140000', $r, 'Zona desfavorable', false],
            ['150000', $r, 'Adelanto vacacional', true],
            ['151000', $r, 'Plus vacacional', false],
            ['160000', $r, 'Adicionales', false],
            ['160001', $r, 'Adicional por antigüedad', false],
            ['160002', $r, 'Adicional por título', false],
            ['160003', $r, 'Adicional por tarea', false],
            ['160004', $r, 'Adicional por desarraigo', false],
            ['170000', $r, 'Gratificaciones y/o Premios', false],
            ['170001', $r, 'Premio por presentismo', false],
            ['170002', $r, 'Premio por producción', false],
            ['170003', $r, 'Comisiones', false],
            ['170004', $r, 'Accesorios', false],
            ['170005', $r, 'Viáticos sin comprobante', false],
            ['170006', $r, 'Propinas habituales no prohibidas', false],
            ['499999', $r, 'Redondeo (Remunerativo)', false],
            ['510000', $n, 'Asignaciones Familiares', false],
            ['510001', $n, 'Ayuda escolar', false],
            ['510002', $n, 'Asignación por hijo/hijo con discapacidad', false],
            ['510003', $n, 'Asignación por maternidad', false],
            ['510004', $n, 'Asignación por maternidad down', false],
            ['510005', $n, 'Asignación por matrimonio', false],
            ['510006', $n, 'Asignación por nacimiento / adopción', false],
            ['510007', $n, 'Asignación por prenatal', false],
            ['520000', $n, 'Beneficios sociales', false],
            ['520001', $n, 'Servicio de comedor', false],
            ['520002', $n, 'Gastos médicos', false],
            ['520003', $n, 'Provisión de ropa de trabajo', false],
            ['520004', $n, 'Guardería', false],
            ['520005', $n, 'Provisión de útiles escolares', false],
            ['520006', $n, 'Gastos de sepelio', false],
            ['520007', $n, 'Cursos de capacitación', false],
            ['520008', $n, 'Becas (art. 7 Ley 24.241)', false],
            ['520009', $n, 'Desempleo (art. 7 Ley 24.241)', false],
            ['520010', $n, 'Gratificación por cese laboral', false],
            ['520011', $n, 'Indemnización por extinción del contrato', false],
            ['520012', $n, 'Vacaciones no gozadas', false],
            ['520013', $n, 'Incapacidad permanente', false],
            ['520014', $n, 'Indemnización por despido', false],
            ['520015', $n, 'Indemnización sustitutiva del preaviso', false],
            ['520016', $n, 'Integración mes de despido', false],
            ['520017', $n, 'SAC sobre integración o preaviso', false],
            ['520018', $n, 'SAC sobre vacaciones no gozadas', false],
            ['530000', $n, 'Incrementos no remunerativos (con aportes OS)', false],
            ['540000', $n, 'Incrementos no remunerativos (con aportes y contrib. OS)', false],
            ['550000', $n, 'Importes no remunerativos especiales', false],
            ['799999', $n, 'Redondeo (No Remunerativo)', false],
            ['810000', $d, 'Sistema previsional', false],
            ['810001', $d, 'INSSJyP', false],
            ['810002', $d, 'Obra Social', false],
            ['810003', $d, 'Fondo Solidario de Redistribución (ex ANSSAL)', false],
            ['810004', $d, 'Cuota Sindical', false],
            ['810005', $d, 'Seguro de Vida', false],
            ['810006', $d, 'RENATEA (ex RENATRE)', false],
            ['810007', $d, 'Préstamos', false],
            ['810008', $d, 'Impuesto a las Ganancias', false],
            ['810009', $d, 'Obra Social – Adherentes', false],
            ['810010', $d, 'Fondo Solidario de Redistribución - Adherentes', false],
            ['820000', $d, 'Otros descuentos', false],
        ];
    }
};
