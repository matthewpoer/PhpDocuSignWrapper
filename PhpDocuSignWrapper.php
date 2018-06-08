<?php
class PhpDocuSignWrapper {
  var $host = '';
  var $auth = array();
  var $account_id = '';
  var $pest = NULL;

  /**
   * Start the DocuSign API interaction by supplying connection directionly
   * upon invocation. Login will happen upon invocation.
   *
   * @var string $host the DocuSign Host to connect to, i.e.
   *                   https://demo.docusign.net/restapi/v2 for testing/demo or
   *                   https://www.docusign.net/restapi/v2 for production.
   * @var string $user the email address/username of the DocuSign account
   * @var string $pass the password of the DocuSign account
   * @var string $key the Integration Key for your DocuSign account check out
   *                  https://developers.docusign.com/esign-rest-api/guides for
   *                  more info.
   */
  public function __construct($host, $user, $pass, $key) {
    $this->host = $host;
    $this->auth = array(
      'Content-Type' => 'application/json',
      'X-DocuSign-Authentication' => json_encode(array(
        'Username' => $user,
        'Password' => $pass,
        'IntegratorKey' => $key
    )));

    $this->pest = new Pest($this->host);
    $this->login();
  }

  /**
   * Send the API call through the Pest client. Specify everything we might want
   * to specify for a RESTful API call, including method, URL params, and
   * headers. Standard Auth. headers for DocuSign will be automatically
   * included, but can be overridden or appended to. Returns the JSON-decoded
   * array.
   * @param string $method lower-case REST verb, e.g. get, put, post, etc.
   * @param string $url the endpoint, not includeing the host or "/v2" portion
   * @param array $get_params optional GET parameters
   * @param array $additional_headers
   * @return array
   */
  private function _call(
    $method = 'get',
    $url,
    $get_params = array(),
    $additional_headers = array()
  ) {
    $headers = array_merge($this->auth, $additional_headers);
    $thing = $this->pest->$method($url, $get_params, $headers);
    return json_decode($thing, TRUE);
  }

  /**
   * Authenticate with the DocuSign API. This will specify the Account ID for
   * all API calls to follow.
   */
  private function login() {
    $params = array('api_password', TRUE);
    $result = $this->_call('get', '/login_information', $params);
    $this->account_id = $result['loginAccounts'][0]['accountId'];
    return TRUE;
  }

  /**
   * Get a list of all envelopes' IDs from a certain date. If not date is
   * specified then we will send 1970-01-01 and fetch all envelopes.
   * @param string $from date in YYYY-mm-dd format
   * @return array
   */
  public function get_envelopes($from = '1970-01-01') {
    $url = '/accounts/' . $this->account_id . '/envelopes?from_date=' . $from;
    $result = $this->_call('get', $url);
    $envelopes = array();
    foreach($result['envelopes'] as $envelope) {
      $envelopes[$envelope['envelopeId']] = array();
    }
    return $envelopes;
  }

  /**
   * Get an array list of all of the recipients associated with a specified
   * envelope.
   * @param string $envelope_id the ID of the envelope
   * @return array
   */
  public function get_recipients_for_envelope($envelope_id) {
    $url = '/accounts/' . $this->account_id . '/envelopes/' . $envelope_id . '/recipients';
    $result = $this->_call('get', $url);
    $recipients = array();
    foreach($result['signers'] as $signers) {
      $recipients[$signers['recipientId']] = array();
    }
    return $recipients;
  }

  /**
   * Note that "Tabs" is a DocuSign term that to a technically minded person
   * might mean "field". Each Tab is a field with a label and a value and is
   * associated with a specific recipient. This method will build and return an
   * associative array of strucurted field-types, field-names and field-values
   * for a specific recipient and a specific envelope. Return array format will
   * resemble the following:
   * <code>
   * array(
   *   emailAddressTabs = array(
   *     'tabId' => array('name' => 'value')
   *   ),
   *   textTabs = array(
   *     'tabId' => array('tabLabel' => 'value'),
   *     'tabId' => array('tabLabel' => 'value')
   *   ),
   *   signHereTabs = array(
   *     'tabId' => array('SignHere' => 'signed')
   *     'tabId' => array('SignHere' => FALSE)
   *   )
   * )
   * </code>
   * @param string $envelope_id the ID of the envelope to search
   * @param string $recipient_id the ID of the recipient to search
   * @return array of field types, fields and values
   */
  public function get_tabs_for_recipient_for_envelope($envelope_id, $recipient_id) {
    $url = '/accounts/' . $this->account_id . '/envelopes/' . $envelope_id . '/recipients/' . $recipient_id . '/tabs';
    $result = $this->_call('get', $url);
    $tabs_and_fields_and_values = array();
    foreach($result as $key => $value) {
      if(!isset($tabs_and_fields_and_values[$key])) {
        $tabs_and_fields_and_values[$key] = array();
      }
      foreach($value as $form_key => $form_data) {
        switch ($key) {
          case 'signHereTabs':
            $status = !empty($form_data['status']) ? $form_data['status'] : FALSE;
            $tabs_and_fields_and_values[$key][$form_data['tabId']] = array($form_data['tabLabel'] => $status);
            break;
          case 'textTabs':
          case 'fullNameTabs':
          case 'emailAddressTabs':
          default:
            if(empty($form_data['value'])) {
              $form_data['value'] = '';
            }
            $tabs_and_fields_and_values[$key][$form_data['tabId']] = array($form_data['tabLabel'] => $form_data['value']);
            break;
        }
      }
    }
    return $tabs_and_fields_and_values;
  }
}
