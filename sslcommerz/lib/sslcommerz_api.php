<?php
/**
 * SSLCommerz API.
 *
 * @package blesta
 * @subpackage blesta.components.modules.sslcommerz
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class SslcommerzApi
{
    /**
     * @var string The store ID
     */
    private $store_id;

    /**
     * @var string The store password
     */
    private $store_passwd;

    /**
     * @var bool Post transactions to the SSLCommerz sandbox environment
     */
    private $dev_mode;

    /**
     * Initializes the class.
     *
     * @param string $store_id The store ID
     * @param string $store_passwd The store password
     * @param string $dev_mode True, to enable the sandbox environment
     */
    public function __construct($store_id, $store_passwd, $dev_mode = false)
    {
        $this->store_id = $store_id;
        $this->store_passwd = $store_passwd;
        $this->dev_mode = $dev_mode;
    }

    /**
     * Send a request to the SSLCommerz API.
     *
     * @param string $method Specifies the endpoint and method to invoke
     * @param array $params The parameters to include in the api call
     * @param string $type The HTTP request type
     * @return stdClass An object containing the api response
     */
    private function apiRequest($method, array $params = [], $type = 'GET')
    {
        // Select api url
        if ($this->dev_mode) {
            $url = 'https://sandbox.sslcommerz.com/';
        } else {
            $url = 'https://securepay.sslcommerz.com/';
        }

        // Send request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Set authentication details
        $auth = [
            'store_id' => $this->store_id,
            'store_passwd' => $this->store_passwd
        ];
        $params = array_merge($auth, $params);

        // Build GET request
        if ($type == 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            $method = $method . '?' . http_build_query($params);
        }

        // Build POST request
        if ($type == 'POST') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POST, true);

            if (!empty($params)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            }
        }

        // Execute request
        curl_setopt($ch, CURLOPT_URL, $url . trim($method, '/'));
        $data = json_decode(curl_exec($ch));
        curl_close($ch);

        return $data;
    }

    /**
     * Build the payment request.
     *
     * @param array $params An array contaning the following arguments:
     *  - total_amount: The total amount of the sale.
     *  - currency: The currency of the sale.
     *  - tran_id: The unique transaction id.
     *  - success_url: The URL where user will redirect after payment.
     *  - fail_url: The URL where user will redirect after payment.
     *  - cancel_url: The URL where user will redirect after payment.
     *  - emi_option: Enable EMI transaction for this sale.
     *  - cus_name: The customer full name.
     *  - cus_email: The customer email address.
     *  - cus_phone: The customer phone number.
     *  - value_a: Custom paramaeter. (Optional)
     *  - value_b: Custom paramaeter. (Optional)
     *  - value_c: Custom paramaeter. (Optional)
     * @return stdClass An object contaning the api response
     */
    public function buildPayment($params)
    {
        return $this->apiRequest('/gwprocess/v3/api.php', $params, 'POST');
    }

    /**
     * Refund a payment.
     *
     * @param array $params An array contaning the following arguments:
     *  - bank_tran_id: The bank transaction id.
     *  - refund_amount: The amount will be refunded.
     *  - refund_remarks: The reason of refund.
     * @return stdClass An object contaning the api response
     */
    public function refundPayment($params)
    {
        return $this->apiRequest('/validator/api/merchantTransIDvalidationAPI.php', $params);
    }

    /**
     * Get a payment.
     *
     * @param string $tran_id The unique transaction id.
     * @return stdClass An object contaning the api response
     */
    public function getPayment($tran_id)
    {
        $result = $this->apiRequest('/validator/api/merchantTransIDvalidationAPI.php', ['tran_id' => $tran_id]);

        return (isset($result->element[0]) ? $result->element[0] : $result);
    }
}
