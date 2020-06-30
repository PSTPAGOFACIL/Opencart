<?php

require_once('pagofacil/vendor/autoload.php');

use PagoFacilCore\PagoFacilSdk;
use PagoFacilCore\EnvironmentEnum;
use PagoFacilCore\Utils;

/**
 *
 */
class ControllerExtensionPaymentPagofacil extends Controller
{
    private $error = array();

    private $sections = array('token_secret', 'token_service', 'environment', 'status');

    public function index()
    {
        $logger = new Log('error.log');
        $logger->write(' controller - payment - admin INDEX');

        $this->load->language('extension/payment/pagofacil');

        $this->document->setTitle($this->language->get('heading_title'));

        

        $redirs = array('complete' , 'callback', 'cancel');
        foreach ($redirs as $value) {
            $this->request->post['payment_pagofacil_url_'.$value] = HTTP_CATALOG . 'index.php?route=extension/payment/pagofacil/' .$value;
        }

        $selects = array(5 => 'completed_order_status', 10 => 'rejected_order_status', 7 => 'canceled_order_status');
        foreach ($selects as $i =>  $value) {
            $this->request->post['payment_pagofacil_'.$value] = $i;
        }

        $selects_1 = array('geo_zone', 'sort_order', 'status', 'total');

        foreach ($selects_1 as $value) {
            if (isset($this->request->post['payment_pagofacil_'.$value])) {
                $data['payment_pagofacil_'.$value] = $this->request->post['payment_pagofacil_'.$value];
            } else {
                $data['payment_pagofacil_'.$value] = $this->config->get('payment_pagofacil_'.$value);
            }
        }
        
        // validacion de modificaciones
        $error_payment_option = '';
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('payment_pagofacil', $this->request->post);
            try {
                $this->editSettingsAdditionalData('payment_pagofacil', $this->request->post);
                $this->session->data['success'] = $this->language->get('text_success');
                $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' .$this->session->data['user_token'] . '&type=payment', true));
            } catch (Exception $e) {
                $logger->write('request post: '. print_r($e->getMessage(), TRUE));
                $error_payment_option = 1;
            }
            
        }

        // se imprimen errores si existen
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        foreach ($this->sections as $value) {
            if (isset($this->error['payment_pagofacil_'.$value])) {
                $data['error_'.$value] = $this->error['payment_pagofacil_'.$value];
            } else {
                $data['error_'.$value] = '';
            }
        }
        $data['error_payment_options'] = $error_payment_option;

        $vars = array('entry_token_secret', 'entry_token_service',);
        foreach ($vars as $var) {
            $data[$var] = $this->language->get($var);
        }

