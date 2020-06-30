<?php


require_once(dirname(__FILE__) . '/../../../../admin/controller/extension/payment/pagofacil/vendor/autoload.php');
use PagoFacilCore\PagoFacilSdk;
use PagoFacilCore\Transaction;


class ControllerExtensionPaymentPagofacil extends Controller
{
    private $_payment_option;

    function __construct($registry, $payment_option = null) {
        parent::__construct($registry);
        $this->_payment_option = $payment_option;
    }

    /**
     * Asigna valor de ambiente en configuracion de la clase.
     */
    private function setupPagoFacil()
    {
        $this->conf = array(
          "ECOMMERCE" => "opencart",
            "MODO" => $this->config->get('payment_pagofacil_environment')
        );
        return $this->conf;
    }

    public function index()
    {
        $this->load->language('extension/payment/pagofacil');

        $button_message = $this->language->get('button_confirm');
        $data['button_confirm'] = str_replace('::METODOPAGO::', $this->_payment_option, $button_message);

        $this->load->model('checkout/order');

        $this->configPagoFacil = $this->setupPagoFacil();
        $payOpt = strtolower($this->_payment_option);
        $data['url'] = $this->url->link('extension/payment/'.$payOpt.'/redirect', '', 'true');
        

        return $this->load->view('extension/payment/pagofacil', $data);
    }

    public function redirect()
    {
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $token_service = $this->config->get('payment_pagofacil_token_service');
        $token_secret = $this->config->get('payment_pagofacil_token_secret');
        $environment = $this->config->get('payment_pagofacil_environment');

        $pagoFacil = PagoFacilSdk::create()
            ->setTokenSecret($token_secret)
            ->setTokenService($token_service)
            ->setEnvironment($environment);

        //Validar monto total, no debe ser menor a 1000
        if(round($order_info['total']) < 1000) {
            $this->load->language('extension/payment/pagofacil');
            $error_monto_total_message = $this->language->get('error_monto_total');
            $this->session->data['error'] = $error_monto_total_message;
        } 

        try {
            $transaction = new Transaction();
            $transaction->setUrlCallback($this->url->link('extension/payment/pagofacil/callback', '', 'true'));
            $transaction->setUrlCancel($this->url->link('extension/payment/pagofacil/cancel', '', 'true'));
            $transaction->setUrlComplete($this->url->link('extension/payment/pagofacil/complete', '', 'true'));
            $transaction->setCustomerEmail($order_info['email']);
            $transaction->setReference($order_info['order_id']);
            $transaction->setAmount(round($order_info['total']));
            $transaction->setCurrency($order_info['currency_code']);
            $transaction->setShopCountry($order_info['payment_iso_code_2']);
            $transaction->setSessionId(date('Ymdhis').rand(0, 9).rand(0, 9).rand(0, 9));
            $transaction->setAccountId($token_service);
            $data = $pagoFacil->initPayment($transaction, $this->_payment_option);
            //redirecciona pagina de metodo de pago
            $this->response->redirect($data['urlTrx']);
        } catch (Exception $e) {
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 400 Bad Request';
            header($header);
            $this->response->redirect($this->url->link('checkout/cart'));
        }
    }
    
    public function complete()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->procesarComplete($_POST);
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 200 OK';
            header($header);
        } else {
            error_log("NO SE INGRESA POR POST (405)");
        }
    }
    public function callback()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->procesarCallback($_POST);
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 200 OK';
            header($header);
        } else {
            error_log("NO SE INGRESA POR POST (405)");
        }
    }

    private function procesarCallback($response)
    {
        $this->load->model('checkout/order');
        $this->token_service = $this->config->get('payment_pagofacil_token_service');
        $this->token_secret = $this->config->get('payment_pagofacil_token_secret');
        $environment = $this->config->get('payment_pagofacil_environment');

        $order_id = $response["x_reference"];

        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (empty($order_info)) {
            error_log('ORDEN $order_id NO ENCONTRADA');
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 404 No encontrado';
            header($header);
        }

        $pagoFacil = PagoFacilSdk::create()
            ->setTokenSecret($this->token_secret)
            ->setEnvironment($environment);

        $this->load->model('checkout/order');
        //Validate Signed message
        if ($pagoFacil->validateSignature($response)) {
            error_log("FIRMAS CORRESPONDEN");
            //Validate order state
            if ($response['x_result'] == "completed") {
                //Validate amount of order
                if (round($order_info['total']) != $response["x_amount"]) {
                    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                    $header = $protocol  . ' 400 Bad Request';
                    header($header);
                    
                } else {
                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_pagofacil_completed_order_status'), true);
                    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                    $header = $protocol  . ' 200 OK';
                    header($header);
                }
            } else {
                $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                $header = $protocol  . ' 200 OK';
                header($header);
            }
        } else {
            error_log("FIRMAS NO CORRESPONDEN");
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 400 Bad Request';
            header($header);
        }
    }

    private function procesarComplete($response)
    {
        $this->load->model('checkout/order');
        $this->token_service = $this->config->get('payment_pagofacil_token_service');
        $this->token_secret = $this->config->get('payment_pagofacil_token_secret');
        $environment = $this->config->get('payment_pagofacil_environment');

        $order_id = $response["x_reference"];

        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (empty($order_info)) {
            error_log('ORDEN $order_id NO ENCONTRADA');
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 404 No encontrado';
            header($header);
        }

        $pagoFacil = PagoFacilSdk::create()
            ->setTokenSecret($this->token_secret)
            ->setEnvironment($environment);
            
        $this->load->model('checkout/order');
        $data = array();
        //Validate Signed message
        if ($pagoFacil->validateSignature($response)) {
            error_log("FIRMAS CORRESPONDEN");
            //Validate order state
            if ($response['x_result'] == "completed") {
                //Validate amount of order
                if (round($order_info['total']) != $response["x_amount"]) {
                    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                    $header = $protocol  . ' 400 Bad Request';
                    header($header);
                } else {
                    $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_pagofacil_completed_order_status'), true);
                    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                    $header = $protocol  . ' 200 OK';
                    header($header);

                    $data['amount'] = $response['x_amount'];
                    $data['reference'] = $response['x_reference'];
                    $data['gateway_reference'] = $response['x_gateway_reference'];
                }
            } else {
                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_pagofacil_canceled_order_status'), true);
                $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                $header = $protocol  . ' 200 OK';
                header($header);
                $this->response->redirect($this->url->link('checkout/cart'));
            }
        } else {
            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_pagofacil_canceled_order_status'), true);
            error_log("FIRMAS NO CORRESPONDEN");
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 400 Bad Request';
            header($header);
            $this->response->redirect($this->url->link('checkout/cart'));
        }
        $this->response->redirect($this->url->link('checkout/success'));
    }

    /**
     * Evento que se ejecuta antes de llamar al controllador payment_method
     */
    public function eventCheckoutPayment($route, &$data) {
        //carga opciones de pago.
        $this->load->model('extension/payment/pagofacil');
        $this->model_extension_payment_pagofacil->insert_payment_methods();

	}
}
