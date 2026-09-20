<?php

namespace App\Services\Stock;

use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Configuracion\Handlers\StockRecepcionProveedorIngresadaAvisoHandler;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Stock\RecepcionProveedorDiferenciaSupport;
use App\Support\Stock\RecepcionProveedorEncuestaSupport;
use App\Support\Stock\RecepcionProveedorEnlacePublicoSupport;
use App\Support\Stock\RecepcionProveedorParteUnicaSupport;
use App\Support\Stock\RecepcionProveedorRequisicionEmailSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Un solo correo por recepción y por destinatario, en lugar de uno por cada novedad.
 *
 * Confirmar una recepción podía disparar hasta diez correos a la misma persona: el aviso de
 * ingreso, la encuesta, y uno por cada bandera (diferencia de precio, de cantidad, artículo extra,
 * faltante de OC, laboratorio, línea rechazada, parte única). Acá se resuelven primero los
 * destinatarios de cada tipo de aviso y después se invierte el mapa: cada persona recibe un único
 * correo con las novedades que le corresponden según la configuración del ABM.
 *
 * No cambia quién se entera de qué: si alguien no estaba en la lista de "diferencia de precio",
 * su correo no incluye esa sección. Tampoco pisa los interruptores existentes: un tipo de aviso
 * inactivo sigue sin enviarse ni aparecer como sección.
 */
class RecepcionProveedorAvisoConfirmacionConsolidadoService
{
    /** Aviso base: da el asunto y el cuerpo del correo consolidado. */
    private const CODIGO_BASE = 'recepcion_proveedor_ingresada';

