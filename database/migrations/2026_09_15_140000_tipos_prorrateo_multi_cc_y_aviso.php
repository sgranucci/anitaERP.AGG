<?php

use App\ApiAnita;
use App\Models\Compras\Tipotransaccion_Compra;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos prorrateados multi-CC (2ª letra P + ítem B/S/L/U) sin conceptos asociados.
 * Alta en anitaERP y intento de espejo en Anita compras.t_comp.
 * Aviso modulo → Sergio para probar precargas FxP*.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO_AVISO = 'precarga_prorrateo_multi_cc';

    private const SERGIO_USUARIO_ID = 2;

    /** @var list<array{abreviatura: string, nombre: string, codigoafip: string, signo: string, asientocontable: string}> */
    private const TIPOS = [
        ['abreviatura' => 'FPB', 'nombre' => 'Fac. Prorrateada Bienes', 'codigoafip' => '01', 'signo' => 'S', 'asientocontable' => 'S'],
        ['abreviatura' => 'FPS', 'nombre' => 'Fac. Prorrateada Servicios', 'codigoafip' => '01', 'signo' => 'S', 'asientocontable' => 'S'],
        ['abreviatura' => 'FPL', 'nombre' => 'Fac. Prorrateada Locaciones', 'codigoafip' => '01', 'signo' => 'S', 'asientocontable' => 'S'],
        ['abreviatura' => 'FPU', 'nombre' => 'Fac. Prorrateada Bienes de Uso', 'codigoafip' => '01', 'signo' => 'S', 'asientocontable' => 'S'],
        ['abreviatura' => 'CPB', 'nombre' => 'NC Prorrateada Bienes', 'codigoafip' => '03', 'signo' => 'R', 'asientocontable' => 'S'],
        ['abreviatura' => 'CPS', 'nombre' => 'NC Prorrateada Servicios', 'codigoafip' => '03', 'signo' => 'R', 'asientocontable' => 'S'],
        ['abreviatura' => 'CPL', 'nombre' => 'NC Prorrateada Locaciones', 'codigoafip' => '03', 'signo' => 'R', 'asientocontable' => 'S'],
        ['abreviatura' => 'CPU', 'nombre' => 'NC Prorrateada Bienes de Uso', 'codigoafip' => '03', 'signo' => 'R', 'asientocontable' => 'S'],
        ['abreviatura' => 'DPB', 'nombre' => 'ND Prorrateada Bienes', 'codigoafip' => '02', 'signo' => 'S', 'asientocontable' => 'S'],
        ['abreviatura' => 'DPS', 'nombre' => 'ND Prorrateada Servicios', 'codigoafip' => '02', 'signo' => 'S', 'asientocontable' => 'S'],
        ['abreviatura' => 'DPL', 'nombre' => 'ND Prorrateada Locaciones', 'codigoafip' => '02', 'signo' => 'S', 'asientocontable' => 'S'],
        ['abreviatura' => 'DPU', 'nombre' => 'ND Prorrateada Bienes de Uso', 'codigoafip' => '02', 'signo' => 'S', 'asientocontable' => 'S'],
    ];

    public function up(): void
    {
        $this->sembrarTiposErp();
        $this->sembrarTiposAnita();
        $this->sembrarAviso();
    }

    public function down(): void
    {
        if (Schema::hasTable('modulo_aviso_tipo')) {
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', self::MODULO)
                ->where('codigo', self::CODIGO_AVISO)
                ->value('id') ?? 0);
            if ($tipoId > 0 && Schema::hasTable('modulo_aviso_destinatario')) {
                DB::table('modulo_aviso_destinatario')
                    ->where('modulo_aviso_tipo_id', $tipoId)
                    ->delete();
            }
            DB::table('modulo_aviso_tipo')
                ->where('modulo', self::MODULO)
                ->where('codigo', self::CODIGO_AVISO)
                ->delete();
        }

        foreach (self::TIPOS as $tipo) {
            Tipotransaccion_Compra::query()
                ->where('abreviatura', $tipo['abreviatura'])
                ->delete();
        }
    }

    private function sembrarTiposErp(): void
    {
        $now = now();
        foreach (self::TIPOS as $tipo) {
            $existe = Tipotransaccion_Compra::withTrashed()
                ->where('abreviatura', $tipo['abreviatura'])
                ->first();
            if ($existe) {
                if (method_exists($existe, 'trashed') && $existe->trashed()) {
                    $existe->restore();
                }
                $existe->fill([
                    'nombre' => $tipo['nombre'],
                    'operacion' => 'L',
                    'codigoafip' => $tipo['codigoafip'],
                    'signo' => $tipo['signo'],
                    'subdiario' => 'C',
                    'asientocontable' => $tipo['asientocontable'],
                    'retieneiva' => 'S',
                    'retieneganancia' => 'S',
                    'retieneIIBB' => 'S',
                    'estado' => 'A',
                ]);
                $existe->save();
                continue;
            }

            Tipotransaccion_Compra::query()->create([
                'nombre' => $tipo['nombre'],
                'operacion' => 'L',
                'abreviatura' => $tipo['abreviatura'],
                'codigoafip' => $tipo['codigoafip'],
                'signo' => $tipo['signo'],
                'subdiario' => 'C',
                'asientocontable' => $tipo['asientocontable'],
                'retieneiva' => 'S',
                'retieneganancia' => 'S',
                'retieneIIBB' => 'S',
                'estado' => 'A',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function sembrarTiposAnita(): void
    {
        try {
            $api = new ApiAnita;
            $raw = $api->apiCall([
                'tabla' => 't_comp',
                'acc' => 'list',
                'sistema' => 'compras',
                'campos' => 'tcomp_clave',
            ]);
            $existentes = [];
            foreach (json_decode((string) $raw, true) ?: [] as $fila) {
                $clave = strtoupper(trim((string) ($fila['tcomp_clave'] ?? '')));
                if ($clave !== '') {
                    $existentes[$clave] = true;
                }
            }

            $campos = 'tcomp_clave,tcomp_desc,tcomp_oper,tcomp_refer,tcomp_subdiar,tcomp_oper_stk,'
                .'tcomp_genera_asi,tcomp_concepto,tcomp_tipo_comp,tcomp_tipo_oper,tcomp_toma_ret,tcomp_estado';

            foreach (self::TIPOS as $tipo) {
                $abrev = $tipo['abreviatura'];
                if (isset($existentes[$abrev])) {
                    continue;
                }

                $signoAnita = $tipo['signo'] === 'R' ? 'R' : 'S';
                $desc = mb_substr(str_replace('"', '', $tipo['nombre']), 0, 30);
                $valores = sprintf(
                    '"%s","%s","%s","000","C","","S","0","%s","M","T","A"',
                    $abrev,
                    $desc,
                    $signoAnita,
                    $tipo['codigoafip']
                );

                $api->apiCallEscritura([
                    'tabla' => 't_comp',
                    'acc' => 'insert',
                    'sistema' => 'compras',
                    'campos' => $campos,
                    'valores' => $valores,
                ], 't_comp insert '.$abrev, 'anita_bridge.fallo', true);
            }
        } catch (\Throwable $e) {
            Log::warning('migracion_tipos_prorrateo_anita', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sembrarAviso(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO_AVISO)
            ->value('id') ?? 0);

        if ($tipoId === 0) {
            $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId([
                'modulo' => self::MODULO,
                'codigo' => self::CODIGO_AVISO,
                'nombre' => 'Precarga prorrateada multi-CC (FxP*)',
                'descripcion' => 'Se dispara cuando la API de precarga graba una factura con tipo '
                    .'prorrateado (FPB/FPS/…) porque la OC tiene centros de costo que resuelven a '
                    .'distintos tipos finos. Sirve para revisar la apertura de IVA/gravado. '
                    .'Destinatarios en Configuración → Avisos por módulo.',
                'activo' => true,
                'mail_asunto' => 'Precarga prorrateada multi-CC — {comprobante} ({proveedor})',
                'mail_texto' => "Ingresó una precarga de factura con tipo prorrateado multi-CC.\n\n"
                    ."Empresa: {empresa}\n"
                    ."Proveedor: {proveedor}\n"
                    ."Comprobante: {comprobante}\n"
                    ."Tipo: {tipo}\n"
                    ."Fecha: {fecha}\n"
                    ."OC: {oc}\n"
                    ."CC destino: {centros}\n"
                    ."Tipos origen: {tipos_origen}\n"
                    ."Pesos por fino: {pesos}\n"
                    ."Subtotal: {subtotal}\n"
                    ."Total: {total}\n"
                    ."Alerta: {alerta}\n\n"
                    ."Conceptos:\n{conceptos}\n\n"
                    ."Revisar la precarga: {link_consulta}\n",
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($tipoId <= 0 || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $sergio = DB::table('usuario')
            ->where('id', self::SERGIO_USUARIO_ID)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->first(['id', 'email']);

        if (! $sergio) {
            return;
        }

        $existe = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where(function ($q) use ($sergio) {
                $q->where('usuario_id', $sergio->id)
                    ->orWhereRaw('LOWER(email) = ?', [strtolower((string) $sergio->email)]);
            })
            ->exists();

        if ($existe) {
            return;
        }

        DB::table('modulo_aviso_destinatario')->insert([
            'modulo_aviso_tipo_id' => $tipoId,
            'email' => $sergio->email,
            'usuario_id' => (int) $sergio->id,
            'empresa_id' => null,
            'centrocosto_id' => null,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
