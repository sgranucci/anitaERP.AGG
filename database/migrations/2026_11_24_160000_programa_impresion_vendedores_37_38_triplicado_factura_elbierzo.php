<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\ComprobanteImpresionReglaClave;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El Bierzo: programa para vendedores 37 y 38 (Raúl Raigada padre/hijo).
 * Copia del DEFAULT vigente + copia TRI (TRIPLICADO) en factura.
 */
return new class extends Migration
{
    private const CODIGO = 'VEND_37_38_TRI';

    /** @var list<string> */
    private const CODIGOS_VENDEDOR = ['37', '38', '134', '34'];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }
        if (! Schema::hasTable('comprobante_impresion_programa')
            || ! Schema::hasTable('comprobante_impresion_formulario')
            || ! Schema::hasTable('comprobante_impresion_copia')
            || ! Schema::hasTable('comprobante_impresion_regla')) {
            return;
        }

        $ahora = now();
        $origen = DB::table('comprobante_impresion_programa')
            ->where('codigo', 'DEFAULT')
            ->orderByDesc('empresa_id')
            ->first();
        if (! $origen) {
            return;
        }

        $programaId = (int) (DB::table('comprobante_impresion_programa')
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);

        if ($programaId === 0) {
            $programaId = (int) DB::table('comprobante_impresion_programa')->insertGetId([
                'codigo' => self::CODIGO,
                'nombre' => 'Vendedores 37/38 (factura con triplicado)',
                'empresa_id' => $origen->empresa_id,
                'permite_disparo_al_grabar' => (int) $origen->permite_disparo_al_grabar,
                'enviar_automatico_al_facturar' => (int) ($origen->enviar_automatico_al_facturar ?? 1),
                'plan_con_envios' => (int) ($origen->plan_con_envios ?? 0),
                'pedido_solo_si_cliente_divide' => (int) ($origen->pedido_solo_si_cliente_divide ?? 1),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
            $this->clonarFormulariosYCopias((int) $origen->id, $programaId, $ahora);
        }

        $this->asegurarTriplicadoFactura($programaId, $ahora);
        $this->asegurarReglasVendedor($programaId, $ahora);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }
        if (! Schema::hasTable('comprobante_impresion_programa')) {
            return;
        }

        $programaId = (int) (DB::table('comprobante_impresion_programa')
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);
        if ($programaId <= 0) {
            return;
        }

        $formIds = DB::table('comprobante_impresion_formulario')
            ->where('programa_id', $programaId)
            ->pluck('id');
        if ($formIds->isNotEmpty() && Schema::hasTable('comprobante_impresion_copia')) {
            DB::table('comprobante_impresion_copia')->whereIn('formulario_id', $formIds)->delete();
        }
        if (Schema::hasTable('comprobante_impresion_formulario')) {
            DB::table('comprobante_impresion_formulario')->where('programa_id', $programaId)->delete();
        }
        if (Schema::hasTable('comprobante_impresion_regla')) {
            DB::table('comprobante_impresion_regla')->where('programa_id', $programaId)->delete();
        }
        DB::table('comprobante_impresion_programa')->where('id', $programaId)->delete();
    }

    private function clonarFormulariosYCopias(int $origenId, int $destinoId, $ahora): void
    {
        $formularios = DB::table('comprobante_impresion_formulario')
            ->where('programa_id', $origenId)
            ->orderBy('orden')
            ->get();

        foreach ($formularios as $form) {
            $nuevoFormId = (int) DB::table('comprobante_impresion_formulario')->insertGetId([
                'programa_id' => $destinoId,
                'orden' => (int) $form->orden,
                'formulario' => $form->formulario,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            $copias = DB::table('comprobante_impresion_copia')
                ->where('formulario_id', $form->id)
                ->orderBy('orden')
                ->get();
            foreach ($copias as $copia) {
                DB::table('comprobante_impresion_copia')->insert([
                    'formulario_id' => $nuevoFormId,
                    'orden' => (int) $copia->orden,
                    'codigo' => $copia->codigo,
                    'leyenda' => $copia->leyenda,
                    'destinatario' => $copia->destinatario,
                    'salida_id' => $copia->salida_id,
                    'incluir_en_pdf_sesion' => (int) $copia->incluir_en_pdf_sesion,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    private function asegurarTriplicadoFactura(int $programaId, $ahora): void
    {
        $formId = (int) (DB::table('comprobante_impresion_formulario')
            ->where('programa_id', $programaId)
            ->where('formulario', 'FACTURA')
            ->value('id') ?? 0);
        if ($formId <= 0) {
            return;
        }

        $existeTri = DB::table('comprobante_impresion_copia')
            ->where('formulario_id', $formId)
            ->where('codigo', 'TRI')
            ->exists();
        if ($existeTri) {
            return;
        }

        // Dejar ORI / TRI / DUP como en remito: triplicado papel entre original y archivo NAS.
        $dup = DB::table('comprobante_impresion_copia')
            ->where('formulario_id', $formId)
            ->where('codigo', 'DUP')
            ->first();
        if ($dup && (int) $dup->orden <= 20) {
            DB::table('comprobante_impresion_copia')
                ->where('id', $dup->id)
                ->update([
                    'orden' => 30,
                    'updated_at' => $ahora,
                ]);
        }

        DB::table('comprobante_impresion_copia')->insert([
            'formulario_id' => $formId,
            'orden' => 20,
            'codigo' => 'TRI',
            'leyenda' => 'TRIPLICADO',
            'destinatario' => 'Chofer',
            'salida_id' => null,
            'incluir_en_pdf_sesion' => 1,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    private function asegurarReglasVendedor(int $programaId, $ahora): void
    {
        if (! Schema::hasTable('vendedor')) {
            return;
        }

        $prioridad = (int) (ComprobanteImpresionReglaClave::PRECEDENCIA[ComprobanteImpresionReglaClave::VENDEDOR] ?? 50);

        foreach (self::CODIGOS_VENDEDOR as $codigo) {
            $vendedorId = (int) (DB::table('vendedor')
                ->where('codigo', $codigo)
                ->value('id') ?? 0);
            if ($vendedorId <= 0) {
                continue;
            }

            $existe = DB::table('comprobante_impresion_regla')
                ->where('programa_id', $programaId)
                ->where('clave', ComprobanteImpresionReglaClave::VENDEDOR)
                ->where('valor_id', $vendedorId)
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('comprobante_impresion_regla')->insert([
                'programa_id' => $programaId,
                'clave' => ComprobanteImpresionReglaClave::VENDEDOR,
                'valor_id' => $vendedorId,
                'prioridad' => $prioridad,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }
};
