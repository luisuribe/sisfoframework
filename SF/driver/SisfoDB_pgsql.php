<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * SisfoDB_pgsql.php
 * Driver para pgsql
 *
 * SF 1.0 BETA
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
 * @version    CVS: $Id:SisfoDB_pgsql.class$
 * @see        SisfoDB
 * @since      File available since Release 1.0
 */

require_once realpath(dirname(__FILE__) . '/SisfoDB_Base.php');


/**
 * Primeras lineas por Mauricio Morales
 * Driver de postgres para SF
 *
 */
class SisfoDB_pgsql extends SisfoDB_Base
{
    
    /**
     * Tipos de datos como los describe postgresql en su comando
     * asociacion con tipos de dato estandards
     * 
     * 
     */
    public $tipos = array('char' => array('character', 'character varying'),
                                       'int'  => array('integer', 'smallint', 'bigint'),
                                       'float' => array('double precision'),
                                       'date' => array('date'),
                                       'timestamp' => array('timestamp'),
                                       'text' => array('text'),
    );
    
    function __construct() {
        parent::__construct();
    }
    
    /**
     * Hace un Describe de la tabla del modelo
     * retorna arreglo
     * 
     * @return array
     */
    function describir() {
        
        $query = "SELECT DISTINCT
                    column_name AS nombre, 
                    data_type AS tipo, 
                    is_nullable AS null, 
                    column_default AS default, 
                    ordinal_position AS posicion, 
                    character_maximum_length AS longitud, 
                    character_octet_length AS oct_length 
                   FROM information_schema.columns WHERE table_name = '{$this->tabla}'";
        
        $this->debug($query);
        $data = self::$dbObject->queryAll($query, null, MDB2_FETCHMODE_ASSOC);
        
        if (PEAR::isError($data)) {
            $this->debug('No se puede establecer conexion o error interno del servidor');
            $this->fatalError();
            
            if (self::$autoError || (defined('DEBUG') && DEBUG)) {
                sf_MostrarError('', $data->getUserinfo());
            }
            
            return array();
        }
        
        return $data;
    }
    
    /**
     * Concatenacion
     *
     * @param array $campos
     * @return string
     */
    function concatenar($campos, $separador = ' - ') {
        $output = "";
        foreach ($campos as $campo) {
            $output .= "{$campo} ||'{$separador}'|| ";
        }
        $output = substr($output, 0, (-7 - strlen($separador)));
        return $output;
    }
}

?>
