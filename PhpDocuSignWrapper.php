<?php
class PhpDocuSignWrapper {
  private $host = '';
  private $auth = array();
  private $account_id = '';
  private $pest = NULL;

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
  public function __construct($host, $user, $pass, $key, $account_id) {
    $this->host = $host;
    $this->auth = array(
      'Content-Type' => 'application/json',
      'X-DocuSign-Authentication' => json_encode(array(
        'Username' => $user,
        'Password' => $pass,
        'IntegratorKey' => $key
    )));

    $this->pest = new Pest($this->host);
    $this->login($account_id);
  }

  /**
   * Send the API call through the Pest client. Specify everything we might want
   * to specify for a RESTful API call, including method, URL params, and
   * headers. Standard Auth. headers for DocuSign will be automatically
   * included, but can be overridden or appended to. Returns the JSON-decoded
   * array.
   * @param string $method lower-case REST verb, e.g. get, put, post, etc.
   * @param string $url the endpoint, not includeing the host or "/v2" portion
   *               or any leading slashes. If $include_account is TRUE then the
   *               passed-in URL does not need to include the Account reference
   * @param array $get_params optional GET parameters
   * @param array $additional_headers
   * @param bool $include_account automatically include /account/AccountId in
   *             the URL we're calling. Default TRUE because the only time we
   *             seem to not use this is on login
   * @return array
   */
  private function _call(
    $method = 'get',
    $url,
    $get_params = array(),
    $additional_headers = array(),
    $include_account = TRUE
  ) {

    if($include_account) {
      $url = '/accounts/' . $this->account_id . '/' . $url;
    }

    // do not forget leading slash
    if(substr($url, 0, 1) != '/') {
      $url = '/' . $url;
    }

    $headers = array_merge($this->auth, $additional_headers);
    $thing = $this->pest->$method($url, $get_params, $headers);
    return json_decode($thing, TRUE);
  }

  /**
   * Authenticate with the DocuSign API. This will specify the Account ID for
   * all API calls to follow.
   * @param $account_id string specify the Account to interact with
   * @param $second bool Is this the secondary login call?
   *        note that we must login to the www. host to confirm access and the
   *        correct host, then call the login API a second time on the correct
   *        production host (e.g. NA3).
   */
  private function login($account_id, $second = FALSE) {
    $params = array();
    $result = $this->_call('get', 'login_information', $params, array(), FALSE);
    foreach($result['loginAccounts'] as $loginAccount) {
      if($loginAccount['accountId'] == $account_id) {
        $this->account_id = $account_id;
        $this->host =
          'https://'
          . parse_url($loginAccount['baseUrl'], PHP_URL_HOST)
          . '/restapi/v2/';
        $this->pest = new Pest($this->host);
        return $second ? TRUE : $this->login($account_id, TRUE);
      }
    }
    throw new Exception("Unable to access specified Account ID: {$account_id}");
    return FALSE;
  }

