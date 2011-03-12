<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * SisfoDB.class.php
 * Interaccion con bases de datos
 *
 * SF 1.0 
 * Framework Sisfo para soporte de aplicaciones desarrolladas de forma
 * tradicional
 * 
 * PHP 5
 *
 * LICENSE:    GNU GENERAL PUBLIC LICENSE 2.0
 * http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package    SF
 * @author     Mauricio Morales <mmorales@sisfo.com>
 * @copyright  2008 Sisfo Ltda.
 * @version    CVS: $Id:SisfoModelo.class$
 * @see        SisfoDB_Base
 * @since      File available since Release 1.0
 */

require_once 'PEAR.php';
require_once 'MDB2.php';

include_once realpath(dirname(__FILE__) . '/../conf.main.php');
include_once realpath(dirname(__FILE__) . '/driver/SisfoDB_Base.php');
include_once realpath(dirname(__FILE__) . '/driver/SisfoDB_pgsql.php');
include_once realpath(dirname(__FILE__) . '/driver/SisfoDB_oci8.php');


/**
 * SisfoDB :: Factory
 * Retorna el objeto de interaccion dependiendo del motor de base de datos
 * 
 * @author Mauricio Morales <mmorales@sisfo.com>
 */
class SisfoDB {
    
    /**
     * Retorna manejador
     * 
     * @param string $conexion Conexion a usar de la DB
     * @return object SisfoDB_Oci | SisfoDB_Pgsql
     */
    function getManejador($conexion = null) {
        global $db;
        
        if (!is_null($conexion) && isset($db[$conexion])) {
            $motor = $db[$conexion]['motor'];
        } else {
            $motor = $db['motor'];
        }
        
        switch ($motor) {
            case 'pgsql' :
                return new SisfoDB_pgsql($conexion);
            case 'oci8' :
                return new SisfoDB_oci8($conexion);
            default:
                throw new Exception("Manejador de base de datos desconocido");
        }
        
    }
    
}
?>
