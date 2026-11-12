<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: movimiento bóveda PROPINA (Híbrida/Mesas/Pública),
 * unifica FCOL → FVAL y baja el concepto “no negociable / placas”.
 *
 * Híbrida no mueve billetaje (solo informativo RRHH).
 * Mesas y Pública sí ingresan billetes a bóveda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $this->agregarMueveBilletaje();
        $this->sembrarPropina();
        $this->unificarFichasColorEnValor();
        $this->normalizarFichasNoNegociable();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('tesoreria_crown_boveda_subtipo')) {
            DB::table('tesoreria_crown_boveda_subtipo')
                ->whereIn('codigo', ['HIB', 'MES', 'PUB'])
                ->whereIn('tipo_id', function ($q) {
                    $q->select('id')
                        ->from('tesoreria_crown_boveda_tipo')
                        ->where('abreviatura', 'PROP');
                })
                ->delete();
        }

        if (Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            DB::table('tesoreria_crown_boveda_tipo')
                ->where('abreviatura', 'PROP')
                ->where('codigo', 42)
                ->delete();
        }

        if (Schema::hasTable('tesoreria_crown_boveda_subtipo')
            && Schema::hasColumn('tesoreria_crown_boveda_subtipo', 'mueve_billetaje')) {
            Schema::table('tesoreria_crown_boveda_subtipo', function (Blueprint $table) {
                $table->dropColumn('mueve_billetaje');
            });
        }
    }

    private function agregarMueveBilletaje(): void
    {
        if (! Schema::hasTable('tesoreria_crown_boveda_subtipo')) {
            return;
        }
        if (Schema::hasColumn('tesoreria_crown_boveda_subtipo', 'mueve_billetaje')) {
            return;
        }
        Schema::table('tesoreria_crown_boveda_subtipo', function (Blueprint $table) {
            $table->boolean('mueve_billetaje')->default(true)->after('activo');
        });
    }

    private function sembrarPropina(): void
    {
        if (! Schema::hasTable('tesoreria_crown_boveda_tipo')
            || ! Schema::hasTable('tesoreria_crown_boveda_subtipo')) {
            return;
        }

        $ahora = now();
        $salas = DB::table('sala')->whereRaw('UPPER(codigo) <> ?', ['AC'])->pluck('id');
        $subtipos = [
            ['codigo' => 'HIB', 'nombre' => 'Híbrida', 'mueve_billetaje' => false],
            ['codigo' => 'MES', 'nombre' => 'Mesas', 'mueve_billetaje' => true],
            ['codigo' => 'PUB', 'nombre' => 'Pública', 'mueve_billetaje' => true],
        ];

        foreach ($salas as $salaId) {
            $tipoId = DB::table('tesoreria_crown_boveda_tipo')
                ->where('sala_id', $salaId)
                ->where('abreviatura', 'PROP')
                ->value('id');

            if (! $tipoId) {
                $tipoId = DB::table('tesoreria_crown_boveda_tipo')->insertGetId([
                    'sala_id' => $salaId,
                    'codigo' => 42,
                    'abreviatura' => 'PROP',
                    'nombre' => 'PROPINA',
                    'signo' => '0',
                    'operacion' => '0',
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            } else {
                DB::table('tesoreria_crown_boveda_tipo')->where('id', $tipoId)->update([
                    'nombre' => 'PROPINA',
                    'signo' => '0',
                    'operacion' => '0',
                    'activo' => true,
                    'updated_at' => $ahora,
                ]);
            }

            foreach ($subtipos as $fila) {
                $existe = DB::table('tesoreria_crown_boveda_subtipo')
                    ->where('tipo_id', $tipoId)
                    ->where('codigo', $fila['codigo'])
                    ->exists();
                if ($existe) {
                    DB::table('tesoreria_crown_boveda_subtipo')
                        ->where('tipo_id', $tipoId)
                        ->where('codigo', $fila['codigo'])
                        ->update([
                            'nombre' => $fila['nombre'],
                            'mueve_billetaje' => $fila['mueve_billetaje'],
                            'activo' => true,
                            'updated_at' => $ahora,
                        ]);

                    continue;
                }
                DB::table('tesoreria_crown_boveda_subtipo')->insert([
                    'sala_id' => $salaId,
                    'tipo_id' => $tipoId,
                    'codigo' => $fila['codigo'],
                    'nombre' => $fila['nombre'],
                    'activo' => true,
                    'mueve_billetaje' => $fila['mueve_billetaje'],
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    private function unificarFichasColorEnValor(): void
    {
        if (! Schema::hasTable('tesoreria_crown_tipo_valor')
            || ! Schema::hasTable('tesoreria_crown_valor')) {
            return;
        }

        $ahora = now();
        $fcolIds = DB::table('tesoreria_crown_tipo_valor')
            ->where('codigo', 'FCOL')
            ->pluck('id', 'sala_id');

        foreach ($fcolIds as $salaId => $fcolId) {
            $fvalId = DB::table('tesoreria_crown_tipo_valor')
                ->where('sala_id', $salaId)
                ->where('codigo', 'FVAL')
                ->value('id');
            if (! $fvalId) {
                continue;
            }

            $valores = DB::table('tesoreria_crown_valor')
                ->where('tipo_valor_id', $fcolId)
                ->get(['id', 'serie', 'descripcion']);

            foreach ($valores as $valor) {
                $serie = strtoupper(trim((string) $valor->serie));
                $destino = DB::table('tesoreria_crown_valor')
                    ->where('tipo_valor_id', $fvalId)
                    ->where('serie', $serie)
                    ->value('id');
                if ($destino) {
                    continue;
                }
                $desc = trim((string) $valor->descripcion);
                $desc = preg_replace('/^NO\s+NEGOCIABLE\s+/iu', '', $desc) ?? $desc;
                DB::table('tesoreria_crown_valor')->where('id', $valor->id)->update([
                    'tipo_valor_id' => $fvalId,
                    'descripcion' => $desc !== '' ? $desc : $valor->descripcion,
                    'updated_at' => $ahora,
                ]);
            }

            DB::table('tesoreria_crown_tipo_valor')->where('id', $fcolId)->update([
                'nombre' => 'Fichas (histórico FCOL)',
                'activo' => false,
                'controla_stock' => false,
                'updated_at' => $ahora,
            ]);
        }

        DB::table('tesoreria_crown_tipo_valor')
            ->where('codigo', 'FVAL')
            ->update([
                'nombre' => 'Fichas de valor',
                'controla_stock' => true,
                'activo' => true,
                'updated_at' => $ahora,
            ]);
    }

    private function normalizarFichasNoNegociable(): void
    {
        if (! Schema::hasTable('tesoreria_crown_ficha')) {
            return;
        }
        if (! Schema::hasColumn('tesoreria_crown_ficha', 'ficha_canje')) {
            return;
        }

        $ahora = now();
        $filas = DB::table('tesoreria_crown_ficha')
            ->where('ficha_canje', true)
            ->get(['id', 'desc_color']);

        foreach ($filas as $fila) {
            $desc = trim((string) ($fila->desc_color ?? ''));
            $desc = preg_replace('/^NO\s+NEGOCIABLE\s+/iu', '', $desc) ?? $desc;
            DB::table('tesoreria_crown_ficha')->where('id', $fila->id)->update([
                'ficha_canje' => false,
                'desc_color' => $desc,
                'updated_at' => $ahora,
            ]);
        }
    }
};
