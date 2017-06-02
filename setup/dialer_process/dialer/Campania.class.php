<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 1.2-2                                               |
  | http://www.elastix.com                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2006 Palosanto Solutions S. A.                         |
  +----------------------------------------------------------------------+
  | Cdla. Nueva Kennedy Calle E 222 y 9na. Este                          |
  | Telfs. 2283-268, 2294-440, 2284-356                                  |
  | Guayaquil - Ecuador                                                  |
  | http://www.palosanto.com                                             |
  +----------------------------------------------------------------------+
  | The contents of this file are subject to the General Public License  |
  | (GPL) Version 2 (the "License"); you may not use this file except in |
  | compliance with the License. You may obtain a copy of the License at |
  | http://www.opensource.org/licenses/gpl-license.php                   |
  |                                                                      |
  | Software distributed under the License is distributed on an "AS IS"  |
  | basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See  |
  | the License for the specific language governing rights and           |
  | limitations under the License.                                       |
  +----------------------------------------------------------------------+
  | The Original Code is: Elastix Open Source.                           |
  | The Initial Developer of the Original Code is PaloSanto Solutions    |
  +----------------------------------------------------------------------+
  $Id: Campania.class.php,v 1.48 2009/03/26 13:46:58 alex Exp $ */

/* Número de llamadas por campaña para las que se lleva la cuenta de cuánto
 * tardó en ser contestada */
define('NUM_LLAMADAS_HISTORIAL_CONTESTADA', 20);

class Campania
{
    // Relaciones con otros objetos conocidos
    private $_log;
    private $_tuberia;

    var $id;                // ID en base de datos de la campaña
    var $name;              // Nombre de la campaña
    var $queue;             // Número de la cola que recibe las llamadas
    var $datetime_init;     // Fecha yyyy-mm-dd del inicio de vigencia de campaña
    var $datetime_end;      // Fecha yyyy-mm-dd del final de vigencia de campaña
    var $daytime_init;      // Hora hh:mm:ss del inicio del horario de la campaña
    var $daytime_end;       // Hora hh:mm:ss del final del horario de la campaña
    var $tipo_campania;     // Tipo de campaña 'outgoing' o 'incoming'

    // Variables sólo para campañas salientes
    var $trunk;             // Troncal a usar para la campaña, o NULL para plan marcado
    var $context;           // Contexto para marcado de la campaña
    private $_num_completadas;   // Número de llamadas completadas
    private $_promedio;          // Promedio de la duración de la llamada, en segundos
    private $_desviacion;        // Desviación estándar en el promedio de duración
    private $_variancia = 0;

    // Muestra de cuánto se tardaron las últimas llamadas en ser contestadas
    private $_historial_contestada = array();
    private $_iTiempoContestacion = 8;

    // Variables sólo para campañas entrantes
    var $id_queue_call_entry;   // ID de la cola registrada como entrante

    function __construct($tuberia, $log)
    {
        $this->_tuberia = $tuberia;
        $this->_log = $log;
    }

    public function __toString()
    {
        return "ID={$this->id} {$this->tipo_campania} name={$this->name}";
    }

    public function dump($log)
    {
        $s = "----- CAMPAÑA -----\n";
        $s .= "\tid......................".$this->id."\n";
        $s .= "\tname....................".$this->name."\n";
        $s .= "\tqueue...................".$this->queue."\n";
        $s .= "\tdatetime_init...........".$this->datetime_init."\n";
        $s .= "\tdatetime_end............".$this->datetime_end."\n";
        $s .= "\tdaytime_init............".$this->daytime_init."\n";
        $s .= "\tdaytime_end.............".$this->daytime_end."\n";
        $s .= "\ttipo_campania...........".$this->tipo_campania."\n";
        $s .= "\t_iTiempoContestacion....".$this->_iTiempoContestacion."\n";
        $s .= "\t_historial_contestada...[".implode(' ', $this->_historial_contestada)."]\n";
        if ($this->tipo_campania != 'incoming') {
            $s .= "\ttrunk...................".(is_null($this->trunk) ? '(por plan de marcado)' : $this->trunk)."\n";
            $s .= "\tcontext.................".$this->context."\n";
            $s .= "\t_num_completadas........".$this->_num_completadas."\n";
            $s .= "\t_promedio...............".(is_null($this->_promedio) ? 'N/D' : $this->_promedio)."\n";
            $s .= "\t_desviacion.............".(is_null($this->_desviacion) ? 'N/D' : $this->_desviacion)."\n";
            $s .= "\t_variancia..............".(is_null($this->_variancia) ? 'N/D' : $this->_variancia)."\n";
        } elseif ($this->tipo_campania == 'incoming') {
            $s .= "\tid_queue_call_entry.....".$this->id_queue_call_entry."\n";
        }
        $log->output($s);
    }

