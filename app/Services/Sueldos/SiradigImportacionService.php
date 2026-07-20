<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Siradig_Concepto_Sueldos;
use App\Models\Sueldos\Siradig_Otro_Empleador_Sueldos;
use App\Models\Sueldos\Siradig_Presentacion_Sueldos;
use App\Support\Sueldos\SiradigTablas;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lectura e importación del F572 Web (SiRADIG - ARCA).
 *
 * No hay web service: se lee el/los XML descargados de "SiRADIG Empleador"
 * (resultadosXML.zip => 1 XML por empleado; sección A y, en pluriempleo, sección B).
 *
 * Criterio de vigencia (unánime en el mercado): la presentación importada reemplaza
 * la anterior del mismo año fiscal para ese CUIL. Se conserva el histórico y se marca
 * como `vigente` la de mayor nro_presentacion por (empresa, cuil, período, sección).
 */
class SiradigImportacionService
{
    /**
     * Mapeo XML (IngresoAporteType) => columnas de siradig_otro_empleador_mes_sueldos.
     *
     * @var array<string, string>
     */
    private const MAP_INGRESO_APORTE = [
        'obraSoc' => 'obra_soc',
        'segSoc' => 'seg_soc',
        'segSocANSES' => 'seg_soc_anses',
        'segSocCajas' => 'seg_soc_cajas',
        'sind' => 'sind',
        'ganBrut' => 'gan_brut',
        'retGan' => 'ret_gan',
        'retribNoHab' => 'retrib_no_hab',
        'ajuste' => 'ajuste',
        'ajusteRemGravadas' => 'ajuste_rem_gravadas',
        'ajusteRemExeNoAlcanzadas' => 'ajuste_rem_exe_no_alcanzadas',
        'exeNoAlc' => 'exe_no_alc',
        'asignFam' => 'asign_fam',
        'intPrestEmp' => 'int_prest_emp',
        'remunJudiciales' => 'remun_judiciales',
        'indemLey4003' => 'indem_ley4003',
        'remunLey19640' => 'remun_ley19640',
        'remunCctPetro' => 'remun_cct_petro',
        'cursosSemin' => 'cursos_semin',
        'indumEquipEmp' => 'indum_equip_emp',
        'sac' => 'sac',
        'horasExtGr' => 'horas_ext_gr',
        'horasExtEx' => 'horas_ext_ex',
        'matDid' => 'mat_did',
        'movilidad' => 'movilidad',
        'viaticos' => 'viaticos',
        'otrosConAn' => 'otros_con_an',
        'bonosProd' => 'bonos_prod',
        'fallosCaja' => 'fallos_caja',
        'conSimNat' => 'con_sim_nat',
        'remunExentaLey27549' => 'remun_exenta_ley27549',
        'suplemParticLey19101' => 'suplem_partic_ley19101',
        'teletrabajoExento' => 'teletrabajo_exento',
        'noRetMedCaut' => 'no_ret_med_caut',
    ];

    /** Detalles de conceptos análogos por mes: elemento XML => grupo persistido. */
    private const DETALLE_INGRESO_APORTE = [
        'otrosConAnDetalle' => 'otrosConAn',
        'bonosProdDetalle' => 'bonosProd',
        'fallosCajaDetalle' => 'fallosCaja',
        'conSimNatDetalle' => 'conSimNat',
    ];

    /**
     * Importa un ZIP (resultadosXML.zip) con uno o varios XML.
     *
     * @return array{importadas: list<Siradig_Presentacion_Sueldos>, omitidas: list<string>, errores: array<string, string>}
     */
    public function importarZip(string $rutaZip, int $empresaId, ?int $importadoPorId = null): array
    {
        if (! is_file($rutaZip)) {
            throw new InvalidArgumentException("No se encontró el archivo ZIP: {$rutaZip}");
        }

        $zip = new ZipArchive;
        if ($zip->open($rutaZip) !== true) {
            throw new RuntimeException("No se pudo abrir el ZIP: {$rutaZip}");
        }

        $importadas = [];
        $omitidas = [];
        $errores = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = (string) $zip->getNameIndex($i);
            if (! str_ends_with(strtolower($nombre), '.xml')) {
                continue;
            }

            $contenido = $zip->getFromIndex($i);
            if ($contenido === false || trim($contenido) === '') {
                $errores[$nombre] = 'Archivo vacío o ilegible';

                continue;
            }

            try {
                $presentacion = $this->importarXml($contenido, $empresaId, basename($nombre), $importadoPorId);
                if ($presentacion === null) {
                    $omitidas[] = $nombre;

                    continue;
                }
                $importadas[] = $presentacion;
            } catch (\Throwable $e) {
                $errores[$nombre] = $e->getMessage();
            }
        }

