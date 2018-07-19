<?php

/**
 *
 */
class ControllerExtensionPaymentPagofacil extends Controller
{
    private $error = array();

    private $sections = array('token_secret', 'token_service', 'environment', 'status');

    public function index()
    {
        $this->load->language('extension/payment/pagofacil');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        //unset($data);

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

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_pagofacil', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');

            // FIXME: descomentar redireccion
            // $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' .$this->session->data['user_token'] . '&type=payment', true));
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

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/pagofacil', $data));
    }

    // retorno de validaciones
    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/pagofacil')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        foreach ($this->sections as $value) {
            if (!$this->request->post['payment_pagofacil_'.$value]) {
                $this->error[$value] = $this->language->get('error_'.$value);
            }
        }

        return !$this->error;
    }
}
