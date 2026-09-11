<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Arca\ArcaCertificadoCsrService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificadoArcaController extends Controller
{
    public function __construct(
        private ArcaCertificadoCsrService $csrService,
    ) {}

    public function index()
    {
        can('listar-certificados-arca');

        $filas = $this->csrService->inventariar();
        $puedeGenerar = can('generar-csr-certificados-arca', false);
        $puedeInstalar = can('instalar-certificados-arca', false);
        $filasJs = collect($filas)->map(static function (array $f) {
            return [
                'id' => $f['id'],
                'etiqueta' => $f['etiqueta'],
                'alias' => $f['alias'] ?? '',
                'cuit' => $f['cuit'] ?? '',
            ];
        })->values()->all();

        return view('ventas.certificados_arca.index', compact(
            'filas',
            'filasJs',
            'puedeGenerar',
            'puedeInstalar'
        ));
    }

    public function generarCsr(Request $request): RedirectResponse
    {
        can('generar-csr-certificados-arca');

        $id = trim((string) $request->input('certificado_id', ''));
        if ($id === '') {
            return $this->volverError('Indique el certificado.');
        }

        try {
            $entrada = $this->csrService->buscarPorId($id);
            $r = $this->csrService->generar($entrada);
        } catch (Exception $e) {
            return $this->volverError($e->getMessage());
        }

        return redirect()
            ->route('certificados_arca')
            ->with(
                'mensaje',
                'CSR generado para alias «'.$r['alias'].'». Descárguelo y súbalo en ARCA con el mismo alias. '.
                'Cuando tenga el .crt, úselo en Subir certificado (se valida e instala solo).'
            );
    }

    public function descargarCsr(Request $request): BinaryFileResponse|RedirectResponse
    {
        can('listar-certificados-arca');

        $id = trim((string) $request->query('id', ''));
        if ($id === '') {
            return $this->volverError('Indique el certificado.');
        }

        try {
            $entrada = $this->csrService->buscarPorId($id);
        } catch (Exception $e) {
            return $this->volverError($e->getMessage());
        }

        $path = (string) ($entrada['csr_path'] ?? '');
        if ($path === '' || ! is_readable($path)) {
            return $this->volverError('No hay un CSR generado para este certificado. Genérelo primero.');
        }

        $servicio = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string) ($entrada['servicio'] ?? 'arca')) ?: 'arca';
        $alias = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string) ($entrada['alias'] ?? 'pedido')) ?: 'pedido';

        return response()->download($path, $servicio.'_'.$alias.'.csr', [
            'Content-Type' => 'application/pkcs10',
        ]);
    }

    public function instalar(Request $request): RedirectResponse
    {
        can('instalar-certificados-arca');

        $id = trim((string) $request->input('certificado_id', ''));
        if ($id === '') {
            return $this->volverError('Indique el certificado.');
        }

        $file = $request->file('certificado');
        if ($file === null || ! $file->isValid()) {
            return $this->volverError('Seleccione el archivo .crt descargado de ARCA.');
        }
        if ($file->getSize() > 65536) {
            return $this->volverError('El archivo es demasiado grande para un certificado ARCA.');
        }

        $raw = @file_get_contents($file->getRealPath());
        if (! is_string($raw) || trim($raw) === '') {
            return $this->volverError('No se pudo leer el archivo subido.');
        }

        try {
            $entrada = $this->csrService->buscarPorId($id);
            $replicarIds = $this->replicarIdsDesdeRequest($request, $id);
            $r = $this->csrService->instalarDesdeUpload($entrada, $raw, false, $replicarIds);
        } catch (Exception $e) {
            return $this->volverError($e->getMessage());
        }

        $v = $r['validacion'] ?? [];
        $alias = (string) ($v['alias'] ?? $entrada['alias'] ?? '');
        $vence = (string) ($v['valid_to'] ?? '');
        $etiquetas = [];
        foreach ($r['instalados'] ?? [] as $inst) {
            $etiquetas[] = (string) ($inst['etiqueta'] ?? $inst['id'] ?? '');
        }
        $etiquetas = array_values(array_filter($etiquetas));

        return redirect()
            ->route('certificados_arca')
            ->with(
                'mensaje',
                'Certificado «'.$alias.'» validado e instalado'.($vence !== '' ? ' (vence '.$vence.')' : '').
                ' en: '.( $etiquetas !== [] ? implode(', ', $etiquetas) : $entrada['etiqueta'] ).
                '. Backup en '.$r['backup_dir'].'.'
            );
    }

    /**
     * @return list<string>
     */
    private function replicarIdsDesdeRequest(Request $request, string $origenId): array
    {
        $raw = $request->input('replicar_ids', []);
        if (! is_array($raw)) {
            $raw = [$raw];
        }
        $ids = [];
        foreach ($raw as $id) {
            $id = trim((string) $id);
            if ($id === '' || $id === $origenId) {
                continue;
            }
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    private function volverError(string $mensaje): RedirectResponse
    {
        return redirect()
            ->route('certificados_arca')
            ->with('mensaje-error', $mensaje);
    }
}
