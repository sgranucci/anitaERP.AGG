<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alícuotas de shared.impvar y parámetros de shared.impuesto de Crown.
 * Los ids 1–4 son los códigos Anita (el ERP los usa así al facturar).
 * El id 3 había quedado como placeholder «Exento lab» de la ND de cheque.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ahora = now();
        $this->alicuotas($ahora);
        $this->regimenes($ahora);
        $this->cuentasDebitoFiscal($ahora);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
    }

    private function alicuotas($ahora): void
    {
        if (! Schema::hasTable('impuesto')) {
            return;
        }

        $filas = [
            1 => ['Exento', 0, '1', '2'],
            2 => ['Gravado al 10.5%', 10.5, '2', '4'],
            3 => ['Gravado al 21%', 21, '3', '5'],
            4 => ['Gravado al 27%', 27, '4', '6'],
        ];

        if (DB::table('concepto_venta')->where('impuesto_id', 3)->where('codigo', 'like', 'NDR-%')->exists()) {
            if (! DB::table('impuesto')->where('id', 1)->exists()) {
                DB::table('impuesto')->insert([
                    'id' => 1,
                    'nombre' => 'Exento',
                    'valor' => 0,
                    'fechavigencia' => '2003-01-01',
                    'codigo' => '1',
                    'codigoarca' => '2',
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
            DB::table('concepto_venta')
                ->where('impuesto_id', 3)
                ->where('codigo', 'like', 'NDR-%')
                ->update(['impuesto_id' => 1, 'updated_at' => $ahora]);
        }

        foreach ($filas as $id => [$nombre, $valor, $codigo, $arca]) {
            $payload = [
                'nombre' => $nombre,
                'valor' => $valor,
                'fechavigencia' => '2003-01-01',
                'codigo' => $codigo,
                'codigoarca' => $arca,
                'updated_at' => $ahora,
            ];
            if (DB::table('impuesto')->where('id', $id)->exists()) {
                DB::table('impuesto')->where('id', $id)->update($payload);
                continue;
            }
            DB::table('impuesto')->insert($payload + [
                'id' => $id,
                'created_at' => $ahora,
            ]);
        }
    }

    private function regimenes($ahora): void
    {
        if (! Schema::hasTable('regimen_percepcion')) {
            return;
        }

        DB::table('regimen_percepcion')->where('codigo', 'PIVA')->update([
            'nombre' => 'Percepción IVA RI (RG 5329)',
            'habilitado' => false,
            'tasa' => 0,
            'minimo_base' => 0,
            'minimo_importe' => 9999999,
            'vigencia_desde' => '2003-01-01',
            'updated_at' => $ahora,
        ]);

        DB::table('regimen_percepcion')->where('codigo', 'PNC')->update([
            'nombre' => 'Percepción IVA no categorizado (RG 2126)',
            'habilitado' => true,
            'tasa' => 10.5,
            'minimo_base' => 0,
            'minimo_importe' => 0,
            'vigencia_desde' => '2003-01-01',
            'updated_at' => $ahora,
        ]);
    }

    private function cuentasDebitoFiscal($ahora): void
    {
        if (! Schema::hasTable('regimen_percepcion_cuentacontable') || ! Schema::hasTable('cuentacontable')) {
            return;
        }

        $usuarioId = (int) (DB::table('usuario')->orderBy('id')->value('id') ?? 0);
        if ($usuarioId <= 0) {
            return;
        }

        $regimenes = DB::table('regimen_percepcion')->whereIn('codigo', ['PIVA', 'PNC'])->pluck('id', 'codigo');
        $cuentas = DB::table('cuentacontable')
            ->where('codigo', '330303000')
            ->where('tipocuenta', '1')
            ->get(['id', 'empresa_id']);

        foreach ($regimenes as $regimenId) {
            foreach ($cuentas as $cuenta) {
                $existe = DB::table('regimen_percepcion_cuentacontable')
                    ->where('regimen_percepcion_id', $regimenId)
                    ->where('empresa_id', $cuenta->empresa_id)
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('regimen_percepcion_cuentacontable')->insert([
                    'regimen_percepcion_id' => $regimenId,
                    'empresa_id' => $cuenta->empresa_id,
                    'cuentacontable_id' => $cuenta->id,
                    'creousuario_id' => $usuarioId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }
};
