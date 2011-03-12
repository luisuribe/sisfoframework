<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * extension.php
 *
 * SF 1.0 BETA
 * Convenciones de programacion, metodos que ahorran escritura de codigo
 * 
 * PHP 5
 *
 * LICENSE:    GNU GENERAL PUBLIC LICENSE 2.0
 * http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package    SF
 * @author     Mauricio Morales <mmorales@sisfo.com>
 * @version    CVS: $Id:extension$
 * @see        include.php
 * @since      File available since Release 1.0
 */

/**
 * Convencion para operador TERNARIO
 *
 * @param boolean $condicion
 * @param mixed $verdad Valor que se retorna cuando evalua a verdad
 * @param mixed $falso  Valor que se retorna cuando evalua a falso
 */
function ife($condicion, $verdad, $falso) {
     return ($condicion) ? $verdad : $falso;
}

/**
 * Convencion para strtolower
 *
 * @param string $str
 * @return string
 */
function low($str) {
    return strtolower($str);
}

/**
 * Convencion para strtoupper
 *
 * @param string $str
 * @return string
 */
function up($str) {
    return strtoupper($str);
}

/**
 * Convencion para str_replace
 *
 * @param string $buscar
 * @param string $reemplazar
 * @param string $donde
 * @return string
 */
function r($buscar, $reemplazar, $donde) {
    return str_replace($buscar, $reemplazar, $donde);
}

/**
 * Metodo para sanear cadenas de SQL injection
 *
 * @param mixed $str
 * @return mixed
 */
function _sanear($str) {
    // esta es como un paranoid
}

/**
 * Normaliza a UTF8
 *
 * @param string $str
 * @return string
 */
function _normalizar($str) {
    
    $tmp = strtolower($str);
    $tmp = str_replace(" ", "_", $tmp);
    $tmp = str_replace("á", "a", $tmp);
    $tmp = str_replace("é", "e", $tmp);
    $tmp = str_replace("í", "i", $tmp);
    $tmp = str_replace("ó", "o", $tmp);
    $tmp = str_replace("ú", "u", $tmp);
    $tmp = str_replace("Á", "a", $tmp);
    $tmp = str_replace("É", "e", $tmp);
    $tmp = str_replace("Í", "i", $tmp);
    $tmp = str_replace("Ó", "o", $tmp);
    $tmp = str_replace("Ú", "u", $tmp);
    $tmp = str_replace("ñ", "n", $tmp);
    $tmp = str_replace("Ñ", "_", $tmp);
    $tmp = str_replace("´", "_", $tmp);
    $tmp = str_replace("`", "_", $tmp);
    
    return $tmp;
}

/**
 * Retorna cantidad de dias en un mes dado
 *
 * @param int $year
 * @param int $month
 * @return int
 */
function diasEnMes($year, $month) {
    if ($month == 2) {
          if ($year % 4 == 0 && ($year <= 1582 || ($year % 100 != 0 || $year % 400 == 0))) {
                return 29;
          }
          return 28;
    }
    if ($month > 7) $month ++;
    return ($month % 2 == 0) ? 30 : 31;
}


/**
 * Retorna verdadero si la cadena no es vacia y pasa la validacion
 * Retorna falso si la cadena es vacia
 *
 * @param mixed $str
 * @return boolean
 */
function val_noVacio($str) {
    return ife(empty($str), false, true);
}

/**
 * Valida fecha YYYY-MM-DD
 *
 * @param string $str
 * @return boolean
 */
function val_Fecha($str) {
    
    $sep = ife(strpos($str, '/') !== false, '/', '-');
    list($year, $month, $day) = split($sep, $str);
    
    if (empty($year) || empty($month) || empty($day)) {
        return false;
    }
    
    if (strlen($year) != 4) {
        return false;
    }
    
    if ($month > 12 || $day > diasEnMes($year, $month)) {
        return false;
    }
    
    return true;
}


/**
 * Valida fecha hora YYYY-MM-DD H:i
 *
 * @param string $str
 * @return boolean
 */
function val_FechaHora($str) {
    if (ereg("^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$", $str)) {
        
        $adate = substr($str, 0, 10);
        
        if (!val_Fecha($adate)) {
            return false;
        } else {
            return true;
        }
    }
    
    return false;
}

/**
 * Valida numerico
 *
 * @param string $str
 * @return boolean
 */
function val_Numerico($str) {
    return ife(is_numeric($str), true, false);
}


/**
 * Valida alfanumerico
 *
 * @param string $str
 * @return boolean
 */