        $zip->close();

        return ['importadas' => $importadas, 'omitidas' => $omitidas, 'errores' => $errores];
    }

    /**
     * Importa un XML (sección A o B). Devuelve null si el archivo ya estaba importado
     * (mismo contenido / hash) para evitar duplicados.
     */
    public function importarXml(
        string $xml,
        int $empresaId,
        ?string $archivoNombre = null,
        ?int $importadoPorId = null
    ): ?Siradig_Presentacion_Sueldos {
        $datos = $this->parsear($xml);
        $hash = hash('sha256', $xml);

        $duplicada = Siradig_Presentacion_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->where('archivo_hash', $hash)
            ->exists();

        if ($duplicada) {
            return null;
        }

        $empleadoId = $this->resolverEmpleadoId($empresaId, $datos['empleado']['cuit']);

        return DB::transaction(function () use ($datos, $empresaId, $empleadoId, $archivoNombre, $hash, $xml, $importadoPorId) {
            /** @var Siradig_Presentacion_Sueldos $presentacion */
            $presentacion = Siradig_Presentacion_Sueldos::create([
                'empresa_id' => $empresaId,
                'empleado_id' => $empleadoId,
                'seccion' => $datos['seccion'],
                'version' => $datos['version'],
                'periodo' => $datos['periodo'],
                'nro_presentacion' => $datos['nro_presentacion'],
                'fecha_presentacion' => $datos['fecha_presentacion'],
                'empleado_cuit' => $datos['empleado']['cuit'],
                'empleado_tipo_doc' => $datos['empleado']['tipo_doc'],
                'empleado_apellido' => $datos['empleado']['apellido'],
                'empleado_nombre' => $datos['empleado']['nombre'],
                'dom_provincia' => $datos['empleado']['dom_provincia'],
                'dom_cp' => $datos['empleado']['dom_cp'],
                'dom_localidad' => $datos['empleado']['dom_localidad'],
                'dom_calle' => $datos['empleado']['dom_calle'],
                'dom_nro' => $datos['empleado']['dom_nro'],
                'dom_piso' => $datos['empleado']['dom_piso'],
                'dom_dpto' => $datos['empleado']['dom_dpto'],
                'agente_retencion_cuit' => $datos['agente_retencion']['cuit'],
                'agente_retencion_denominacion' => $datos['agente_retencion']['denominacion'],
                'es_agente_retencion' => $datos['seccion'] === 'A',
                'vigente' => true,
                'archivo_nombre' => $archivoNombre,
                'archivo_hash' => $hash,
                'xml_crudo' => $xml,
                'importado_por_id' => $importadoPorId,
                'importado_at' => now(),
            ]);

            $this->guardarCargasFamilia($presentacion, $datos['cargas_familia']);
            $this->guardarOtrosEmpleadores($presentacion, $datos['otros_empleadores']);
            $this->guardarConceptos($presentacion, $datos['conceptos']);
            $this->guardarDatosAdicionales($presentacion, $datos['datos_adicionales']);

            $this->recalcularVigencia($presentacion);

            return $presentacion->refresh();
        });
    }

    /**
     * Parsea un XML F572 (sección A o B) a una estructura normalizada.
     *
     * @return array<string, mixed>
     */
    public function parsear(string $xml): array
    {
        $usarErrores = libxml_use_internal_errors(true);
        $raiz = simplexml_load_string($xml);
        libxml_use_internal_errors($usarErrores);

        if ($raiz === false) {
            throw new InvalidArgumentException('El archivo no es un XML válido de SiRADIG.');
        }

        $esSeccionB = isset($raiz->agenteRetencion);

        $cuit = $this->soloDigitos((string) $raiz->empleado->cuit);
        if ($cuit === '') {
            throw new InvalidArgumentException('El XML no contiene el CUIL del empleado.');
        }

        return [
            'seccion' => $esSeccionB ? 'B' : 'A',
            'version' => $this->attr($raiz, 'version'),
            'periodo' => (int) (string) $raiz->periodo,
            'nro_presentacion' => (int) (string) $raiz->nroPresentacion,
            'fecha_presentacion' => $this->fecha((string) $raiz->fechaPresentacion),
            'empleado' => $this->parsearEmpleado($raiz->empleado),
            'agente_retencion' => [
                'cuit' => $esSeccionB ? $this->soloDigitos((string) $raiz->agenteRetencion->cuit) : null,
                'denominacion' => $esSeccionB ? $this->texto((string) $raiz->agenteRetencion->denominacion) : null,
            ],
            'cargas_familia' => $esSeccionB ? [] : $this->parsearCargasFamilia($raiz),
            'otros_empleadores' => $esSeccionB ? [] : $this->parsearOtrosEmpleadores($raiz),
            'conceptos' => $esSeccionB ? [] : $this->parsearConceptos($raiz),
            'datos_adicionales' => $esSeccionB ? [] : $this->parsearDatosAdicionales($raiz),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parsearEmpleado(SimpleXMLElement $empleado): array
    {
        $dir = $empleado->direccion;

        return [
            'cuit' => $this->soloDigitos((string) $empleado->cuit),
            'tipo_doc' => $this->entero((string) $empleado->tipoDoc),
            'apellido' => $this->texto((string) $empleado->apellido),
            'nombre' => $this->texto((string) $empleado->nombre),
            'dom_provincia' => isset($dir->provincia) ? $this->entero((string) $dir->provincia) : null,
            'dom_cp' => isset($dir->cp) ? $this->texto((string) $dir->cp) : null,
            'dom_localidad' => isset($dir->localidad) ? $this->texto((string) $dir->localidad) : null,
            'dom_calle' => isset($dir->calle) ? $this->texto((string) $dir->calle) : null,
            'dom_nro' => isset($dir->nro) ? $this->texto((string) $dir->nro) : null,
            'dom_piso' => isset($dir->piso) ? $this->texto((string) $dir->piso) : null,
            'dom_dpto' => isset($dir->dpto) ? $this->texto((string) $dir->dpto) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsearCargasFamilia(SimpleXMLElement $raiz): array
    {
        $cargas = [];
        if (! isset($raiz->cargasFamilia->cargaFamilia)) {
            return $cargas;
        }

        foreach ($raiz->cargasFamilia->cargaFamilia as $cf) {
            $cargas[] = [
                'tipo_doc' => $this->entero((string) $cf->tipoDoc),
                'nro_doc' => $this->texto((string) $cf->nroDoc),
                'apellido' => $this->texto((string) $cf->apellido),
                'nombre' => $this->texto((string) $cf->nombre),
                'fecha_nac' => $this->fecha((string) $cf->fechaNac),
                'mes_desde' => $this->entero((string) $cf->mesDesde),
                'mes_hasta' => $this->entero((string) $cf->mesHasta),
                'parentesco' => $this->entero((string) $cf->parentesco),
                'vigente_proximos_periodos' => isset($cf->vigenteProximosPeriodos) ? $this->texto((string) $cf->vigenteProximosPeriodos) : null,
                'fecha_limite' => isset($cf->fechaLimite) ? $this->fecha((string) $cf->fechaLimite) : null,
                'porcentaje_deduccion' => isset($cf->porcentajeDeduccion) ? $this->entero((string) $cf->porcentajeDeduccion) : null,
            ];
        }

        return $cargas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsearOtrosEmpleadores(SimpleXMLElement $raiz): array
    {
        $empleadores = [];
        if (! isset($raiz->ganLiqOtrosEmpEnt->empEnt)) {
            return $empleadores;
        }

        foreach ($raiz->ganLiqOtrosEmpEnt->empEnt as $emp) {
            $meses = [];
            if (isset($emp->ingresosAportes->ingAp)) {
                foreach ($emp->ingresosAportes->ingAp as $ingAp) {
                    $meses[] = $this->parsearIngresoAporte($ingAp);
                }
            }

            $empleadores[] = [
                'cuit' => $this->soloDigitos((string) $emp->cuit),
                'denominacion' => $this->texto((string) $emp->denominacion),
                'convenio_colectivo' => isset($emp->convenioColectivo) ? $this->texto((string) $emp->convenioColectivo) : null,
                'transporte_larga_dist' => isset($emp->transporteLargaDist) ? $this->texto((string) $emp->transporteLargaDist) : null,
                'transporte_terr_larga_dist' => isset($emp->transporteTerrLargaDist) ? $this->texto((string) $emp->transporteTerrLargaDist) : null,
                'meses' => $meses,
            ];
        }

        return $empleadores;
    }

    /**
     * @return array<string, mixed>
     */
    private function parsearIngresoAporte(SimpleXMLElement $ingAp): array
    {
        $mes = [
            'mes' => $this->entero($this->attr($ingAp, 'mes')),
            'regimen' => $this->texto($this->attr($ingAp, 'regimen')),
            'detalles' => [],
        ];

        foreach (self::MAP_INGRESO_APORTE as $xmlName => $columna) {
            $mes[$columna] = isset($ingAp->{$xmlName}) ? $this->numero((string) $ingAp->{$xmlName}) : null;
        }

        foreach (self::DETALLE_INGRESO_APORTE as $xmlName => $grupo) {
            if (! isset($ingAp->{$xmlName}->concepto)) {
                continue;
            }
            foreach ($ingAp->{$xmlName}->concepto as $concepto) {
                $mes['detalles'][] = [
                    'grupo' => $grupo,
                    'descripcion' => $this->texto($this->attr($concepto, 'descripcion')),
                    'monto' => $this->numero($this->attr($concepto, 'monto')),
                ];
            }
        }

        return $mes;
    }

    /**
     * Deducciones + retenciones/percepciones/pagos + ajustes en una sola lista.
     *
     * @return list<array<string, mixed>>
     */
    private function parsearConceptos(SimpleXMLElement $raiz): array
    {
        $conceptos = [];

        if (isset($raiz->deducciones->deduccion)) {
            foreach ($raiz->deducciones->deduccion as $item) {
                $conceptos[] = $this->parsearConcepto($item, SiradigTablas::GRUPO_DEDUCCION);
            }
        }

        if (isset($raiz->retPerPagos->retPerPago)) {
            foreach ($raiz->retPerPagos->retPerPago as $item) {
                $conceptos[] = $this->parsearConcepto($item, SiradigTablas::GRUPO_RETENCION);
            }
        }

        if (isset($raiz->ajustes->ajuste)) {
            foreach ($raiz->ajustes->ajuste as $item) {
                $conceptos[] = $this->parsearConcepto($item, SiradigTablas::GRUPO_AJUSTE);
            }
        }

        return $conceptos;
    }

    /**
     * @return array<string, mixed>
     */
    private function parsearConcepto(SimpleXMLElement $item, string $grupo): array
    {
        $periodos = [];
        if (isset($item->periodos->periodo)) {
            foreach ($item->periodos->periodo as $per) {
                $periodos[] = [
                    'mes_desde' => $this->entero($this->attr($per, 'mesDesde')),
                    'mes_hasta' => $this->entero($this->attr($per, 'mesHasta')),
                    'monto_mensual' => $this->numero($this->attr($per, 'montoMensual')),
                ];
            }
        }

        $detalles = [];
        if (isset($item->detalles->detalle)) {
            foreach ($item->detalles->detalle as $det) {
                $detalles[] = [
                    'nombre' => $this->texto($this->attr($det, 'nombre')),
                    'valor' => $this->texto($this->attr($det, 'valor')),
                ];
            }
        }

        return [
            'grupo' => $grupo,
            'tipo' => $this->entero($this->attr($item, 'tipo')) ?? 0,
            'tipo_doc' => isset($item->tipoDoc) ? $this->entero((string) $item->tipoDoc) : null,
            'nro_doc' => isset($item->nroDoc) ? $this->texto((string) $item->nroDoc) : null,
            'cuit' => isset($item->cuit) ? $this->soloDigitos((string) $item->cuit) : null,
            'denominacion' => isset($item->denominacion) ? $this->texto((string) $item->denominacion) : null,
            'desc_basica' => isset($item->descBasica) ? $this->texto((string) $item->descBasica) : null,
            'desc_adicional' => isset($item->descAdicional) ? $this->texto((string) $item->descAdicional) : null,
            'monto_total' => $this->numero((string) $item->montoTotal) ?? 0,
            'periodos' => $periodos,
            'detalles' => $detalles,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsearDatosAdicionales(SimpleXMLElement $raiz): array
    {
        $datos = [];
        if (! isset($raiz->datosAdicionales->datoAdicional)) {
            return $datos;
        }

        foreach ($raiz->datosAdicionales->datoAdicional as $da) {
            $datos[] = [
                'nombre' => $this->texto($this->attr($da, 'nombre')),
                'mes_desde' => $this->entero($this->attr($da, 'mesDesde')),
                'mes_hasta' => $this->entero($this->attr($da, 'mesHasta')),
                'valor' => $this->texto($this->attr($da, 'valor')),
            ];
        }

        return $datos;
    }

    /**
     * @param  list<array<string, mixed>>  $cargas
     */
    private function guardarCargasFamilia(Siradig_Presentacion_Sueldos $presentacion, array $cargas): void
    {
        foreach ($cargas as $carga) {
            $presentacion->cargasFamilia()->create($carga);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $empleadores
     */
    private function guardarOtrosEmpleadores(Siradig_Presentacion_Sueldos $presentacion, array $empleadores): void
    {
        foreach ($empleadores as $emp) {
            $meses = $emp['meses'];
            unset($emp['meses']);

            /** @var Siradig_Otro_Empleador_Sueldos $otroEmpleador */
            $otroEmpleador = $presentacion->otrosEmpleadores()->create($emp);

            foreach ($meses as $mes) {
                $detalles = $mes['detalles'];
                unset($mes['detalles']);

                $mesModelo = $otroEmpleador->meses()->create($mes);
                foreach ($detalles as $detalle) {
                    $mesModelo->detalles()->create($detalle);
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     */
    private function guardarConceptos(Siradig_Presentacion_Sueldos $presentacion, array $conceptos): void
    {
        foreach ($conceptos as $concepto) {
            $periodos = $concepto['periodos'];
            $detalles = $concepto['detalles'];
            unset($concepto['periodos'], $concepto['detalles']);

            /** @var Siradig_Concepto_Sueldos $conceptoModelo */
            $conceptoModelo = $presentacion->conceptos()->create($concepto);

            foreach ($periodos as $periodo) {
                $conceptoModelo->periodos()->create($periodo);
            }
            foreach ($detalles as $detalle) {
                $conceptoModelo->detalles()->create($detalle);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $datos
     */
    private function guardarDatosAdicionales(Siradig_Presentacion_Sueldos $presentacion, array $datos): void
    {
        foreach ($datos as $dato) {
            $presentacion->datosAdicionales()->create($dato);
        }
    }

    /**
     * Marca como vigente la presentación de mayor nro_presentacion por
     * (empresa, cuil, período, sección) y las demás como no vigentes.
     */
    private function recalcularVigencia(Siradig_Presentacion_Sueldos $presentacion): void
    {
        $this->recalcularVigenciaClaves(
            (int) $presentacion->empresa_id,
            (string) $presentacion->empleado_cuit,
            (int) $presentacion->periodo,
            (string) $presentacion->seccion
        );
    }

    /**
     * Recalcula la vigencia de un grupo (empresa, cuil, período, sección).
     * Útil tras eliminar una presentación para promover la anterior.
     */
    public function recalcularVigenciaClaves(int $empresaId, string $cuit, int $periodo, string $seccion): void
    {
        $grupo = Siradig_Presentacion_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->where('empleado_cuit', $cuit)
            ->where('periodo', $periodo)
            ->where('seccion', $seccion);

        $vigenteId = (int) ($grupo->clone()
            ->orderByDesc('nro_presentacion')
            ->orderByDesc('id')
            ->value('id') ?? 0);

        if ($vigenteId === 0) {
            return;
        }

        $grupo->clone()->where('id', '!=', $vigenteId)->update(['vigente' => false]);
        $grupo->clone()->where('id', $vigenteId)->update(['vigente' => true]);
    }

    private function resolverEmpleadoId(int $empresaId, string $cuit): ?int
    {
        $cuit = $this->soloDigitos($cuit);
        if ($cuit === '') {
            return null;
        }

        return Empleado_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->whereRaw("REPLACE(REPLACE(cuil, '-', ''), ' ', '') = ?", [$cuit])
            ->value('id');
    }

    private function attr(SimpleXMLElement $nodo, string $nombre): ?string
    {
        $attrs = $nodo->attributes();
        if ($attrs === null || ! isset($attrs[$nombre])) {
            return null;
        }

        return (string) $attrs[$nombre];
    }

    private function texto(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function entero(?string $valor): ?int
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : (int) $valor;
    }

    private function numero(?string $valor): ?float
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : (float) $valor;
    }

    private function fecha(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function soloDigitos(?string $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor) ?? '';
    }
}
