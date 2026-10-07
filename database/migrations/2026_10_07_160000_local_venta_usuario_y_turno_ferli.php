<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asigna usuarios y turnos de caja a cada local.
 * El esquema es genérico. La carga inicial (Caballito / Lugano) es solo Ferli.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('local_venta_usuario')) {
            Schema::create('local_venta_usuario', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('local_venta_id');
                $table->unsignedBigInteger('usuario_id');
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();

                $table->unique(['local_venta_id', 'usuario_id'], 'local_venta_usuario_unico');
                $table->foreign('local_venta_id', 'fk_local_venta_usuario_local')
                    ->references('id')->on('local_venta')->cascadeOnDelete();
                $table->foreign('usuario_id', 'fk_local_venta_usuario_usuario')
                    ->references('id')->on('usuario')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('local_venta_turno')) {
            Schema::create('local_venta_turno', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('local_venta_id');
                $table->unsignedBigInteger('turno_local_id');
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();

                $table->unique(['local_venta_id', 'turno_local_id'], 'local_venta_turno_unico');
                $table->foreign('local_venta_id', 'fk_local_venta_turno_local')
                    ->references('id')->on('local_venta')->cascadeOnDelete();
                $table->foreign('turno_local_id', 'fk_local_venta_turno_turno')
                    ->references('id')->on('turno_local')->cascadeOnDelete();
            });
        }

        $this->seedFerli();
    }

    public function down(): void
    {
        Schema::dropIfExists('local_venta_turno');
        Schema::dropIfExists('local_venta_usuario');
    }

    /**
     * Caballito queda solo en su local y su turno.
     * Lugano opera el local y las tiendas que ya abre todos los días, con el turno LUGANO.
     */
    private function seedFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('local_venta') || ! Schema::hasTable('usuario') || ! Schema::hasTable('turno_local')) {
            return;
        }

        $localesPorNombre = [];
        foreach (DB::table('local_venta')->get(['id', 'nombre']) as $local) {
            $localesPorNombre[$this->clave((string) $local->nombre)] = (int) $local->id;
        }

        $turnosPorNombre = [];
        foreach (DB::table('turno_local')->get(['id', 'nombre']) as $turno) {
            $turnosPorNombre[$this->clave((string) $turno->nombre)] = (int) $turno->id;
        }

        $this->asignarUsuarios($localesPorNombre, [
            'Caballito' => ['Caballito'],
            'Lugano' => ['Lugano', 'Ferli Tienda Online', 'Boaonda Tienda Online'],
        ]);

        $this->asignarTurnos($localesPorNombre, $turnosPorNombre, [
            'Caballito' => ['CABALLITO'],
            'Lugano' => ['LUGANO'],
            'Ferli Tienda Online' => ['LUGANO'],
            'Boaonda Tienda Online' => ['LUGANO'],
        ]);
    }

    /**
     * @param  array<string, int>  $localesPorNombre
     * @param  array<string, list<string>>  $mapa  usuario login => nombres de local
     */
    private function asignarUsuarios(array $localesPorNombre, array $mapa): void
    {
        foreach ($mapa as $login => $nombresLocal) {
            $usuarioId = (int) (DB::table('usuario')->where('usuario', $login)->value('id') ?? 0);
            if ($usuarioId <= 0) {
                continue;
            }
            $orden = 0;
            foreach ($nombresLocal as $nombre) {
                $localId = $localesPorNombre[$this->clave($nombre)] ?? 0;
                if ($localId <= 0) {
                    continue;
                }
                $existe = DB::table('local_venta_usuario')
                    ->where('local_venta_id', $localId)
                    ->where('usuario_id', $usuarioId)
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('local_venta_usuario')->insert([
                    'local_venta_id' => $localId,
                    'usuario_id' => $usuarioId,
                    'orden' => $orden,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $orden++;
            }
        }
    }

    /**
     * @param  array<string, int>  $localesPorNombre
     * @param  array<string, int>  $turnosPorNombre
     * @param  array<string, list<string>>  $mapa  nombre de local => nombres de turno
     */
    private function asignarTurnos(array $localesPorNombre, array $turnosPorNombre, array $mapa): void
    {
        foreach ($mapa as $nombreLocal => $nombresTurno) {
            $localId = $localesPorNombre[$this->clave($nombreLocal)] ?? 0;
            if ($localId <= 0) {
                continue;
            }
            $orden = 0;
            foreach ($nombresTurno as $nombreTurno) {
                $turnoId = $turnosPorNombre[$this->clave($nombreTurno)] ?? 0;
                if ($turnoId <= 0) {
                    continue;
                }
                $existe = DB::table('local_venta_turno')
                    ->where('local_venta_id', $localId)
                    ->where('turno_local_id', $turnoId)
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('local_venta_turno')->insert([
                    'local_venta_id' => $localId,
                    'turno_local_id' => $turnoId,
                    'orden' => $orden,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $orden++;
            }
        }
    }

    private function clave(string $nombre): string
    {
        return mb_strtoupper(trim($nombre));
    }
};
