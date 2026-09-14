<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro de turnos Facturación Local (Mañana/Tarde/…) — sin SoftDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('turno_local')) {
            Schema::create('turno_local', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->string('nombre', 255);
                $table->string('codigo', 50)->nullable();
                $table->time('hora_desde')->nullable();
                $table->time('hora_hasta')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->foreign('empresa_id')->references('id')->on('empresa')->onDelete('restrict');
                $table->unique(['empresa_id', 'nombre']);
                $table->unique(['empresa_id', 'codigo']);
            });
        }

        if (Schema::hasTable('turno_operativo_local') && ! Schema::hasColumn('turno_operativo_local', 'turno_local_id')) {
            Schema::table('turno_operativo_local', function (Blueprint $table) {
                $table->unsignedBigInteger('turno_local_id')->nullable()->after('local_venta_id');
                $table->foreign('turno_local_id')->references('id')->on('turno_local')->onDelete('restrict');
            });
        }

        $this->seedTurnosDefault();
    }

    public function down(): void
    {
        if (Schema::hasTable('turno_operativo_local') && Schema::hasColumn('turno_operativo_local', 'turno_local_id')) {
            Schema::table('turno_operativo_local', function (Blueprint $table) {
                $table->dropForeign(['turno_local_id']);
                $table->dropColumn('turno_local_id');
            });
        }

        Schema::dropIfExists('turno_local');
    }

    private function seedTurnosDefault(): void
    {
        if (! Schema::hasTable('turno_local')) {
            return;
        }

        $empresaId = (int) (DB::table('local_venta')->orderBy('id')->value('empresa_id') ?? 0);
        if ($empresaId <= 0) {
            $empresaId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?? 0);
        }
        if ($empresaId <= 0) {
            return;
        }

        $defaults = [
            ['codigo' => 'M', 'nombre' => 'Mañana', 'hora_desde' => '08:00:00', 'hora_hasta' => '14:00:00', 'orden' => 1],
            ['codigo' => 'T', 'nombre' => 'Tarde', 'hora_desde' => '14:00:00', 'hora_hasta' => '20:00:00', 'orden' => 2],
            ['codigo' => 'N', 'nombre' => 'Noche', 'hora_desde' => '20:00:00', 'hora_hasta' => '02:00:00', 'orden' => 3],
        ];

        $now = now();
        foreach ($defaults as $row) {
            $exists = DB::table('turno_local')
                ->where('empresa_id', $empresaId)
                ->where(function ($q) use ($row) {
                    $q->where('codigo', $row['codigo'])->orWhere('nombre', $row['nombre']);
                })
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('turno_local')->insert([
                'empresa_id' => $empresaId,
                'nombre' => $row['nombre'],
                'codigo' => $row['codigo'],
                'hora_desde' => $row['hora_desde'],
                'hora_hasta' => $row['hora_hasta'],
                'orden' => $row['orden'],
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
