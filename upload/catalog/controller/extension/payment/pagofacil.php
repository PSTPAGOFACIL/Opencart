<?php

/**
 *
 */
require_once('pagofacil/vendor/autoload.php');
use PagoFacil\lib\Transaction;
use PagoFacil\lib\Request;

class ControllerExtensionPaymentPagofacil extends Controller
{
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

        $data['button_confirm'] = $this->language->get('button_confirm');

        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $amount = (int)$order_info['total'];

        $this->configPagoFacil = $this->setupPagoFacil();

        $sessionId = $this->session->data['order_id'].date('YmdHis');

        $request = new Request();

        $data['url'] = $this->url->link('extension/payment/pagofacil/redirect', '', 'true');
        ;

        return $this->load->view('extension/payment/pagofacil', $data);
    }
    public function redirect()
    {
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $request = new Request();

        $request->account_id = $this->config->get('payment_pagofacil_token_service');
        $request->amount = round($order_info['total']);
        $request->currency = $order_info['currency_code'];
        $request->reference = $order_info['order_id'];
        $request->customer_email = $order_info['email'];
        $request->url_complete =  $this->url->link('extension/payment/pagofacil/complete', '', 'true');
        ;
        $request->url_cancel =  $this->url->link('extension/payment/pagofacil/cancel', '', 'true');
        ;
        $request->url_callback =  $this->url->link('extension/payment/pagofacil/callback', '', 'true');
        ;
        $request->shop_country =  $order_info['payment_iso_code_2'];
        $request->session_id = date('Ymdhis').rand(0, 9).rand(0, 9).rand(0, 9);
        $transaction = new Transaction($request);
        $transaction->environment = $this->config->get('payment_pagofacil_environment');

        $transaction->setToken($this->config->get('payment_pagofacil_token_secret'));

        $transaction->initTransaction($request);
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

        $order_id = $response["x_reference"];

        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (empty($order_info)) {
            error_log('ORDEN $order_id NO ENCONTRADA');
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 404 No encontrado';
            header($header);
        }

        $transaction = new Transaction();
        $transaction->setToken($this->token_secret);
        $this->load->model('checkout/order');
        //Validate Signed message
        if ($transaction->validate($response)) {
            error_log("FIRMAS CORRESPONDEN");
            //Validate order state
            if ($response['x_result'] == "completed") {
                //Validate amount of order
                if (round($order_info['total']) != $response["x_amount"]) {
                    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                    $header = $protocol  . ' 400 Bad Request';
                    header($header);
                }
                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_pagofacil_completed_order_status'), true);
                $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                $header = $protocol  . ' 200 OK';
                header($header);
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

        $order_id = $response["x_reference"];

        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (empty($order_info)) {
            error_log('ORDEN $order_id NO ENCONTRADA');
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 404 No encontrado';
            header($header);
        }

        $transaction = new Transaction();
        $transaction->setToken($this->token_secret);
        $this->load->model('checkout/order');

        $data = array();
        //Validate Signed message
        if ($transaction->validate($response)) {
            error_log("FIRMAS CORRESPONDEN");
            //Validate order state
            if ($response['x_result'] == "completed") {
                //Validate amount of order
                if (round($order_info['total']) != $response["x_amount"]) {
                    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                    $header = $protocol  . ' 400 Bad Request';
                    header($header);
                }
                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_pagofacil_completed_order_status'), true);
                $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                $header = $protocol  . ' 200 OK';
                header($header);

                $data['amount'] = $response['x_amount'];
                $data['reference'] = $response['x_reference'];
                $data['gateway_reference'] = $response['x_gateway_reference'];
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

    public function authorize()
    {
        die('authorize');
        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            # code...
            $this->token = $this->request->post['token_ws'];
        }
        //die;
        if (!isset($this->token)) {
            $result['error'] = $this->language->get('error_token');
            $this->response->setOutput($result);
        }
        $this->configPagoFacil = $this->setupPagoFacil();

        $conf = new PagoFacilConfig($this->configPagoFacil);
        $pagofacil = new PagoFacilNormal($conf);

        //  error_reporting(0);
        $result = $pagofacil->getTransactionResult($this->token);

        $order_id = $result->buyOrder;
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $order_status_id = $this->config->get('config_order_status_id');
        $voucher = false;

        $this->session->data['pagofacil'] = json_decode(json_encode($result), true);

        if ($order_id && $order_info) {
            if (($result->VCI == "TSY" || $result->VCI == "A" || $result->VCI == "") && $result->detailOutput->responseCode == 0) {
                $voucher = true;

                $order_status_id = $this->config->get('payment_pagofacil_completed_order_status');
            } else {
                $order_status_id = $this->config->get('payment_pagofacil_rejected_order_status');
            }
        } else {
            $this->log->write($this->language->get('error_response').print_r($result, true));
        }


        if ($voucher) {
            $this->redirect($result->urlRedirection, array('token_ws' => $this->token));
        } else {
            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_pagofacil_rejected_order_status'), true);
            $this->redirect($this->config->get('payment_pagofacil_url_reject'), array(
            "token_ws" => $this->token,
            "code" => $result->detailOutput->responseCode,
            "description" => htmlentities($result->detailOutput->responseDescription),
            "fecha" => $result->transactionDate
          ));
        }
    }

    public function finish()
    {
        $this->language->load('extension/payment/pagofacil');
        $this->load->model('checkout/order');

        $maindata = array('header', 'column_left', 'column_right', 'footer' );
        foreach ($maindata as $main) {
            $data[$main] = $this->load->controller('common/'.$main);
        }

        if (!isset($this->request->server['HTTPS']) || ($this->request->server['HTTPS'] != 'on')) {
            $data['base'] = $this->config->get('config_url');
        } else {
            $data['base'] = $this->config->get('config_ssl');
        }

        $data['language'] = $this->language->get('code');
        $data['direction'] = $this->language->get('direction');

        $data['title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

        $data['heading_title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

        $data['text_success'] = $this->language->get('text_success');
        $data['text_success_wait'] = sprintf($this->language->get('text_success_wait'), $this->url->link('checkout/success', '', 'SSL'));

        $data['button_continue'] = $this->language->get('button_continue');
        $data['continue'] = $this->url->link('checkout/success');

        if (isset($this->session->data['pagofacil']) && isset($this->request->post['token_ws'])) {
            $pagofacilData = $this->session->data['pagofacil'];

            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        }

        if (isset($order_info) && $order_info && $pagofacilData["buyOrder"]) {
            $data['tbk_tipo_transaccion'] = 'Venta';
            $data['tbk_respuesta'] = "Aceptado";

            $data['tbk_nombre_comercio'] = $this->config->get('config_name');
            $data['tbk_url_comercio'] = $data['base'];
            $data['tbk_nombre_comprador'] = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
            $data['tbk_orden_compra'] = $pagofacilData["buyOrder"];
            $data['tbk_monto'] = $pagofacilData["detailOutput"]["amount"];
            $data['tbk_codigo_autorizacion'] = $pagofacilData["detailOutput"]["authorizationCode"];
            $data['tbk_fecha_contable'] = substr($pagofacilData["accountingDate"], 2, 2) . "-" . substr($pagofacilData["accountingDate"], 0, 2) . "-" . date('Y');
            $datetime = new DateTime($pagofacilData["transactionDate"]);
            $data['tbk_hora_transaccion'] = $datetime->format('H:i:s');
            $data['tbk_dia_transaccion'] = $datetime->format('d-m-Y');

            $data['tbk_final_numero_tarjeta'] = '************' . $pagofacilData["cardDetail"]["cardNumber"];

            $this->configPagoFacil = $this->setupPagoFacil();
            $data['tbk_tipo_pago'] = $this->configPagoFacil['VENTA_DESC'][$pagofacilData["detailOutput"]["paymentTypeCode"]];

            $data['tbk_tipo_cuotas'] = $pagofacilData["detailOutput"]["sharesNumber"];

            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_pagofacil_completed_order_status'), true);
        } else {
            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_pagofacil_canceled_order_status'), true);

            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        }


        $this->response->setOutput($this->load->view('extension/payment/pagofacil_success', $data));
    }

    public function reject()
    {
        $this->language->load('extension/payment/pagofacil');
        $this->load->model('checkout/order');

        $maindata = array('header', 'column_left', 'column_right', 'footer' );
        foreach ($maindata as $main) {
            $data[$main] = $this->load->controller('common/'.$main);
        }

        if (!isset($this->request->server['HTTPS']) || ($this->request->server['HTTPS'] != 'on')) {
            $data['base'] = $this->config->get('config_url');
        } else {
            $data['base'] = $this->config->get('config_ssl');
        }

        $data['language'] = $this->language->get('code');
        $data['direction'] = $this->language->get('direction');

        $data['title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

        $data['heading_title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

        $data['text_response'] = $this->language->get('text_response');

        $data['date'] = $this->request->post['fecha'];
        $data['text_razon'] = $this->request->post['description'];
        $data['text_failure'] = $this->language->get('text_failure');
        $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('checkout/cart', '', 'SSL'));

        if (isset($this->request->post['data'])) {
            $pagofacilData = $this->session->data['payment_pagofacil'];
        }

        $data['button_continue'] = $this->language->get('button_continue');
        $data['continue'] = $this->url->link('checkout/cart');

        if (isset($this->session->data['order_id'])) {
            $data['orden_compra'] = $this->session->data['order_id'];
        } else {
            $data['orden_compra'] = $pagofacilData["buyOrder"];
        }
        $data['reject_time'] = date('H:i:s');
        $data['reject_data'] = date('d-m-Y');

        $this->load->model('checkout/order');
        $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_pagofacil_rejected_order_status'), true);

        $this->response->setOutput($this->load->view('extension/payment/pagofacil_failure', $data));
    }
}
