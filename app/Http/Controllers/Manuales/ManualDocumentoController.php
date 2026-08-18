<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manuales;

use App\Http\Controllers\Controller;
use App\Services\Manuales\ManualContenidoService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

abstract class ManualDocumentoController extends Controller
{
    protected string $directorio;

    protected string $baseName;

    protected string $configKey;

    protected string $imgPublicPrefix;

    protected string $etiquetaModulo;

    /**
     * @var array<int, array{label:string,route:string}>
     */
    protected array $atajos = [];

    public function __construct(
        protected readonly ManualContenidoService $manual,
    ) {
    }

    public function index()
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        return view('manual.documento', [
            'meta' => $this->manual->meta(),
            'configKey' => $this->configKey,
            'imgPublicPrefix' => $this->imgPublicPrefix,
            'etiquetaModulo' => $this->etiquetaModulo,
            'rutaPdf' => static::rutaPdf(),
            'rutaWord' => static::rutaWord(),
            'atajos' => $this->atajos,
        ]);
    }

    public function descargarPdf(): BinaryFileResponse
    {
        return $this->descargar('pdf');
    }

    public function descargarWord(): BinaryFileResponse
    {
        return $this->descargar('docx');
    }

    abstract protected static function rutaPdf(): string;

    abstract protected static function rutaWord(): string;

    private function descargar(string $extension): BinaryFileResponse
    {
        $path = base_path(
            'docs/'.$this->directorio.'/'.$this->baseName.'.'.$extension,
        );
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/'.$this->directorio.'/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
