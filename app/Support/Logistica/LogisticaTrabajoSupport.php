<?php

namespace App\Support\Logistica;

use App\Models\Compras\Ordencompra;
use App\Models\Logistica\LogisticaTipoSolicitud;
use App\Models\Logistica\LogisticaTrabajoTipo;
use App\Models\Logistica\LogisticaUbicacion;
use App\Models\Logistica\SolicitudLogistica;
use App\Models\Logistica\SolicitudLogisticaArchivo;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LogisticaTrabajoSupport
{
    private const RANGO = [
        'Baja' => 1,
        'Normal' => 2,
        'Media' => 2,
        'Alta' => 3,
        'Urgente' => 4,
    ];

    /**
     * @param  list<int>  $empresasPermitidas
     */
    public static function crear(int $usuarioId, int $centrocostoId, Request $request, array $empresasPermitidas): SolicitudLogistica
    {
        $tipo = LogisticaTipoSolicitud::query()->where('codigo', 'trabajos')->where('activo', true)->first();
        if ($tipo === null || ! LogisticaVisibilidadSupport::habilitado('tipo', $usuarioId, (int) $tipo->id)) {
            throw new RuntimeException('No podés solicitar trabajos.');
        }

        $codigo = (string) $request->input('trabajo_codigo', '');
        $trabajo = LogisticaTrabajoTipo::query()->where('codigo', $codigo)->where('activo', true)->first();
        if ($trabajo === null) {
            throw new RuntimeException('Elegí un tipo de trabajo.');
        }

        $prioridad = self::prioridadAceptada($trabajo, (string) $request->input('prioridad', ''));
        $datos = self::datosPorCodigo($trabajo->codigo, $request, $empresasPermitidas);

        return DB::transaction(function () use ($usuarioId, $centrocostoId, $tipo, $trabajo, $prioridad, $datos, $request) {
            $solicitud = SolicitudLogistica::query()->create(array_merge($datos, [
                'numero' => SolicitudLogistica::siguienteNumero(),
                'fecha' => now()->toDateString(),
                'usuario_id' => $usuarioId,
                'centrocosto_id' => $centrocostoId,
                'tipo_solicitud_id' => $tipo->id,
                'trabajo_tipo_id' => $trabajo->id,
                'prioridad' => $prioridad,
                'estado' => 'enviada',
                'total_estimado' => 0,
                'responsable_snapshot' => $trabajo->responsable,
            ]));

            self::guardarArchivo($solicitud, $request->file('foto'), $trabajo->codigo === 'retiro');
            LogisticaPlazoSupport::aplicar($solicitud);
            if ($trabajo->codigo === 'butacas') {
                $solicitud->load('ubicacionDestino:id,nombre');
                LogisticaUidSupport::registrar($solicitud, $usuarioId);
            }

            return $solicitud->fresh(['trabajoTipo', 'tipo']);
        });
    }

    /**
     * @param  list<int>  $empresasPermitidas
     * @return array<string, mixed>
     */
    private static function datosPorCodigo(string $codigo, Request $request, array $empresasPermitidas): array
    {
        return match ($codigo) {
            'slots' => self::datosSlots($request),
            'retiro' => self::datosRetiro($request, $empresasPermitidas),
            'elementos' => self::datosElementos($request),
            'butacas' => self::datosButacas($request),
            default => throw new RuntimeException('Ese tipo de trabajo no tiene formulario.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function datosSlots(Request $request): array
    {
        $origen = self::ubicacion((int) $request->input('ubicacion_origen_id', 0));
        $destino = self::ubicacion((int) $request->input('ubicacion_destino_id', 0));
        if ($origen->id === $destino->id) {
            throw new RuntimeException('El origen y el destino tienen que ser distintos.');
        }
        $cantidad = (float) $request->input('cantidad', 0);
        if ($cantidad < 1) {
            throw new RuntimeException('Indicá la cantidad de slots.');
        }
        $fecha = (string) $request->input('fecha_tentativa', '');
        if ($fecha === '' || strtotime($fecha) === false) {
            throw new RuntimeException('Indicá la fecha tentativa.');
        }
        $accesorios = $request->boolean('accesorios');
        $detalleAccesorios = trim((string) $request->input('accesorios_detalle', ''));
        if ($accesorios && $detalleAccesorios === '') {
            throw new RuntimeException('Indicá qué accesorios van con los slots.');
        }

        return [
            'ubicacion_origen_id' => $origen->id,
            'ubicacion_destino_id' => $destino->id,
            'cantidad' => $cantidad,
            'fecha_tentativa' => $fecha,
            'detalle' => trim((string) $request->input('detalle', '')) ?: null,
            'accesorios' => $accesorios,
            'accesorios_detalle' => $accesorios ? $detalleAccesorios : null,
        ];
    }

    /**
     * @param  list<int>  $empresasPermitidas
     * @return array<string, mixed>
     */
    private static function datosRetiro(Request $request, array $empresasPermitidas): array
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 || ! in_array($empresaId, $empresasPermitidas, true)) {
            throw new RuntimeException('Elegí la empresa del retiro.');
        }
        $orden = self::ordencompra((string) $request->input('ordencompra_numero', ''));
        if ((int) $orden->empresa_id !== $empresaId) {
            throw new RuntimeException('La orden de compra no es de la empresa elegida.');
        }
        $direccion = trim((string) $request->input('direccion_retiro', ''));
        if ($direccion === '') {
            $direccion = trim((string) ($orden->proveedores->domicilio ?? ''));
        }
        if ($direccion === '') {
            throw new RuntimeException('La orden no tiene domicilio de proveedor. Cargá la dirección de retiro.');
        }

        return [
            'empresa_id' => $empresaId,
            'ordencompra_id' => $orden->id,
            'direccion_retiro' => mb_substr($direccion, 0, 180),
            'detalle' => trim((string) $request->input('detalle', '')) ?: null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function datosElementos(Request $request): array
    {
        $motivo = (string) $request->input('motivo', '');
        if (! in_array($motivo, ['resguardo', 'destruccion'], true)) {
            throw new RuntimeException('Elegí si el traslado es por resguardo o por destrucción.');
        }
        $ubicacion = self::ubicacion((int) $request->input('ubicacion_origen_id', 0));
        $detalle = trim((string) $request->input('detalle', ''));
        if ($detalle === '') {
            throw new RuntimeException('Describí los elementos a trasladar.');
        }

        return [
            'motivo' => $motivo,
            'ubicacion_origen_id' => $ubicacion->id,
            'detalle' => $detalle,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function datosButacas(Request $request): array
    {
        $tipo = (string) $request->input('tipo_butaca', '');
        if (! in_array($tipo, ['vip', 'especial', 'comunes'], true)) {
            throw new RuntimeException('Elegí el tipo de butaca.');
        }
        $cantidad = (int) $request->input('cantidad', 0);
        if ($cantidad < 1 || $cantidad > 10) {
            throw new RuntimeException('La cantidad de butacas va de 1 a 10.');
        }

        return [
            'tipo_butaca' => $tipo,
            'cantidad' => $cantidad,
            'uid_bien' => trim((string) $request->input('uid_bien', '')) ?: null,
            'detalle' => trim((string) $request->input('detalle', '')) ?: null,
        ];
    }

    private static function prioridadAceptada(LogisticaTrabajoTipo $trabajo, string $pedida): string
    {
        $piso = (string) $trabajo->prioridad_piso;
        if (! isset(self::RANGO[$piso])) {
            $piso = 'Media';
        }
        if ($pedida === '' || ! isset(self::RANGO[$pedida])) {
            return $piso;
        }
        if (self::RANGO[$pedida] < self::RANGO[$piso]) {
            throw new RuntimeException('La prioridad no puede ser menor que '.$piso.'.');
        }

        return $pedida;
    }

    private static function ubicacion(int $id): LogisticaUbicacion
    {
        $ubicacion = LogisticaUbicacion::query()->whereKey($id)->where('activo', true)->first();
        if ($ubicacion === null) {
            throw new RuntimeException('Elegí una ubicación válida.');
        }

        return $ubicacion;
    }

    private static function ordencompra(string $numero): Ordencompra
    {
        $numero = trim($numero);
        if ($numero === '' || ! ctype_digit($numero)) {
            throw new RuntimeException('Indicá el número de orden de compra.');
        }
        $orden = Ordencompra::query()
            ->with('proveedores:id,nombre,domicilio')
            ->where('numeroordencompra', (int) $numero)
            ->orderByDesc('id')
            ->first();
        if ($orden === null) {
            throw new RuntimeException('No existe la orden de compra '.$numero.'.');
        }

        return $orden;
    }

    private static function guardarArchivo(SolicitudLogistica $solicitud, ?UploadedFile $archivo, bool $obligatorio): void
    {
        if ($archivo === null) {
            if ($obligatorio) {
                throw new RuntimeException('Adjuntá la foto de la orden de compra.');
            }

            return;
        }
        $extension = strtolower($archivo->getClientOriginalExtension());
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
            throw new RuntimeException('El adjunto tiene que ser una imagen o un PDF.');
        }
        if ($archivo->getSize() > 5 * 1024 * 1024) {
            throw new RuntimeException('El adjunto no puede superar 5 MB.');
        }
        $ruta = $archivo->store('logistica/solicitudes/'.$solicitud->id, 'local');
        SolicitudLogisticaArchivo::query()->create([
            'solicitud_logistica_id' => $solicitud->id,
            'nombre' => mb_substr($archivo->getClientOriginalName(), 0, 180),
            'ruta' => $ruta,
        ]);
    }
}