  /**
   * Get a list of all envelopes' IDs from a certain date. If not date is
   * specified then we will send 1970-01-01 and fetch all envelopes.
   * @param string $from date in YYYY-mm-dd format
   * @param array $other_filters array of filters as key-values pairs to be
   *              included in the API request. If empty, no filter will be used
   * @return array
   */
  public function get_envelopes($from = '1970-01-01', $other_filters = array()) {
    $from = urlencode($from);
    $url = 'envelopes?from_date=' . $from;
    foreach($other_filters as $key => $value) {
      if(is_array($value)) {
        foreach($value as $value_key => $value_value) {
          $value[$value_key] = urlencode($value_value);
        }
        $value = implode($value,',');
      }
      else {
        $value = urlencode($value);
      }
      $url .= '&' . $key . '=' . $value;
    }
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
    $url = 'envelopes/' . $envelope_id . '/recipients';
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
   * @param bool $name_value_only adjust output organization by grouping fields
   *             by field-type and index by Field ID (default false)
   * @return array of field types, fields and values
   */
  public function get_tabs_for_recipient_for_envelope($envelope_id, $recipient_id, $name_value_only = TRUE) {
    $url = 'envelopes/' . $envelope_id . '/recipients/' . $recipient_id . '/tabs';
    $result = $this->_call('get', $url);
    $tabs_and_fields_and_values = array();
    foreach($result as $key => $value) {
      foreach($value as $form_key => $form_data) {
        switch ($key) {
          case 'radioGroupTabs':
            $field_label = $form_data['groupName'];
            $field_value = '';
            foreach($form_data['radios'] as $radio) {
              if($radio['selected'] == 'true') {
                $field_value = $radio['value'];
              }
            }
            break;
          case 'signHereTabs':
            $field_label = $form_data['tabLabel'];
            $field_value = !empty($form_data['status']) ? $form_data['status'] : '0';
            break;
          case 'checkboxTabs':
            $field_label = $form_data['tabLabel'];
            $field_value = '0';
            if(!empty($form_data['selected']) && $form_data['selected'] == 'true') {
              $field_value = '1';
            }
            break;
          case 'initialHereTabs':
            $field_label = $form_data['tabLabel'];
            $field_value = '0';
            if(!empty($form_data['status']) && $form_data['status'] == 'signed') {
              $field_value = '1';
            }
            break;
          case 'textTabs':
          case 'fullNameTabs':
          case 'emailAddressTabs':
          default:
            $field_label = $form_data['tabLabel'];
            $field_value = empty($form_data['value']) ? '' : $form_data['value'];
            break;
        }

        if($name_value_only) {
          $tabs_and_fields_and_values[$field_label] = $field_value;
        }
        else {
          if(!isset($tabs_and_fields_and_values[$key])) {
            $tabs_and_fields_and_values[$key] = array();
          }
          $tabs_and_fields_and_values[$key][$form_data['tabId']] = array($field_label => $field_value);
        }

      }
    }
    return $tabs_and_fields_and_values;
  }

  /**
   * Get a list of all accessible folders. The list will be flattened and not
   * respect folder hierarchy.
   * @param string $template default empty. Leave it empty to get only envelope
   *     folders, or set to 'include' for both template and envelope folders, or
   *     set to 'only' for what probably should be only template folders, but in
   *     my experience is unpredictable and unrelated to any list of folders you
   *     see in the DocuSign UI
   * @return array of folders, with the ID as the key and Name as the value
   */
  public function get_folders($template = '') {
    if(
      !empty($template)
      && $template != 'only'
      && $template != 'include'
    ) {
      throw new Exception("Invalid Template Setting. \$template value can be set to an empty string _or_ 'include' _or 'both'");
    }

    $payload = 'folders';
    if(!empty($template)) {
      $payload .= '?template=' . $template;
    }

    $result = $this->_call('get', $payload);
    $folders = array();
    if(empty($result['folders'])) {
      return $folders;
    }
    foreach($result['folders'] as $folder) {
      $folders[$folder['folderId']] = $folder['name'];
      $this->get_folders_recusive($folders, $folder);
    }
    return $folders;
  }

  /**
   * walk through any of child folders of the current folder, and child folders
   * of those child folders...
   * @see get_folders()
   */
  private function get_folders_recusive(&$folders, $folder) {
    $folder['folders'] = empty($folder['folders']) ? array() : $folder['folders'];
    foreach($folder['folders'] as $child_folder) {
      $folders[$child_folder['folderId']] = $child_folder['name'];
      $this->get_folders_recusive($folders, $child_folder);
    }
  }

  /**
   * list envelopes in a folder
   * @todo DocuSign API docs. state 100 envelopes will be returned at a time,
   *       but this does not currently check $result['totalSetSize'] and ask for
   *       more like it should
   * @param string $folderId the ID of the folder to check
   * @param bool $include_status whether or not to include the sending status in
   *             the name of the envelope
   * @return array of envelopes inside a folder
   */
  public function get_folder_contents($folderId, $include_status = FALSE) {
    $result = $this->_call('get', 'folders/' . $folderId);
    $envelopes = array();
    if(empty($result['folderItems'])) {
      return $envelopes;
    }
    foreach($result['folderItems'] as $envelope) {
      $subject = $envelope['subject'];
      if($include_status) {
        $subject .= ' (' . $envelope['status'] . ')';
      }
      $envelopes[$envelope['envelopeId']] = $subject;
    }
    return $envelopes;
  }

  /**
   * get list of DocuSign users
   * @param bool $active_only toggle TRUE to only see active users
   * @return array of (userId => userName) for all users
   */
  public function get_users($active_only = FALSE) {
    $params = array();
    if($active_only) {
      $params = array('status' => 'Active');
    }
    $result = $this->_call('get', 'users', $params);
    $users = array();
    foreach($result['users'] as $user) {
      $users[$user['userId']] = $user['userName'];
    }
    return $users;
  }

  /**
   * find all groups associated with a given user
   * @param string $userId
   * @return array of group info
   */
  public function get_user_groups($userId) {
    $result = $this->_call('get', 'users/' . $userId);
    $groups = array();
    if(empty($result['groupList'])) {
      return $groups;
    }
    foreach($result['groupList'] as $groupInfo) {
      $groups[$groupInfo['groupId']] = $groupInfo['groupName'];
    }
    return $groups;
  }

  public function get_templates_for_envelope($envelopeId) {
    $result = $this->_call('get', 'envelopes/' . $envelopeId . '/templates');
    $templates = array();
    if(empty($result['templates'])) {
      return $templates;
    }
    foreach($result['templates'] as $templateInfo) {
      $templates[$templateInfo['templateId']] = $templateInfo['name'];
    }
    return $templates;
  }

  public function get_documents_for_envelope($envelopeId) {
    $result = $this->_call('get', 'envelopes/' . $envelopeId . '/documents/combined');
    if($result === NULL && !empty($this->pest->last_response['body'])) {
      return $this->pest->last_response['body'];
    }
    return NULL;
  }

  /**
   * Get a list of Templates contained in a folder, specified by the folder ID
   * @param string $folderId the ID of a folder. DocuSign API documentation
   *               suggests that a string Folder Name is also valid but I have
   *               not had luck with this.
   * @return array keys are Template IDs and values are Template Names
   */
  public function get_templates_in_folder($folderId) {
    $result = $this->_call('get', 'templates?folder=' . $folderId);
    $templates = array();
    if(empty($result['envelopeTemplates'])) {
      return $templates;
    }
    foreach($result['envelopeTemplates'] as $envelopeTemplates) {
      $templates[$envelopeTemplates['templateId']] = $envelopeTemplates['name'];
    }
    return $templates;
  }
}
