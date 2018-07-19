<?php
class ModelExtensionPaymentPagofacil extends Model
{
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/pagofacil');

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_pagofacil_geo_zone') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

        if ($this->config->get('payment_pagofacil_total') > $total) {
            $status = false;
        } elseif (!$this->config->get('payment_pagofacil_geo_zone')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        // die('here');

        $method_data = array();

        if ($status) {
            $method_data = array(
        'code'       => 'pagofacil',
        'title'      => $this->language->get('text_title'),
        'terms'      => '',
        'sort_order' => $this->config->get('payment_pagofacil_sort_order')
      );
        }

        return $method_data;
    }
}