function val_Alfanumerico($str) {
    
    if (ereg("^[0-9,a-z,A-Z]+$", $str)) {
        return true;
    }
    
    return false;
}


/**
 * Valida longitud
 *
 * @param string $str
 * @param int $longitud
 * @return boolean
 */
function val_Longitud($str, $long) {
    return ife((strlen($str) <= $long), true, false);
}


/**
 * Valida email
 *
 * @param string $str
 * @return boolean
 */
function val_Email($str) {
    
    if (ereg( "^([0-9,a-z,A-Z]+)([.,_]([0-9,a-z,A-Z]+))*[@]([0-9,a-z,A-Z]+)([.,_,-]([0-9,a-z,A-Z]+))*[.]([0-9,a-z,A-Z]){2}([0-9,a-z,A-Z])?$", $str)) {
        return true;
    }
    
    return false;
}

/**
 * Muestra error en pantalla
 *
 * @param string $serror Mensaje de error visual
 * @param string $errorOculto trace del error que no se muestra
 * @param boolean $body mostrar o no mostrar el cuerpo <html></html>
 * 
 * @return void
 */
function sf_MostrarError($serror, $errorOculto, $body = true) {
    
    if (defined('WEBAPP')) {
        $accion    = WEBAPP . '/htdocs/util/bug.report.php';
    } else {
        $accion    = '';
    }
    
    $serror    = ife($serror,
                     htmlentities($serror), 
                     'Se ha producido una falla del sistema, por favor vuelva a intentarlo
                     o contacte al administrador.'
                     );
                     
    $solicitud = htmlentities(print_r($_REQUEST, true));
    $version   = VERSION;
    $errorOculto = htmlentities(print_r($errorOculto, true));
    
    if ($body) {
        $str = '<html><title>Error del Sistema</title><body style="font-family:Tahoma; font-size:11px;">';
    }
    
    $str .= "
        <p>&nbsp;</p>
        <table class=\"fixedTable\" bgcolor=\"#cccccc\" align=\"center\" cellspacing=\"0\" cellpadding=\"0\" style=\"width:350; border:1px solid #ababab; font-size:12px;\">
         <tr>
          <td align=\"center\" class=\"cell\">
          <p><b>Error del Sistema</b></p>
          <p>{$serror}</p>
          <p>&nbsp;</p>
          <input type=\"button\" class=\"button\" value=\"Regresar\" onClick=\"javascript: history.back(-1);\">
          </td>
         </tr>
        </table><br><br><br>
        ";
        
    if (!empty($accion)) {
        $str .= "<center><b>Sisfo Ltda.</b> le agradece que env&iacute;e la informaci&oacute;n de este incidente a nuestro servidor de Bugs,  
        su colaboraci&oacute;n se ver&aacute; reflejada en un excelente producto que cada d&iacute;a est&aacute; mejorando.
        <form name=\"formulario\" action=\"{$accion}\" method=\"POST\">
        <input type=\"hidden\" name=\"a\" value=\"submitdata\">
        <input type=\"hidden\" name=\"error\" value=\"{$serror}\">
        <input type=\"hidden\" name=\"errorOculto\" value=\"{$errorOculto}\">
        <input type=\"hidden\" name=\"data\" value=\"{$solicitud}\">
        <input type=\"hidden\" name=\"clientID\" value=\"{$version}\"><br>
        <input type=\"button\" class=\"button\" value=\"Realizar Feedback\" onClick=\"document.formulario.submit();\">
        </form>
        </center>";
    } 
    
    if (APP_ALIAS == 'distribuidoresDEV') {
        $str.= "<br/><br/><hr style=\"border: 1px #cccccc dotted;\"><center>
                <script type=\"text/javascript\">
                function verTraza() {
                    if (document.getElementById('traza').style.display == 'inline') {
                        document.getElementById('traza').style.display = 'none';
                    } else {
                        document.getElementById('traza').style.display = 'inline';
                    }
                }
                </script>
                <a href=\"javascript:void(0);\" onclick=\"verTraza()\" style=\"text-decoration: none;\"><b style=\"color: #cccccc;\">Ver Detalle</b></a><br/>
                <div id=\"traza\" style=\"display:none;\">
                    <p style=\"color:red; border: 1px black dotted;\">
                    <b> ::: DETALLE DEL ERROR ::: </b><br/><br/>
                    {$errorOculto}
                    </p>
                </div>
                </center>";
    }

    
    if ($body) {
        $str .= '</body></html>';
    }
        
    echo $str;
    
    exit;
}


?>