    function estadisticasIniciales($num, $prom, $stddev)
    {
    	$this->_num_completadas = $num;
        $this->_promedio = $prom;
        $this->_desviacion = $stddev;
        $this->_variancia = $stddev * $stddev;
    }

    function tiempoContestarOmision($i) { $this->_iTiempoContestacion = (int)$i; }

    /* Procedimiento que actualiza la lista de las últimas llamadas que fueron
     * contestadas o perdidas.
     */
    function agregarTiempoContestar($iMuestra)
    {
        array_push($this->_historial_contestada, $iMuestra);
        while (count($this->_historial_contestada) > NUM_LLAMADAS_HISTORIAL_CONTESTADA)
            array_shift($this->_historial_contestada);
    }

    function leerTiempoContestar()
    {
        $iNumElems = count($this->_historial_contestada);
        $iSuma = array_sum($this->_historial_contestada);
        if ($iNumElems < NUM_LLAMADAS_HISTORIAL_CONTESTADA) {
            $iSuma += $this->_iTiempoContestacion * (NUM_LLAMADAS_HISTORIAL_CONTESTADA - $iNumElems);
            $iNumElems = NUM_LLAMADAS_HISTORIAL_CONTESTADA;
        }
        $iTiempoContestar = $iSuma / $iNumElems;

        return $iTiempoContestar;
    }

    // Calcular promedio y desviación estándar
    function actualizarEstadisticas($iDuracionLlamada)
    {
    	if (is_null($this->_num_completadas)) $this->_num_completadas = 0;

        // Calcular nuevo promedio
        if ($this->_num_completadas > 0) {
            $iNuevoPromedio = $this->_nuevoPromedio($this->_promedio,
                $this->_num_completadas, $iDuracionLlamada);
        } else {
            $iNuevoPromedio = $iDuracionLlamada;
        }

        // Calcular nueva desviación estándar
        if ($this->_num_completadas > 1) {
            $iNuevaVariancia = $this->_nuevaVarianciaMuestra($this->_promedio,
                $iNuevoPromedio, $this->_num_completadas, $this->_variancia,
                $iDuracionLlamada);
        } else if ($this->_num_completadas == 1) {
            $iViejoPromedio = $this->_promedio;
            $iNuevaVariancia =
                ($iViejoPromedio - $iNuevoPromedio) * ($iViejoPromedio - $iNuevoPromedio) +
                ($iDuracionLlamada - $iNuevoPromedio) * ($iDuracionLlamada - $iNuevoPromedio);
        } else {
            $iNuevaVariancia = 0;
        }

        $this->_num_completadas++;
        $this->_promedio = $iNuevoPromedio;
        $this->_variancia = $iNuevaVariancia;
        $this->_desviacion = sqrt($this->_variancia);

        $this->_tuberia->msg_SQLWorkerProcess_sqlupdatestatcampaign($this->id,
            $this->_num_completadas, $this->_promedio, $this->_desviacion);
    }

    private function _nuevoPromedio($iViejoProm, $n, $x)
    {
        return $iViejoProm + ($x - $iViejoProm) / ($n + 1);
    }

    private function _nuevaVarianciaMuestra($iViejoProm, $iNuevoProm, $n, $iViejaVar, $x)
    {
        return ($n * $iViejaVar + ($x - $iNuevoProm) * ($x - $iViejoProm)) / ($n + 1);
    }

    public function enHorarioVigencia($iTimestamp)
    {
        $sFecha = date('Y-m-d', $iTimestamp);
        $sHora = date('H:i:s', $iTimestamp);
        return (
            $this->datetime_init <= $sFecha &&
            $sFecha <= $this->datetime_end &&
            (   ($this->daytime_init <= $this->daytime_end &&
                $this->daytime_init <= $sHora &&
                $sHora <= $this->daytime_end)
                ||
                ($this->daytime_init > $this->daytime_end &&
                ($this->daytime_init <= $sHora ||
                $sHora <= $this->daytime_end))
            )
        );

    }
}
?>