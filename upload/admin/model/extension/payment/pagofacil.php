<?php

class ModelExtensionPaymentPagofacil extends Model {

    
    /**
     * Crea tabla usada por la extension.
     */
    public function createTables() {
        //guarda datos de las opciones de pago.
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "pagofacil_paymentoptions` (
            `pagofacil_paymentoptions_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `codigo` varchar(255) NOT NULL DEFAULT '',
            `nombre` varchar(255) NOT NULL DEFAULT '',
            `descripcion` varchar(255) NOT NULL DEFAULT '',
            `url_imagen` varchar(255) NOT NULL DEFAULT '',
            `setting_key` varchar(255) NOT NULL DEFAULT '',
            `store_id` INT(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (`pagofacil_paymentoptions_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
    }

    /**
     * Crea eventos usados por la extension. (metodo del evento definido en catalog)
     */
    public function createEvents() {
        // Registra evento que manipula metodos de pago en el checkout del catalogo
        $this->load->model('setting/setting');
        $pagofacil_checkout_payment = $this->model_setting_event->getEventByCode("extension_pagofacil_checkout_payment");
        if (empty($pagofacil_checkout_payment)) {
            $this->model_setting_event->addEvent(
                'extension_pagofacil_checkout_payment',
                'catalog/controller/checkout/checkout/before',
                'extension/payment/pagofacil/eventCheckoutPayment');
        }
    }

