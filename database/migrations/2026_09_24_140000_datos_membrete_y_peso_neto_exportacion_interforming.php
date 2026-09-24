<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturaPdfMembreteSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FAE Anita (E4-703 INEMA): membrete Interforming + peso_neto en venta_exportacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('venta_exportacion', 'peso_neto')) {
            Schema::table('venta_exportacion', function (Blueprint $table) {
                $table->decimal('peso_neto', 18, 4)->nullable()->after('leyendaexportacion');
            });
        }

        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $locId = (int) (DB::table('localidad')->where('nombre', 'Isidro Casanova')->value('id') ?: 0);
        $provBsAs = (int) (DB::table('provincia')->where('nombre', 'Buenos Aires')->value('id') ?: 1);

        if ($locId > 0) {
            DB::table('localidad')->where('id', $locId)->update([
                'codigopostal' => '1765',
                'provincia_id' => $provBsAs,
                'updated_at' => now(),
            ]);
        }

        DB::table('empresa')->where('id', 1)->update([
            'domicilio' => 'Bruselas 1524',
            'localidad_id' => $locId > 0 ? $locId : null,
            'provincia_id' => $provBsAs,
            'codigopostal' => '1765',
            'numeroiibb' => '902-860-218-1',
            'fechainicioactividad' => '1975-08-27',
            'updated_at' => now(),
        ]);

        DB::table('puntoventa')->where('id', 4)->update([
            'domicilio' => 'Bruselas 1524',
            'localidad_id' => $locId > 0 ? $locId : null,
            'provincia_id' => $provBsAs,
            'codigopostal' => '1765',
            'telefono' => '(5411) 4625-0600 Lineas Rotativas',
            'email' => 'info@interforming.com.ar',
            'updated_at' => now(),
        ]);

        $params = [
            FacturaPdfMembreteSupport::CLAVE_LUGAR => 'Isidro Casanova, Buenos Aires',
            FacturaPdfMembreteSupport::CLAVE_WEB => 'E.Mail: info@interforming.com.ar - www.interforming.com.ar',
            FacturaPdfMembreteSupport::CLAVE_LEYENDA_IVA => 'IVA Responsable Inscripto',
            FacturaPdfMembreteSupport::CLAVE_INICIO_FALLBACK => '27-08-75',
            'pdf_caja_jubilacion' => '440-265',
            'pdf_fax' => '(5411) 4625-5899',
        ];

        foreach ($params as $clave => $valor) {
            $existe = DB::table('factura_pdf_parametro')
                ->where('empresa_id', 1)
                ->where('clave', $clave)
                ->exists();
            if ($existe) {
                DB::table('factura_pdf_parametro')
                    ->where('empresa_id', 1)
                    ->where('clave', $clave)
                    ->update(['valor' => $valor, 'updated_at' => now()]);
            } else {
                DB::table('factura_pdf_parametro')->insert([
                    'empresa_id' => 1,
                    'clave' => $clave,
                    'valor' => $valor,
                    'etiqueta' => $clave,
                    'ayuda' => 'Membrete FAE Interforming (Anita)',
                    'orden' => 200,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        FacturaPdfMembreteSupport::forget(1);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }
        // No revertir datos de membrete (operativos). Solo columna peso_neto si se agregó.
    }
};
