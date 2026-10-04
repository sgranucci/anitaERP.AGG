<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Arca\ArcaCertificadoCsrService;
use App\Support\Arca\ArcaCertificadoCsrSupport;
use App\Support\Arca\ArcaCertificadoPantallaSupport;
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
        $reemplazoPendiente = $this->reemplazoPendienteVigente();
        $serviciosVisibles = ArcaCertificadoPantallaSupport::serviciosVisibles();
        $serviciosPantalla = [];
        foreach (ArcaCertificadoCsrService::etiquetasServicio() as $servicioId => $etiqueta) {
            $serviciosPantalla[] = [
                'id' => $servicioId,
                'etiqueta' => $etiqueta,
                'visible' => in_array($servicioId, $serviciosVisibles, true),
            ];
        }

        return view('ventas.certificados_arca.index', compact(
            'filas',
            'filasJs',
            'puedeGenerar',
            'puedeInstalar',
            'reemplazoPendiente',
            'serviciosPantalla'
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

        if (! $hayZip && ! $hayCrt && ! $hayClave) {
            return $this->volverError('Seleccione el certificado, la clave privada, el ZIP, o la combinación que quiera instalar.');
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
            $crtRaw = null;
            $keyRaw = null;
            if ($hayZip) {
                $par = ArcaCertificadoCsrSupport::parDesdeZip((string) $zip->getRealPath());
                $crtRaw = $par['cert'];
                $keyRaw = $par['key'];
            }
            if ((! is_string($crtRaw) || trim($crtRaw) === '') && $hayCrt && $file instanceof UploadedFile) {
                $crtRaw = $this->leerUpload($file);
            }
            if ((! is_string($keyRaw) || trim($keyRaw) === '') && $hayClave && $clave instanceof UploadedFile) {
                $keyRaw = $this->leerUpload($clave);
            }
            $hayContenidoCrt = is_string($crtRaw) && trim($crtRaw) !== '';
            $hayContenidoKey = is_string($keyRaw) && trim($keyRaw) !== '';
            if ($hayContenidoCrt && ! $hayContenidoKey && $this->csrService->crtCoincideConCsrLocal($entrada, $crtRaw)) {
                $r = $this->csrService->instalarDesdeUpload($entrada, $crtRaw, false, $replicarIds);
            } else {
                $r = $this->csrService->instalarLoSubido($entrada, $crtRaw, $keyRaw, false, $replicarIds);
            }
        } catch (Exception $e) {
            return $this->volverError($e->getMessage());
        }

        if (! empty($r['pendiente'])) {
            $this->descartarReemplazoPendiente();
            $token = bin2hex(random_bytes(16));
            session([
                'cert_arca_reemplazo_pendiente' => [
                    'token' => $token,
                    'id' => $id,
                    'etiqueta' => (string) ($entrada['etiqueta'] ?? $id),
                    'dir' => (string) ($r['dir'] ?? ''),
                    'hay_crt' => (bool) ($r['hay_crt'] ?? false),
                    'hay_key' => (bool) ($r['hay_key'] ?? false),
                    'replicar_ids' => $replicarIds,
                    'avisos' => array_values(is_array($r['avisos'] ?? null) ? $r['avisos'] : []),
                    'alias' => (string) (($r['validacion']['alias'] ?? '') ?: ''),
                    'cuit' => (string) (($r['validacion']['cuit'] ?? '') ?: ''),
                    'valid_to' => (string) (($r['validacion']['valid_to'] ?? '') ?: ''),
                    'expira' => time() + 1800,
                ],
            ]);

            return redirect()->route('certificados_arca');
        }

        $this->descartarReemplazoPendiente();

        $v = $r['validacion'] ?? [];
        $alias = (string) ($v['alias'] ?? $entrada['alias'] ?? '');
        $vence = (string) ($v['valid_to'] ?? '');
        $etiquetas = [];
        foreach ($r['instalados'] ?? [] as $inst) {
            $etiquetas[] = (string) ($inst['etiqueta'] ?? $inst['id'] ?? '');
        }
        $etiquetas = array_values(array_filter($etiquetas));

        $desdeOtro = ($r['origen'] ?? '') === 'par';
        $soloClave = ($r['origen'] ?? '') === 'clave';
        $que = $soloClave
            ? 'Clave privada instalada'
            : ('Certificado «'.$alias.'» '.($desdeOtro ? 'y clave importados' : 'instalado').($vence !== '' ? ' (vence '.$vence.')' : ''));
        $avisos = is_array($r['avisos'] ?? null) ? $r['avisos'] : [];
        $aviso = trim((string) ($r['aviso'] ?? ''));
        if ($avisos === [] && $aviso !== '') {
            $avisos = [$aviso];
        }

        $redirect = redirect()
            ->route('certificados_arca')
            ->with(
                'mensaje',
                $que.
                ' en: '.( $etiquetas !== [] ? implode(', ', $etiquetas) : $entrada['etiqueta'] ).
                '. Backup en '.$r['backup_dir'].'.'
            );
        if ($avisos !== []) {
            $redirect->with('mensaje-aviso', array_values($avisos));
        }

        return $redirect;
    }

    public function guardarWebservices(Request $request): RedirectResponse
    {
        can('instalar-certificados-arca');

        $raw = $request->input('servicios', []);
        if (! is_array($raw)) {
            $raw = [];
        }

        try {
            ArcaCertificadoPantallaSupport::guardar(array_map('strval', $raw));
        } catch (Exception $e) {
            return $this->volverError($e->getMessage());
        }

        return redirect()
            ->route('certificados_arca')
            ->with('mensaje', 'Webservices de esta pantalla actualizados. Los destildados dejan de figurar en el listado y en el mail de vencimiento. El punto de venta no cambia.');
    }

    public function confirmarReemplazo(Request $request): RedirectResponse
    {
        can('instalar-certificados-arca');

        $pendiente = $this->reemplazoPendienteVigente();
        $token = (string) $request->input('token', '');
        if ($pendiente === null || $token === '' || ! hash_equals((string) $pendiente['token'], $token)) {
            return $this->volverError('No hay un reemplazo pendiente para confirmar. Vuelva a subir el certificado.');
        }

        try {
            $entrada = $this->buscarCertificadoPermitido((string) $pendiente['id']);
            $r = $this->csrService->completarInstalacionSubida(
                $entrada,
                (string) $pendiente['dir'],
                (bool) $pendiente['hay_crt'],
                (bool) $pendiente['hay_key'],
                is_array($pendiente['replicar_ids'] ?? null) ? $pendiente['replicar_ids'] : [],
            );
        } catch (Exception $e) {
            return $this->volverError($e->getMessage());
        }

        session()->forget('cert_arca_reemplazo_pendiente');

        $alias = (string) ($pendiente['alias'] ?? $entrada['alias'] ?? '');
        $vence = (string) ($pendiente['valid_to'] ?? '');
        $etiquetas = [];
        foreach ($r['instalados'] ?? [] as $inst) {
            $etiquetas[] = (string) ($inst['etiqueta'] ?? $inst['id'] ?? '');
        }
        $etiquetas = array_values(array_filter($etiquetas));
        $avisos = is_array($pendiente['avisos'] ?? null) ? $pendiente['avisos'] : [];

        $redirect = redirect()
            ->route('certificados_arca')
            ->with(
                'mensaje',
                'Certificado «'.$alias.'» instalado'.
                ($vence !== '' ? ' (vence '.$vence.')' : '').
                ' en: '.($etiquetas !== [] ? implode(', ', $etiquetas) : $entrada['etiqueta']).
                '. Backup en '.$r['backup_dir'].'.'
            );
        if ($avisos !== []) {
            $redirect->with('mensaje-aviso', array_values($avisos));
        }

        return $redirect;
    }

    public function cancelarReemplazo(Request $request): RedirectResponse
    {
        can('instalar-certificados-arca');

        $pendiente = $this->reemplazoPendienteVigente();
        $token = (string) $request->input('token', '');
        if ($pendiente !== null && $token !== '' && hash_equals((string) $pendiente['token'], $token)) {
            $this->descartarReemplazoPendiente();
        }

        return redirect()
            ->route('certificados_arca')
            ->with('mensaje', 'Reemplazo cancelado. El certificado vigente no se modificó.');
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
        return $this->csrService->filtrar(
            $this->csrService->filtrarPorEmpresasAsignadas(
                $this->csrService->inventariar(),
                $this->empresaRepository->traeEmpresasAsignadas()
            ),
            ArcaCertificadoPantallaSupport::serviciosVisibles(),
            null
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

    /**
     * @return array<string, mixed>|null
     */
    private function reemplazoPendienteVigente(): ?array
    {
        $pendiente = session('cert_arca_reemplazo_pendiente');
        if (! is_array($pendiente)) {
            return null;
        }
        if ((int) ($pendiente['expira'] ?? 0) < time()) {
            $this->descartarReemplazoPendiente();

            return null;
        }

        return $pendiente;
    }

    private function descartarReemplazoPendiente(): void
    {
        $pendiente = session('cert_arca_reemplazo_pendiente');
        session()->forget('cert_arca_reemplazo_pendiente');
        if (! is_array($pendiente)) {
            return;
        }
        $id = trim((string) ($pendiente['id'] ?? ''));
        $dir = trim((string) ($pendiente['dir'] ?? ''));
        if ($id === '' || $dir === '') {
            return;
        }
        try {
            $entrada = $this->buscarCertificadoPermitido($id);
            $this->csrService->descartarInstalacionSubida($entrada, $dir);
        } catch (Exception) {
            // El pendiente ya no está o no corresponde a un certificado visible.
        }
    }

    private function volverError(string $mensaje): RedirectResponse
    {
        return redirect()
            ->route('certificados_arca')
            ->with('mensaje-error', $mensaje);
    }
}