    private const CODIGO_ENCUESTA = 'recepcion_proveedor_encuesta';

    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
        private readonly StockRecepcionProveedorIngresadaAvisoHandler $handlerBase,
        private readonly RecepcionProveedorPdfService $pdfService,
    ) {}

    public function habilitado(): bool
    {
        return (bool) config('recepcion_proveedor.avisos_confirmacion_consolidados', true);
    }

    /**
     * @return bool true si se encargó del envío; false si el caller debe usar el camino por novedad
     */
    public function enviar(Recepcion_Proveedor $recepcion): bool
    {
        if (! $this->habilitado()) {
            return false;
        }

        try {
            $recepcion->loadMissing([
                'proveedores',
                'creousuarios',
                'recepcion_proveedor_articulos.articulos',
                'ordencompras',
            ]);

            $codigos = $this->codigosAplicables($recepcion);
            if ($codigos === []) {
                return true;
            }

            $porDestinatario = $this->novedadesPorDestinatario($recepcion, $codigos);
            if ($porDestinatario === []) {
                Log::info('recepcion_proveedor.avisos_consolidados.sin_destinatarios', [
                    'recepcion_id' => (int) $recepcion->id,
                ]);

                return true;
            }

            $this->despachar($recepcion, $porDestinatario);

            return true;
        } catch (\Throwable $e) {
            // Nunca romper la confirmación de la recepción por un problema de avisos.
            Log::error('recepcion_proveedor.avisos_consolidados.fallo', [
                'recepcion_id' => (int) $recepcion->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mismas condiciones que el envío por novedad, en el orden en que se leen en el correo.
     *
     * @return list<string>
     */
    private function codigosAplicables(Recepcion_Proveedor $recepcion): array
    {
        $codigos = [];

        if ($recepcion->tipo === Recepcion_Proveedor::TIPO_RECEPCION) {
            $codigos[] = self::CODIGO_BASE;
        }
        if ($recepcion->fl_precio_diferencia
            || RecepcionProveedorDiferenciaSupport::recepcionTieneDiferenciaPrecioEstricta($recepcion)) {
            $codigos[] = 'recepcion_proveedor_precio_diferencia';
        }
        if ($recepcion->fl_diferencia_cantidad) {
            $codigos[] = 'recepcion_proveedor_cantidad_diferencia';
        }
        if ($recepcion->fl_articulo_extra) {
            $codigos[] = 'recepcion_proveedor_articulo_extra';
        }
        if ($recepcion->fl_faltante_oc) {
            $codigos[] = 'recepcion_proveedor_faltante_oc';
        }
        if ($recepcion->fl_laboratorio) {
            $codigos[] = 'recepcion_proveedor_laboratorio';
        }
        if ($recepcion->fl_linea_rechazada) {
            $codigos[] = 'recepcion_proveedor_linea_rechazada';
        }
        if ($this->tienePartesUnicas($recepcion)) {
            $codigos[] = 'recepcion_proveedor_parte_unica';
        }
        if ($recepcion->tipo === Recepcion_Proveedor::TIPO_RECEPCION
            && config('recepcion_proveedor.encuesta_habilitada', true)) {
            $codigos[] = self::CODIGO_ENCUESTA;
        }

        return $codigos;
    }

    /** Mismo criterio que el envío por novedad: solo líneas con cantidad recibida. */
    private function tienePartesUnicas(Recepcion_Proveedor $recepcion): bool
    {
        $recepcion->loadMissing('recepcion_proveedor_articulos.articulos');

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            if ((float) $linea->cantidad <= 0) {
                continue;
            }
            if (RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($linea->articulos)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $codigos
     * @return array<string, array{tipos: list<ModuloAvisoTipo>, codigos: list<string>}>
     */
    private function novedadesPorDestinatario(Recepcion_Proveedor $recepcion, array $codigos): array
    {
        $filtro = [
            'empresa_id' => $recepcion->empresa_id ? (int) $recepcion->empresa_id : null,
            'centrocosto_id' => (int) (optional($recepcion->ordencompras)->centrocosto_id ?? 0) ?: null,
        ];
        $emailSolicitante = $this->emailSolicitante($recepcion);

        $out = [];
        foreach ($codigos as $codigo) {
            $tipo = ModuloAvisoTipo::query()
                ->where('modulo', 'stock')
                ->where('codigo', $codigo)
                ->where('activo', true)
                ->first();
            if (! $tipo) {
                continue;
            }

            $emails = $this->emailsDeTipo($tipo, $codigo, $filtro, $recepcion, $emailSolicitante);
            foreach ($emails as $email) {
                $out[$email] ??= ['tipos' => [], 'codigos' => []];
                $out[$email]['tipos'][] = $tipo;
                $out[$email]['codigos'][] = $codigo;
            }
        }

        return $out;
    }

    /**
     * @param  array{empresa_id: int|null, centrocosto_id: int|null}  $filtro
     * @return list<string>
     */
    private function emailsDeTipo(
        ModuloAvisoTipo $tipo,
        string $codigo,
        array $filtro,
        Recepcion_Proveedor $recepcion,
        ?string $emailSolicitante,
    ): array {
        // La encuesta nunca fue a la lista del ABM: siempre apuntó al solicitante de la OC.
        if ($codigo === self::CODIGO_ENCUESTA) {
            return $emailSolicitante !== null ? [$emailSolicitante] : [];
        }

        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $filtro);

        // El aviso de ingreso suma al solicitante de la OC, salvo en recepciones de laboratorio.
        if ($codigo === self::CODIGO_BASE && ! $recepcion->fl_laboratorio && $emailSolicitante !== null) {
            $emails[] = $emailSolicitante;
        }

        return array_values(array_unique(array_filter($emails)));
    }

    private function emailSolicitante(Recepcion_Proveedor $recepcion): ?string
    {
        $email = RecepcionProveedorRequisicionEmailSupport::emailSolicitanteOc($recepcion);
        if ($email === null || trim($email) === '') {
            return null;
        }

        return strtolower(trim($email));
    }

    /**
     * @param  array<string, array{tipos: list<ModuloAvisoTipo>, codigos: list<string>}>  $porDestinatario
     */
    private function despachar(Recepcion_Proveedor $recepcion, array $porDestinatario): void
    {
        $recepcionId = (int) $recepcion->id;
        $placeholders = $this->handlerBase->placeholders($recepcionId);
        $linkConsulta = RecepcionProveedorEnlacePublicoSupport::urlConsultaMail($recepcionId);
        $linkEncuesta = RecepcionProveedorEncuestaSupport::linkEncuestaProveedor($recepcion);
        $pdfAdjunto = null;

        foreach ($porDestinatario as $email => $datos) {
            $tipos = $datos['tipos'];
            $base = $this->tipoBase($tipos);

            $asunto = $this->aplicarPlaceholders(
                (string) $base->mail_asunto,
                $placeholders,
                $linkConsulta,
                $linkEncuesta
            );
            $cuerpo = $this->armarCuerpo(
                $base,
                $tipos,
                $datos['codigos'],
                $placeholders,
                $linkConsulta,
                $linkEncuesta
            );

            if ($pdfAdjunto === null && $this->algunoAdjuntaPdf($tipos)) {
                $pdfAdjunto = $this->generarPdf($recepcionId);
            }

            try {
                $mailable = new ModuloAvisoMail(
                    $asunto,
                    $cuerpo,
                    $base->nombre,
                    $base->incluir_link_consulta ? $linkConsulta : null,
                    $this->algunoAdjuntaPdf($tipos) ? $pdfAdjunto : null
                );
                if (! empty($base->mail_remitente)) {
                    $mailable->from($base->mail_remitente);
                }
                Mail::to($email)->queue($mailable);
            } catch (\Throwable $e) {
                Log::error('recepcion_proveedor.avisos_consolidados.envio_fallido', [
                    'recepcion_id' => $recepcionId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('recepcion_proveedor.avisos_consolidados.enviado', [
            'recepcion_id' => $recepcionId,
            'destinatarios' => count($porDestinatario),
        ]);
    }

    /**
     * @param  list<ModuloAvisoTipo>  $tipos
     */
    private function tipoBase(array $tipos): ModuloAvisoTipo
    {
        foreach ($tipos as $tipo) {
            if ((string) $tipo->codigo === self::CODIGO_BASE) {
                return $tipo;
            }
        }

        return $tipos[0];
    }

    /**
     * @param  list<ModuloAvisoTipo>  $tipos
     */
    private function algunoAdjuntaPdf(array $tipos): bool
    {
        foreach ($tipos as $tipo) {
            if ($tipo->adjuntar_pdf) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<ModuloAvisoTipo>  $tipos
     * @param  list<string>  $codigos
     * @param  array<string, string>  $placeholders
     */
    private function armarCuerpo(
        ModuloAvisoTipo $base,
        array $tipos,
        array $codigos,
        array $placeholders,
        ?string $linkConsulta,
        ?string $linkEncuesta,
    ): string {
        $cuerpo = $this->aplicarPlaceholders(
            (string) ($base->mail_texto ?? ''),
            $placeholders,
            $linkConsulta,
            $linkEncuesta
        );

        // Novedades distintas de la base: se listan como secciones del mismo correo.
        $novedades = [];
        foreach ($tipos as $i => $tipo) {
            $codigo = $codigos[$i] ?? '';
            if ($codigo === self::CODIGO_BASE || $codigo === self::CODIGO_ENCUESTA) {
                continue;
            }
            $novedades[] = trim((string) $tipo->nombre);
        }
        $novedades = array_values(array_unique(array_filter($novedades)));

        if ($novedades !== []) {
            $cuerpo .= "\n\nNovedades de esta recepción:";
            foreach ($novedades as $novedad) {
                $cuerpo .= "\n· ".$novedad;
            }
        }

        if (in_array(self::CODIGO_ENCUESTA, $codigos, true) && $linkEncuesta !== null) {
            $cuerpo .= "\n\nEncuesta de satisfacción del proveedor:\n".$linkEncuesta;
        }

        return $cuerpo;
    }

    /**
     * @return array{bytes: string, filename: string, mime: string}|null
     */
    private function generarPdf(int $recepcionId): ?array
    {
        try {
            $doc = $this->pdfService->generarComPdf($recepcionId);

            return [
                'bytes' => $doc['bytes'],
                'filename' => $doc['filename'],
                'mime' => 'application/pdf',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function aplicarPlaceholders(
        string $plantilla,
        array $placeholders,
        ?string $linkConsulta,
        ?string $linkEncuesta,
    ): string {
        $mapa = array_merge($placeholders, [
            'link_consulta' => $linkConsulta ?? '',
            'link_encuesta' => $linkEncuesta ?? '',
        ]);

        $resultado = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m) use ($mapa) {
            $clave = strtolower($m[1]);

            return $mapa[$clave] ?? $m[0];
        }, $plantilla);

        return is_string($resultado) ? $resultado : $plantilla;
    }
}
