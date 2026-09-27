<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Arca\ArcaCertificadoCsrService;
use App\Support\Arca\ArcaCertificadoCsrSupport;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificadoArcaController extends Controller
{
    public function __construct(
        private ArcaCertificadoCsrService $csrService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index()
    {
        can('listar-certificados-arca');

        $filas = $this->inventarioPermitido();
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
            $entrada = $this->buscarCertificadoPermitido($id);
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
            $entrada = $this->buscarCertificadoPermitido($id);
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

    public function exportarPar(Request $request): BinaryFileResponse|RedirectResponse
    {
        can('instalar-certificados-arca');

        $id = trim((string) $request->query('id', ''));
        if ($id === '') {
            return $this->volverError('Indique el certificado.');
        }

        try {
            $entrada = $this->buscarCertificadoPermitido($id);
            $r = $this->csrService->exportarPar($entrada);
        } catch (Exception $e) {
            return $this->volverError($e->getMessage());
        }

        return response()
            ->download($r['zip_path'], $r['download_name'], [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }

    public function instalar(Request $request): RedirectResponse
    {
        can('instalar-certificados-arca');

        $id = trim((string) $request->input('certificado_id', ''));
        if ($id === '') {
            return $this->volverError('Indique el certificado.');
        }

        $zip = $request->file('par_zip');
        $clave = $request->file('clave');
        $file = $request->file('certificado');
        $hayZip = $zip !== null && $zip->isValid();
        $hayClave = $clave !== null && $clave->isValid();
        $hayCrt = $file !== null && $file->isValid();

        if (! $hayZip && ! $hayCrt) {
            return $this->volverError('Seleccione el .crt de ARCA, o el ZIP exportado desde el otro servidor (cert.crt + privada.key).');
        }
        if ($hayZip && $zip->getSize() > 262144) {
            return $this->volverError('El ZIP es demasiado grande para un par de certificado ARCA.');
        }
        if ($hayCrt && $file->getSize() > 65536) {
            return $this->volverError('El archivo es demasiado grande para un certificado ARCA.');
        }
        if ($hayClave && $clave->getSize() > 65536) {
            return $this->volverError('La clave privada es demasiado grande.');
        }

        try {
            $entrada = $this->buscarCertificadoPermitido($id);
            $replicarIds = $this->replicarIdsDesdeRequest($request, $id);
            if ($hayZip) {
                $par = ArcaCertificadoCsrSupport::parDesdeZip($zip->getRealPath());
                $r = $this->csrService->importarParDesdeContenido($entrada, $par['cert'], $par['key'], false, $replicarIds);
            } elseif ($hayClave) {
                $raw = $this->leerUpload($file);
                $keyRaw = $this->leerUpload($clave);
                $r = $this->csrService->importarParDesdeContenido($entrada, $raw, $keyRaw, false, $replicarIds);
            } else {
                $raw = $this->leerUpload($file);
                $r = $this->csrService->instalarDesdeUpload($entrada, $raw, false, $replicarIds);
            }
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

        $desdeOtro = ($r['origen'] ?? '') === 'par';

        return redirect()
            ->route('certificados_arca')
            ->with(
                'mensaje',
                'Certificado «'.$alias.'» '.($desdeOtro ? 'importado' : 'validado').' e instalado'.
                ($vence !== '' ? ' (vence '.$vence.')' : '').
                ' en: '.( $etiquetas !== [] ? implode(', ', $etiquetas) : $entrada['etiqueta'] ).
                '. Backup en '.$r['backup_dir'].'.'
            );
    }

    private function leerUpload(UploadedFile $file): string
    {
        $raw = @file_get_contents($file->getRealPath());
        if (! is_string($raw) || trim($raw) === '') {
            throw new Exception('No se pudo leer el archivo subido.');
        }

        return $raw;
    }

    public function probar(Request $request): RedirectResponse
    {
        can('listar-certificados-arca');

        $id = trim((string) $request->input('certificado_id', ''));
        if ($id === '') {
            return $this->volverError('Indique el certificado.');
        }

        try {
            $entrada = $this->buscarCertificadoPermitido($id);
            $r = $this->csrService->probarConexion($entrada);
        } catch (Exception $e) {
            return $this->volverError($e->getMessage());
        }

        return redirect()
            ->route('certificados_arca')
            ->with('prueba_certificado_arca', $r);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inventarioPermitido(): array
    {
        return $this->csrService->filtrarPorEmpresasAsignadas(
            $this->csrService->inventariar(),
            $this->empresaRepository->traeEmpresasAsignadas()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarCertificadoPermitido(string $id): array
    {
        $entrada = $this->csrService->buscarPorId($id);
        $this->assertAccesoCertificado($entrada);

        return $entrada;
    }

    /**
     * @param  array<string, mixed>  $entrada
     */
    private function assertAccesoCertificado(array $entrada): void
    {
        $empresaId = (int) ($entrada['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new Exception('No tiene acceso a certificados de esa empresa.');
        }
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
        $permitidos = collect($this->inventarioPermitido())->pluck('id')->all();
        $ids = [];
        foreach ($raw as $id) {
            $id = trim((string) $id);
            if ($id === '' || $id === $origenId) {
                continue;
            }
            if (! in_array($id, $permitidos, true)) {
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
