<?php
/**
 * SSLCommerz Gateway.
 *
 * The SSLCommerz API documentation can be found at:
 * https://developer.sslcommerz.com/docs.html
 *
 * @package blesta
 * @subpackage blesta.components.gateways.sslcommerz
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Sslcommerz extends NonmerchantGateway
{
    /**
     * @var string The version of this gateway
     */
    private static $version = '1.0.0';

    /**
     * @var string The authors of this gateway
     */
    private static $authors = [['name'=>'Phillips Data, Inc.','url'=>'http://www.blesta.com']];

    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * Construct a new merchant gateway.
     */
    public function __construct()
    {
        // Load components required by this gateway
        Loader::loadComponents($this, ['Input']);

        // Load the language required by this gateway
        Language::loadLang('sslcommerz', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Returns the name of this gateway.
     *
     * @return string The common name of this gateway
     */
    public function getName()
    {
        return Language::_('Sslcommerz.name', true);
    }

    /**
     * Returns the version of this gateway.
     *
     * @return string The current version of this gateway
     */
    public function getVersion()
    {
        return self::$version;
    }

    /**
     * Returns the name and URL for the authors of this gateway.
     *
     * @return array The name and URL of the authors of this gateway
     */
    public function getAuthors()
    {
        return self::$authors;
    }

    /**
     * Return all currencies supported by this gateway.
     *
     * @return array A numerically indexed array containing all currency codes (ISO 4217 format) this gateway supports
     */
    public function getCurrencies()
    {
        return ['BDT'];
    }

    /**
     * Sets the currency code to be used for all subsequent payments.
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway.
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        $this->view = $this->makeView('settings', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('meta', $meta);

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway.
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Verify meta data is valid
        $rules = [
            'store_id' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Sslcommerz.!error.store_id.valid', true)
                ]
            ],
            'store_password' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Sslcommerz.!error.store_password.valid', true)
                ]
            ]
        ];

        // Set checkbox if not set
        if (!isset($meta['dev_mode'])) {
            $meta['dev_mode'] = 'false';
        }

        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);

        // Return the meta data, no changes required regardless of success or failure for this gateway
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database.
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return ['store_id', 'store_password'];
    }

    /**
     * Sets the meta data for this particular gateway.
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Returns all HTML markup required to render an authorization and capture payment form.
     *
     * @param array $contact_info An array of contact info including:
     *  - id The contact ID
     *  - client_id The ID of the client this contact belongs to
     *  - user_id The user ID this contact belongs to (if any)
     *  - contact_type The type of contact
     *  - contact_type_id The ID of the contact type
     *  - first_name The first name on the contact
     *  - last_name The last name on the contact
     *  - title The title of the contact
     *  - company The company name of the contact
     *  - address1 The address 1 line of the contact
     *  - address2 The address 2 line of the contact
     *  - city The city of the contact
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-cahracter country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the contact
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *  - description The Description of the charge
     *  - return_url The URL to redirect users to after a successful payment
     *  - recur An array of recurring info including:
     *      - start_date The date/time in UTC that the recurring payment begins
     *      - amount The amount to recur
     *      - term The term to recur
     *      - period The recurring period (day, week, month, year, onetime) used in
     *          conjunction with term in order to determine the next recurring payment
     * @return mixed A string of HTML markup required to render an authorization and
     *  capture payment form, or an array of HTML markup
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        // Load the models required
        Loader::loadModels($this, ['Clients', 'Contacts']);

        // Load the helpers required
        Loader::loadHelpers($this, ['Html']);

        // Load library methods
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'sslcommerz_api.php');
        $api = new SslcommerzApi($this->meta['store_id'], $this->meta['store_password'], $this->meta['dev_mode']);

        // Force 2-decimal places only
        $amount = number_format($amount, 2, '.', '');

        // Get client data
        $client = $this->Clients->get($contact_info['client_id']);

        // Get client phone number
        $contact_numbers = $this->Contacts->getNumbers($client->contact_id);

        $client_phone = '';
        foreach ($contact_numbers as $contact_number) {
            switch ($contact_number->location) {
                case 'home':
                    // Set home phone number
                    if ($contact_number->type == 'phone') {
                        $client_phone = $contact_number->number;
                    }
                    break;
                case 'work':
                    // Set work phone/fax number
                    if ($contact_number->type == 'phone') {
                        $client_phone = $contact_number->number;
                    }
                    // No break?
                case 'mobile':
                    // Set mobile phone number
                    if ($contact_number->type == 'phone') {
                        $client_phone = $contact_number->number;
                    }
                    break;
            }
        }

        if (!empty($client_phone)) {
            $client_phone = preg_replace('/[^0-9]/', '', $client_phone);
        } else {
            $client_phone = '01711111111'; // Dummy phone number
        }

        // Set all invoices to pay
        if (isset($invoice_amounts) && is_array($invoice_amounts)) {
            $invoices = $this->serializeInvoices($invoice_amounts);
        }

        // Build the payment request
        $notification_url = Configure::get('Blesta.gw_callback_url') . Configure::get('Blesta.company_id') . '/sslcommerz/?client_id=' . $contact_info['client_id'];
        $params = [
            'total_amount' => $this->ifSet($amount),
            'currency' => $this->ifSet($this->currency),
            'tran_id' => uniqid(),
            'success_url' => $this->ifSet($notification_url),
            'fail_url' => $this->ifSet($notification_url),
            'cancel_url' => $this->ifSet($options['return_url']),
            'emi_option' => 0,
            'cus_name' => $this->Html->concat(
                ' ',
                $this->ifSet($contact_info['first_name']),
                $this->ifSet($contact_info['last_name'])
            ),
            'cus_email' => $this->ifSet($client->email),
            'cus_phone' => $this->ifSet($client_phone),
            'value_a' => $this->ifSet($invoices),
            'value_b' => $this->ifSet($client->id)
        ];
        $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($params), 'input', true);

        // Send the request to the api
        $request = $api->buildPayment($params);

        // Build the payment form
        try {
            if ($request->status == 'SUCCESS') {
                $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($request), 'output', true);

                return $this->buildForm($request->redirectGatewayURL);
            } else {
                // The api has been responded with an error, set the error
                $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($request), 'output', false);
                $this->Input->setErrors(
                    ['api' => ['response' => $request->failedreason]]
                );

                return null;
            }
        } catch (Exception $e) {
            $this->Input->setErrors(
                ['internal' => ['response' => $e->getMessage()]]
            );
        }
    }

    /**
     * Builds the HTML form.
     *
     * @param string $post_to The URL to post to
     * @return string The HTML form
     */
    private function buildForm($post_to)
    {
        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('post_to', $post_to);

        return $this->view->fetch();
    }

    /**
     * Validates the incoming POST/GET response from the gateway to ensure it is
     * legitimate and can be trusted.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's
     *      original transaction (in the case of refunds)
     */
    public function validate(array $get, array $post)
    {
        // Load library methods
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'sslcommerz_api.php');
        $api = new SslcommerzApi($this->meta['store_id'], $this->meta['store_password'], $this->meta['dev_mode']);

        // Get invoices
        $invoices = $this->ifSet($post['value_a']);

        // Get the transaction details
        $response = $api->getPayment($post['tran_id']);

        // Capture the transaction status of all the tenders, or reject it if at least one tender is invalid
        $status = 'error';
        $return_status = false;

        if (isset($response->status)) {
            switch ($response->status) {
                case 'VALID':
                    $status = 'approved';
                    $return_status = true;
                    break;
                case 'FAILED':
                    $status = 'declined';
                    $return_status = true;
                    break;
            }
        }

        // Log response
        $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($response), 'output', $return_status);

        // Get payment details
        $amount = number_format($response->currency_amount, 2, '.', '');
        $currency = $response->currency_type;

        // Get client id
        $client_id = $this->ifSet($get['client_id'], $post['value_b']);

        return [
            'client_id' => $client_id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $this->ifSet($response->bank_tran_id, $post['tran_id']),
            'invoices' => $this->unserializeInvoices($invoices)
        ];
    }

    /**
     * Returns data regarding a success transaction. This method is invoked when
     * a client returns from the non-merchant gateway's web site back to Blesta.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, may set errors using Input if the data appears invalid
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction
     */
    public function success(array $get, array $post)
    {
        // Load library methods
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'sslcommerz_api.php');
        $api = new SslcommerzApi($this->meta['store_id'], $this->meta['store_password'], $this->meta['dev_mode']);

        // Get invoices
        $invoices = $this->ifSet($post['value_a']);

        // Get the transaction details
        $response = $api->getPayment($post['tran_id']);

        // Get payment details
        $amount = number_format($response->currency_amount, 2, '.', '');
        $currency = $response->currency_type;

        // Get client id
        $client_id = $this->ifSet($get['client_id'], $post['value_b']);

        return [
            'client_id' => $client_id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'approved', // we wouldn't be here if it weren't, right?
            'reference_id' => null,
            'transaction_id' => $this->ifSet($response->bank_tran_id, $post['tran_id']),
            'invoices' => $this->unserializeInvoices($invoices)
        ];
    }

    /**
     * Captures a previously authorized payment.
     *
     * @param string $reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The transaction ID for the previously authorized transaction.
     * @param $amount The amount.
     * @param array $invoice_amounts
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function capture($reference_id, $transaction_id, $amount, array $invoice_amounts = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * Void a payment or authorization.
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param string $notes Notes about the void that may be sent to the client by the gateway
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function void($reference_id, $transaction_id, $notes = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * Refund a payment.
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param float $amount The amount to refund this card
     * @param string $notes Notes about the refund that may be sent to the client by the gateway
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function refund($reference_id, $transaction_id, $amount, $notes = null)
    {
        // Load library methods
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'sslcommerz_api.php');
        $api = new SslcommerzApi($this->meta['store_id'], $this->meta['store_password'], $this->meta['dev_mode']);

        // Build the payment refund
        $params = [
            'bank_tran_id' => $this->ifSet($transaction_id),
            'refund_amount' => $this->ifSet($amount),
            'refund_remarks' => $this->ifSet($notes)
        ];
        $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($params), 'input', true);

        // Send the payment request to the api
        $response = $api->refundPayment($params);

        // Capture the transaction status of all the tenders, or reject it if at least one tender is invalid
        $status = 'error';
        $return_status = false;

        if (isset($response->status)) {
            switch ($response->status) {
                case 'success':
                    $status = 'refunded';
                    $return_status = true;
                    break;
                case 'refunded':
                    $status = 'refunded';
                    $return_status = true;
                    break;
                case 'processing':
                    $status = 'pending';
                    $return_status = true;
                    break;
                case 'cancelled':
                    $status = 'returned';
                    $return_status = true;
                    break;
            }
        }

        // Log response
        $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($response), 'output', $return_status);

        return [
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $this->ifSet($transaction_id),
            'message' => $this->ifSet($response->errorReason)
        ];
    }

    /**
     * Serializes an array of invoice info into a string.
     *
     * @param array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     * @return string A serialized string of invoice info in the format of key1=value1|key2=value2
     */
    private function serializeInvoices(array $invoices)
    {
        $str = '';
        foreach ($invoices as $i => $invoice) {
            $str .= ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
        }

        return $str;
    }

    /**
     * Unserializes a string of invoice info into an array.
     *
     * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
     * @param mixed $str
     * @return array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     */
    private function unserializeInvoices($str)
    {
        $invoices = [];
        $temp = explode('|', $str);
        foreach ($temp as $pair) {
            $pairs = explode('=', $pair, 2);
            if (count($pairs) != 2) {
                continue;
            }
            $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
        }

        return $invoices;
    }
}
