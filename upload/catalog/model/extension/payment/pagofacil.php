<?php


class ModelExtensionPaymentPagofacil extends Model
{
    
    /**
     * Obtiene datos del metodo de pago. Como pagofacil no es un metodo de pago retorna un arraglo vacio.
     */
    public function getMethod($address, $total)
    {
        return array();
    }

    /**
     * guarda opciones de pago pagofacil como extensiones de metodos de pago.
     */
    public function insert_payment_methods() {
        //obtiene metodos de pago desde la base de datos.
        $store_id = 0;
        $sql = "SELECT `codigo` FROM `" . DB_PREFIX . "pagofacil_paymentoptions` WHERE `store_id` =" . (int)$store_id;
        $query = $this->db->query($sql);
        foreach ($query->rows as $result) {
            $code = strtolower($result['codigo']);
            $type = 'payment';
            $extensions = $this->getInstalled($type);
            if (!in_array($code, $extensions)) {
                $sql = "INSERT INTO `" . DB_PREFIX . "extension` SET `type` = '" . $this->db->escape($type) . "', `code` = '" . $this->db->escape($code) . "'";
                $this->db->query($sql);
            }
        }
    }

    /**
     * trae extensiones instaldas
     */
    public function getInstalled($type) {
		$extension_data = array();
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "extension` WHERE `type` = '" . $this->db->escape($type) . "' ORDER BY `code`");

		foreach ($query->rows as $result) {
			$extension_data[] = $result['code'];
        }
		return $extension_data;
    }

}