        // se declaran los breadcrumbs (el menu de seguimiento)
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_pagofacil'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/pagofacil', 'user_token=' . $this->session->data['user_token'], true),
        );

        $data['action'] = $this->url->link('extension/payment/pagofacil', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        //carga datos de la configuracion para mostrarlos en el formulario.
        foreach ($this->sections as $value) {
            if (isset($this->request->post['payment_pagofacil_'.$value])) {
                $data['payment_pagofacil_'.$value] = $this->request->post['payment_pagofacil_'.$value];
            } elseif ($this->config->get('payment_pagofacil_'.$value)) {
                $data['payment_pagofacil_'.$value] = $this->config->get('payment_pagofacil_'.$value);
            }
        }
        foreach ($selects as $value) {
            if (isset($this->request->post['payment_pagofacil_'.$value])) {
                $data['payment_pagofacil_'.$value] = $this->request->post['payment_pagofacil_'.$value];
            } else {
                $data['payment_pagofacil_'.$value] = $this->config->get('payment_pagofacil_'.$value);
            }
        }
        //carda datos de opciones de pago.
        $data['payment_options'] = $this->load_payment_options();

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['PRODUCTION'] = EnvironmentEnum::PRODUCTION;
        $data['DEVELOPMENT'] = EnvironmentEnum::DEVELOPMENT;

        $this->response->setOutput($this->load->view('extension/payment/pagofacil', $data));
    }

    /**
     * Actualiza datos adicionales usados por la extension
     * @param $code extension code.
     * @param $data request post data.
     */
    private function editSettingsAdditionalData($code, $data) {
        //valida y guarda opciones de pago adicionales.
        $this->load->model('extension/payment/pagofacil');
        $token_service = $data["payment_pagofacil_token_service"];
        $environment = $data["payment_pagofacil_environment"];
        $paymentOptions = $this->get_payment_options($token_service, $environment);
        $is_extension_enable = $data["payment_pagofacil_status"];
        $additional_settings = $this->validate_additional_settings($paymentOptions, $is_extension_enable, $data);
        if (sizeof($additional_settings) > 0) {
            $this->model_extension_payment_pagofacil->editAdditionalSetting($code, $additional_settings);
        }
        //guarda datos de opciones de pago
        if (sizeof($paymentOptions) > 0) {
            $this->model_extension_payment_pagofacil->editPaymentOptions($paymentOptions);
        }
    }

    /**
     * Valida campos en request->post
     */
    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/pagofacil')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        foreach ($this->sections as $value) {
            if (!isset($this->request->post['payment_pagofacil_'.$value])) {
                $this->error[$value] = $this->language->get('error_'.$value);
            }
        }
        return !$this->error;
    }

    /**
     * Crea tablas y eventos de la extension
     */
    public function install() {
        $this->load->model('extension/payment/pagofacil');
        $this->model_extension_payment_pagofacil->createTables();
        $this->model_extension_payment_pagofacil->createEvents();
    }

    /**
     * Borra tablas y eventos de la extension
     */
    public function uninstall() {
        $this->load->model('extension/payment/pagofacil');
        $this->model_extension_payment_pagofacil->dropTables();
        $this->model_extension_payment_pagofacil->deleteEvents();
    }
    
    /**
     * endpoint para actualizar metodos de pagos en la vista (settings admin.)
     */
    public function payment_methods() {
        $json = array();
        $token_service =  isset($_GET["token_service"]) ?  $_GET["token_service"] : null;
        $environment = isset($_GET["environment"]) ?  $_GET["environment"] : null;
        if (!is_null($token_service)) {
            $json = $this->get_payment_options($token_service, $environment);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Consula opciones de pago de pagofacil
     */
    private function get_payment_options($token_service, $environment) {
        $result = array();
        $pagoFacil = PagoFacilSdk::create()
            ->setTokenService($token_service)
            ->setEnvironment($environment);
        $paymentOptions = $pagoFacil->getPaymentMethods();
        if (property_exists((object) $paymentOptions, 'types')) {
            $paymentTypes = $paymentOptions['types'];
            foreach ($paymentTypes as &$paymentType) {
                $codigo = (isset($paymentType['codigo']) ? $paymentType['codigo'] : '');
                $setting_key = 'payment_'.strtolower($codigo).'_status';
                $result[] = array(
                    'codigo' =>  Utils::clean_non_alphanumeric(Utils::sanitize($codigo)),
                    'nombre' => Utils::sanitize(isset($paymentType['nombre']) ? $paymentType['nombre'] : ''),
                    'descripcion' => Utils::sanitize(isset($paymentType['descripcion']) ? $paymentType['descripcion'] : ''),
                    'url_imagen' => Utils::sanitize(isset($paymentType['url_imagen']) ? $paymentType['url_imagen'] : ''),
                    'setting_key' => $setting_key
                );
            }
        }
        return $result;
    }

    /**
     * Caraga opciones de pago guardadas en la base de datos
     */
    private function load_payment_options() {
        $this->load->model('extension/payment/pagofacil');
        return $this->model_extension_payment_pagofacil->getPaymentOptions();
    }

    /**
     * Valida datos addicionales de configuracion con datos de opciones de pago obtenidas del backend de pagofacil.
     * 
     * @param $paymentOptions         arreglo con opciones de pago obtenidas de pagofacil.
     * @param $is_extension_enable    flag inidica si la extension de pagofacil esta activada o desactivada.
     * @param $data                   datos del equest post.
     * @return arreglo con los datos validos.
     */
    private function validate_additional_settings($paymentOptions, $is_extension_enable, $data) {
        $result = array();
        foreach ($paymentOptions as &$paymentOption) {
            $name = $paymentOption['setting_key'];
            if (isset($this->request->post[$name])) {
                $result[$name] = $is_extension_enable;
            }
        }
        return $result;
    }
}
