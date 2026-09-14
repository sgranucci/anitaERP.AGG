<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Factura_Pdf_Parametro;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\FacturaPdfMembreteSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacturaPdfParametroController extends Controller
{
    /** @var list<array{clave: string, etiqueta: string, ayuda: string, orden: int}> */
    private const DEFINICIONES = [
        ['clave' => FacturaPdfMembreteSupport::CLAVE_WEB, 'etiqueta' => 'Sitio web / email', 'ayuda' => 'Línea de contacto bajo el domicilio en el PDF', 'orden' => 10],
        ['clave' => FacturaPdfMembreteSupport::CLAVE_IMP_INTERNOS, 'etiqueta' => 'Impuestos internos', 'ayuda' => 'Texto fiscal de imp. internos', 'orden' => 20],
        ['clave' => FacturaPdfMembreteSupport::CLAVE_SEGURIDAD_HIGIENE, 'etiqueta' => 'Seguridad e higiene', 'ayuda' => 'Partida / texto municipal', 'orden' => 30],
        ['clave' => FacturaPdfMembreteSupport::CLAVE_HABILITACION, 'etiqueta' => 'Habilitación', 'ayuda' => 'Nro. de habilitación', 'orden' => 40],
        ['clave' => FacturaPdfMembreteSupport::CLAVE_CHEQUES, 'etiqueta' => 'Cheques a la orden', 'ayuda' => 'Beneficiario de la leyenda de cheques', 'orden' => 50],
        ['clave' => FacturaPdfMembreteSupport::CLAVE_LUGAR, 'etiqueta' => 'Lugar de emisión', 'ayuda' => 'Ej. Bs.As. junto a la fecha', 'orden' => 60],
        ['clave' => FacturaPdfMembreteSupport::CLAVE_LEYENDA_IVA, 'etiqueta' => 'Leyenda IVA emisor', 'ayuda' => 'Ej. I.V.A. RESPONSABLE INSCRIPTO', 'orden' => 70],
        ['clave' => FacturaPdfMembreteSupport::CLAVE_INICIO_FALLBACK, 'etiqueta' => 'Inicio actividades (fallback)', 'ayuda' => 'Si empresa.fechainicioactividad está vacía', 'orden' => 80],
        ['clave' => FacturaPdfMembreteSupport::CLAVE_LEYENDA_MERCADERIA, 'etiqueta' => 'Leyenda mercadería', 'ayuda' => 'Pie del comprobante', 'orden' => 90],
        ['clave' => FacturaPdfMembreteSupport::CLAVE_LEYENDA_CHEQUES, 'etiqueta' => 'Leyenda cheques (prefijo)', 'ayuda' => 'Texto antes del beneficiario', 'orden' => 100],
    ];

    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('editar-factura-pdf-parametro');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = (int) ($request->input('empresa_id') ?: old('empresa_id') ?: ($empresa_query->first()->id ?? 0));
        $ambito = (string) ($request->input('ambito') ?: old('ambito') ?: 'empresa');
        if (! in_array($ambito, ['empresa', 'global'], true)) {
            $ambito = 'empresa';
        }

        $filas = $this->filasParaFormulario($ambito === 'global' ? null : $empresa_id);

        return view('ventas.factura_pdf_parametro.editar', compact(
            'empresa_query',
            'empresa_id',
            'ambito',
            'filas'
        ));
    }

    public function actualizar(Request $request)
    {
        can('actualizar-factura-pdf-parametro');

        $request->validate([
            'ambito' => 'required|in:empresa,global',
            'empresa_id' => 'nullable|integer|exists:empresa,id',
            'valores' => 'required|array',
            'valores.*' => 'nullable|string|max:500',
        ]);

        $ambito = (string) $request->input('ambito');
        $empresaId = $ambito === 'global' ? null : (int) $request->input('empresa_id');
        if ($ambito === 'empresa' && $empresaId <= 0) {
            return back()->withInput()->with('mensaje-error', 'Seleccioná una empresa.');
        }

        $valores = $request->input('valores', []);
        $clavesValidas = array_column(self::DEFINICIONES, 'clave');

        DB::transaction(function () use ($empresaId, $valores, $clavesValidas) {
            foreach (self::DEFINICIONES as $def) {
                $clave = $def['clave'];
                if (! in_array($clave, $clavesValidas, true)) {
                    continue;
                }
                $valor = trim((string) ($valores[$clave] ?? ''));
                Factura_Pdf_Parametro::query()->updateOrCreate(
                    [
                        'empresa_id' => $empresaId,
                        'clave' => $clave,
                    ],
                    [
                        'valor' => $valor,
                        'etiqueta' => $def['etiqueta'],
                        'ayuda' => $def['ayuda'],
                        'orden' => $def['orden'],
                    ]
                );
            }
        });

        FacturaPdfMembreteSupport::forget($empresaId);

        $redir = ['ambito' => $ambito];
        if ($empresaId) {
            $redir['empresa_id'] = $empresaId;
        }

        return redirect()
            ->route('factura_pdf_parametro', $redir)
            ->with('mensaje', 'Parámetros PDF de factura guardados.');
    }

    /**
     * @return list<array{clave: string, etiqueta: string, ayuda: string, orden: int, valor: string}>
     */
    private function filasParaFormulario(?int $empresaId): array
    {
        $existentes = Factura_Pdf_Parametro::query()
            ->when(
                $empresaId === null,
                fn ($q) => $q->whereNull('empresa_id'),
                fn ($q) => $q->where('empresa_id', $empresaId)
            )
            ->get(['clave', 'valor'])
            ->keyBy('clave');

        $defaults = FacturaPdfMembreteSupport::paraEmpresa($empresaId);
        $filas = [];
        foreach (self::DEFINICIONES as $def) {
            $clave = $def['clave'];
            $filas[] = array_merge($def, [
                'valor' => (string) (
                    $existentes->get($clave)?->valor
                    ?? $defaults[$clave]
                    ?? ''
                ),
            ]);
        }

        return $filas;
    }
}
