<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comprobante_impresion_programa')) {
            return;
        }

        $ahora = now();
        $script = base_path('bin/archivar-comprobante-nas.sh').' "%s"';

        $this->asegurarSalida('NAS Facturas', $script);
        $this->asegurarSalida('NAS Remitos', $script);
        $this->asegurarSalida('NAS Pedidos', $script);

        $programaId = (int) (DB::table('comprobante_impresion_programa')->where('codigo', 'DEFAULT')->value('id') ?? 0);
        if ($programaId === 0) {
            $programaId = (int) DB::table('comprobante_impresion_programa')->insertGetId([
                'codigo' => 'DEFAULT',
                'nombre' => 'Default sistema (1 factura Original)',
                'permite_disparo_al_grabar' => false,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $formId = (int) (DB::table('comprobante_impresion_formulario')
            ->where('programa_id', $programaId)
            ->where('formulario', 'FACTURA')
            ->value('id') ?? 0);
        if ($formId === 0) {
            $formId = (int) DB::table('comprobante_impresion_formulario')->insertGetId([
                'programa_id' => $programaId,
                'orden' => 10,
                'formulario' => 'FACTURA',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $copiaId = (int) (DB::table('comprobante_impresion_copia')
            ->where('formulario_id', $formId)
            ->where('codigo', 'ORI')
            ->value('id') ?? 0);
        if ($copiaId === 0) {
            DB::table('comprobante_impresion_copia')->insert([
                'formulario_id' => $formId,
                'orden' => 10,
                'codigo' => 'ORI',
                'leyenda' => 'ORIGINAL',
                'destinatario' => 'Cliente',
                'salida_id' => null,
                'incluir_en_pdf_sesion' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $reglaId = (int) (DB::table('comprobante_impresion_regla')
            ->where('programa_id', $programaId)
            ->where('clave', 'DEFAULT')
            ->value('id') ?? 0);
        if ($reglaId === 0) {
            DB::table('comprobante_impresion_regla')->insert([
                'programa_id' => $programaId,
                'clave' => 'DEFAULT',
                'valor_id' => null,
                'prioridad' => 10,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('comprobante_impresion_programa')) {
            return;
        }

        $programaId = (int) (DB::table('comprobante_impresion_programa')->where('codigo', 'DEFAULT')->value('id') ?? 0);
        if ($programaId > 0) {
            $formIds = DB::table('comprobante_impresion_formulario')->where('programa_id', $programaId)->pluck('id');
            if ($formIds->isNotEmpty()) {
                DB::table('comprobante_impresion_copia')->whereIn('formulario_id', $formIds)->delete();
            }
            DB::table('comprobante_impresion_formulario')->where('programa_id', $programaId)->delete();
            DB::table('comprobante_impresion_regla')->where('programa_id', $programaId)->delete();
            DB::table('comprobante_impresion_programa')->where('id', $programaId)->delete();
        }
    }

    private function asegurarSalida(string $nombre, string $comando): void
    {
        if (! Schema::hasTable('salida')) {
            return;
        }
        $id = (int) (DB::table('salida')->where('nombre', $nombre)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('salida')->where('id', $id)->update([
                'comando' => $comando,
                'updated_at' => now(),
            ]);

            return;
        }
        DB::table('salida')->insert([
            'nombre' => $nombre,
            'comando' => $comando,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