    /**
     * Borra tabla usada por la extension.
     */
    public function dropTables() {
        //borra archivos asociados.
        $paymentOptions = $this->getPaymentOptions();
        $this->deletePaymentOptionFiles($paymentOptions);

        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "pagofacil_paymentoptions`");
    }

    /**
     * Borra eventos usados por la extension.
     */
    public function deleteEvents() {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('extension_pagofacil_checkout_payment');
    }

    /**
     * Agrega registros adicionales en tabla settings relacionadas a extension 'payment_pagofacil'. 
     * Para que opciones de pago (pagofacil) se vean en el checkout opencart, se debe tener registradas las opciones de pago como extensiones de pago disponibles,
     *  por lo que se agregan los registros con key: 'payment_${opcion_pago}_status' y valores 0 /1 (desactivada/activada).  
     */
    public function editAdditionalSetting($code, $data, $store_id = 0) {
        //borra datos addicioanles en settings.
        $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "' AND `key` LIKE 'payment_%_status' AND `key` NOT LIKE 'payment_pagofacil_%_status' AND `key` != 'payment_pagofacil_status'");
        //guarda datos addicionales.
		foreach ($data as $key => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "'");
		}
    }
        
    /**
     * guarda datos relacionados a opciones de pago pagofacil
     */
    public function editPaymentOptions($paymentOptions, $store_id = 0) 
    {
        //antes de limpia la tabla rescata los registror para saber que archivos borrar en 'editPaymentOptionsFiles'
        $lastPaymentOptions = $this->getPaymentOptions($store_id);

        //limpia tabla
        $this->db->query("DELETE FROM `" . DB_PREFIX . "pagofacil_paymentoptions`");
        //carga nuevos registros
        foreach ($paymentOptions as &$paymentOption) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "pagofacil_paymentoptions SET store_id = '" . (int)$store_id . "', `codigo` = '" . $this->db->escape($paymentOption['codigo']) . "', `nombre` = '" . $this->db->escape($paymentOption['nombre']) . "', `descripcion` = '" . $this->db->escape($paymentOption['descripcion']) . "', `url_imagen` = '" . $this->db->escape($paymentOption['url_imagen']) . "', `setting_key` = '" . $this->db->escape($paymentOption['setting_key']) . "'");
        }
        //edita archivos relacionados con metodo de pago.
        $this->editPaymentOptionsFiles($lastPaymentOptions, $paymentOptions);
    }

    /**
     * obtiene opciones de pago.
     */
    public function getPaymentOptions($store_id = 0) {
        $sql = "SELECT `codigo`, `nombre`, `descripcion`, `url_imagen`, `setting_key`  FROM `" . DB_PREFIX . "pagofacil_paymentoptions` WHERE `store_id` =" . (int)$store_id;
        return $this->db->query($sql)->rows;
    }

    /**
     * Crea archivos asociados para poder mostrar opciones de pago de pagofacil como metodos de pago.
     * Primero borra archivos antiguos (basados en registros de la base de datos)
     * Crea nuevos archivos basados en lo que recive en el ultimo post request.
     * Los archivos que se crean son los controladores y los modelos asociados a los metodos de pago.
     * 
     * @param $lastPaymentOptions   Ultimos metodos de pago guardados en la base de datos.
     * @param $paymentOptions       metodos de pago recibidos en el post request.
     */
    public function editPaymentOptionsFiles($lastPaymentOptions, $paymentOptions) {
        
        $logger = new Log("error.log");
        //valida que en directorios finales existan archivos 'pagofacil.php' 
        $DIR_FINAL_CONTROLLER = DIR_CATALOG. '/controller/extension/payment/';
        $DIR_FINAL_MODEL = DIR_CATALOG. '/model/extension/payment/';
        if (!file_exists($DIR_FINAL_CONTROLLER. 'pagofacil.php') || !file_exists($DIR_FINAL_MODEL. 'pagofacil.php')) {
            throw new Exception('error en configurar correctamente la extension.');
        }
        //borra archivos
        $this->deletePaymentOptionFiles($lastPaymentOptions);

        //crea archivo model y controlador por cada opcion de pago.
        foreach ($paymentOptions as &$paymentOption) {
           $codigo = $paymentOption['codigo'];
           $filename = strtolower($codigo) .'.php';
           //controlador
           $controller = fopen($DIR_FINAL_CONTROLLER. $filename, "w");
           fwrite($controller, $this->buildController($paymentOption));
           fclose($controller);
           $logger->write('CREO archivo: '. print_r( $DIR_FINAL_CONTROLLER. $filename, TRUE) );
           //modelo
           $model = fopen($DIR_FINAL_MODEL. $filename, "w");
           fwrite($model, $this->buildModel($paymentOption));
           fclose($model);
           $logger->write('CREO archivo: '. print_r( $DIR_FINAL_MODEL. $filename, TRUE) );
        }
    }

    /**
     * borra archivos asociados a metodos de pago pasado por parametros.
     */
    private function deletePaymentOptionFiles($paymentOptions) {
        $logger = new Log("error.log");
        $DIR_FINAL_CONTROLLER = DIR_CATALOG. '/controller/extension/payment/';
        $DIR_FINAL_MODEL = DIR_CATALOG. '/model/extension/payment/';
        //borra archivo model y controlador de cada metodo segun registrado en la base de datos (pasado por parametro).
        foreach ($paymentOptions as &$paymentOption) {
            $filename = strtolower($paymentOption['codigo']) .'.php';
            $logger->write('ve si existe: '. print_r( $DIR_FINAL_CONTROLLER. $filename, TRUE) );
            if (file_exists($DIR_FINAL_CONTROLLER. $filename)) {
                $logger->write('Borrar archivo: '. print_r( $DIR_FINAL_CONTROLLER. $filename, TRUE) );
                unlink($DIR_FINAL_CONTROLLER. $filename);
            }
            $logger->write('ve si existe: '. print_r( $DIR_FINAL_MODEL. $filename, TRUE) );
            if (file_exists($DIR_FINAL_MODEL. $filename)) {
                $logger->write('Borrar archivo: '. print_r( $DIR_FINAL_MODEL. $filename, TRUE) );
                unlink($DIR_FINAL_MODEL. $filename);
            }
        }
    }

    /** 
    * Contruye clase para controlador
    */
    private function buildController($paymentOption) {
        $codigo = $paymentOption['codigo'];
        return <<<EOD
<?php
/************************************************************
* Archivo Generado por plugin Pago Facil.
* No borrar archivo si se tiene plugin instalado,
* archivo es borrado automaticamente si plugin es desinstalado.
**************************************************************/
require_once('pagofacil.php');
class ControllerExtensionPayment$codigo extends ControllerExtensionPaymentPagofacil
{
    function __construct(\$registry) {
        parent::__construct(\$registry,'$codigo');
    }
}
EOD;
    }

    /** 
    * Contruye clase para modelo
    */
    private function buildModel($paymentOption) {
         $codigo = $paymentOption['codigo'];
         $nombre = $paymentOption['nombre'];
         $descripcion = $paymentOption['descripcion'];
         $url_imagen = $paymentOption['url_imagen'];
        return <<<EOD
<?php
/************************************************************
 * Archivo Generado por plugin Pago Facil.
 * No borrar archivo si se tiene plugin instalado,
 * archivo es borrado automaticamente si plugin es desinstalado.
 **************************************************************/
class ModelExtensionPayment$codigo extends Model { 
    public function getMethod(\$address, \$total) 
    {
        \$codigo = '$codigo'; \$nombre = '$nombre'; \$descripcion = '$descripcion'; \$url_imagen = '$url_imagen';
        \$this->load->language('extension/payment/pagofacil');
        \$title_text = \$this->language->get('text_payment_pagofacil');
        \$title_text = str_replace('::METODOPAGO::', \$nombre, \$title_text);
        \$title_text = str_replace('::METODOPAGODESCRIPCION::', \$descripcion, \$title_text);
        \$title_text = str_replace('::METODOPAGOLOGO::', \$url_imagen, \$title_text);
        return array(
            'code'       => strtolower(\$codigo),
            'title'      => \$title_text,
            'terms'      => '',
            'sort_order' => '',
        );
    }
}
EOD;
    }

}