<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membrete PDF factura por empresa + limpieza PV Ferli (tel/localidad).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('factura_pdf_parametro')) {
            Schema::create('factura_pdf_parametro', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable()->index();
                $table->string('clave', 80);
                $table->string('valor', 500)->default('');
                $table->string('etiqueta', 160)->nullable();
                $table->string('ayuda', 500)->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
                $table->unique(['empresa_id', 'clave'], 'factura_pdf_parametro_emp_clave_uq');
            });
        }

        $defs = [
            ['pdf_web', 'Sitio web / email', 'Línea de contacto bajo el domicilio en el PDF', 10],
            ['pdf_imp_internos', 'Impuestos internos', 'Texto fiscal de imp. internos', 20],
            ['pdf_seguridad_higiene', 'Seguridad e higiene', 'Partida / texto municipal', 30],
            ['pdf_habilitacion', 'Habilitación', 'Nro. de habilitación', 40],
            ['pdf_cheques_a_la_orden', 'Cheques a la orden', 'Leyenda del pie (cheques)', 50],
            ['pdf_lugar', 'Lugar de emisión', 'Ej. Bs.As. junto a la fecha', 60],
            ['pdf_leyenda_iva', 'Leyenda IVA emisor', 'Ej. I.V.A. RESPONSABLE INSCRIPTO', 70],
            ['pdf_inicio_actividad_fallback', 'Inicio actividades (fallback)', 'Si empresa.fechainicioactividad está vacía', 80],
            ['pdf_leyenda_mercaderia', 'Leyenda mercadería', 'Pie Ferli / similares', 90],
            ['pdf_leyenda_cheques', 'Leyenda cheques (prefijo)', 'Texto antes del beneficiario', 100],
        ];

        $defaultsGlobales = [
            'pdf_web' => '',
            'pdf_imp_internos' => '',
            'pdf_seguridad_higiene' => '',
            'pdf_habilitacion' => '',
            'pdf_cheques_a_la_orden' => '',
            'pdf_lugar' => '',
            'pdf_leyenda_iva' => 'I.V.A. RESPONSABLE INSCRIPTO',
            'pdf_inicio_actividad_fallback' => '',
            'pdf_leyenda_mercaderia' => '1. La mercaderia viaja por cuenta y riesgo del comprador.',
            'pdf_leyenda_cheques' => '2. Rogamos extender los cheques a la orden de',
        ];

        foreach ($defs as [$clave, $etiqueta, $ayuda, $orden]) {
            $this->asegurarParametro(null, $clave, $defaultsGlobales[$clave] ?? '', $etiqueta, $ayuda, $orden);
        }

        if (EntornoEmpresaSupport::esFerli()) {
            $empresaId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?? 1);
            $ferli = [
                'pdf_web' => (string) env('FACTURACION_PDF_WEB', 'www.ferli.com.ar - info@ferli.com.ar'),
                'pdf_imp_internos' => (string) env('FACTURACION_PDF_IMP_INTERNOS', 'No responsable'),
                'pdf_seguridad_higiene' => (string) env('FACTURACION_PDF_SEGURIDAD_HIGIENE', 'Partida Municipal 55416'),
                'pdf_habilitacion' => (string) env('FACTURACION_PDF_HABILITACION', '44537-92ADM'),
                'pdf_cheques_a_la_orden' => (string) env('FACTURACION_PDF_CHEQUES_A_LA_ORDEN', 'CALZADOS FERLI S.A.'),
                'pdf_lugar' => 'Bs.As.',
                'pdf_leyenda_iva' => 'I.V.A. RESPONSABLE INSCRIPTO',
                'pdf_inicio_actividad_fallback' => '12/02/92',
                'pdf_leyenda_mercaderia' => '1. La mercaderia viaja por cuenta y riesgo del comprador.',
                'pdf_leyenda_cheques' => '2. Rogamos extender los cheques a la orden de',
            ];
            foreach ($defs as [$clave, $etiqueta, $ayuda, $orden]) {
                $this->asegurarParametro($empresaId, $clave, $ferli[$clave] ?? '', $etiqueta, $ayuda, $orden);
            }

            // Limpieza PV: telefono tenía "Ciudad Madero CP.1768"
            $locId = (int) (DB::table('localidad')
                ->where('nombre', 'VILLA MADERO')
                ->where('codigopostal', '1768')
                ->value('id') ?? 0);
            if ($locId > 0) {
                DB::table('puntoventa')
                    ->where('id', 1)
                    ->where('telefono', 'like', '%Madero%')
                    ->update([
                        'telefono' => null,
                        'codigopostal' => '1768',
                        'localidad_id' => $locId,
                        'provincia_id' => 2,
                        'updated_at' => now(),
                    ]);
            }
        }

        if (! Schema::hasColumn('remito_articulo', 'combinacion_id')) {
            Schema::table('remito_articulo', function (Blueprint $table) {
                $table->unsignedBigInteger('combinacion_id')->nullable()->after('articulo_id');
                $table->unsignedBigInteger('talle_id')->nullable()->after('combinacion_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('remito_articulo', 'combinacion_id')) {
            Schema::table('remito_articulo', function (Blueprint $table) {
                $table->dropColumn(['combinacion_id', 'talle_id']);
            });
        }
        Schema::dropIfExists('factura_pdf_parametro');
    }

    private function asegurarParametro(
        ?int $empresaId,
        string $clave,
        string $valor,
        string $etiqueta,
        string $ayuda,
        int $orden
    ): void {
        $q = DB::table('factura_pdf_parametro')->where('clave', $clave);
        if ($empresaId === null) {
            $q->whereNull('empresa_id');
        } else {
            $q->where('empresa_id', $empresaId);
        }
        if ($q->exists()) {
            return;
        }
        DB::table('factura_pdf_parametro')->insert([
            'empresa_id' => $empresaId,
            'clave' => $clave,
            'valor' => $valor,
            'etiqueta' => $etiqueta,
            'ayuda' => $ayuda,
            'orden' => $orden,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
