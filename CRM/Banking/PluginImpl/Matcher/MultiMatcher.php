<?php

require_once 'CRM/Banking/Helpers/OptionValue.php';

/**
 * This matcher tries to reconcile the payments with existing contributions.
 * There are two modes:
 *   default      - matches e.g. to pending contributions and changes the
 * status to completed cancellation - matches negative amounts to completed
 * contributions and changes the status to cancelled
 */
class CRM_Banking_PluginImpl_Matcher_MultiMatcher extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->threshold)) {
      $config->threshold = 0.9;
    }
    if (!isset($config->required_values)) {
      $config->required_values = [];
    }
    if (!isset($config->contribution_selector)) {
      $config->contribution_selector = [];
    }
    if (!isset($config->amount_penalty)) {
      $config->amount_penalty = 0.5;
    }
    if (!isset($config->value_propagation)) {
      $config->value_propagation = [];
    }
  }

  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    // Log
    // watchdog("CiviCRM banking: (MultiMatcher)", '<pre>' . print_r($btx, true) . '</pre>');

    $config = $this->_plugin_config;
    $threshold = $config->threshold;
    $data_parsed = $btx->getDataParsed();

    // Check requirements
    foreach ($config->required_values as $required_key) {
      if (!isset($data_parsed[$required_key])) {
        // log
        // watchdog("CiviCRM banking: (MultiMatcher)", '<pre>BAIL!</pre>');
        // there is no value given for this key => bail
        return NULL;
      }
    }

    // Don't do anything if we don't have a single criteria
    if (empty($data_parsed[$required_key])) {
      return NULL;
    }

    // Find and load the contributions
    $query = ['version' => 3, 'sequential' => 1];
    // Replace tokens
    foreach ($config->contribution_selector as $criteria) {
      $value = $criteria[1];
      if (strrpos($value, '{') == 0 && strrpos($value, '}') == strlen($value) - 1) {
        // this is a token look up value
        $token = substr($value, 1, strlen($value) - 2);
        if (isset($data_parsed[$token])) {
          $value = $data_parsed[$token];
        }
      }
      $query[$criteria[0]] = $value;
    }

    // Load the contributions and evaluate
    $results = civicrm_api('Contribution', 'get', $query);
    if (!empty($results['is_error'])) {
      // Log
      // watchdog("CiviCRM banking: (MultiMatcher)", '<pre>Query failed:' . $results['error_message'] . '</pre>');
      return NULL;
    }
    // If there is no contributions, quit
    if (empty($results['values'])) {

      // TODO: do we want to allow this under certain circumstances?
      //  if so, add a config option

      // Log results
      // watchdog("CiviCRM banking: (MultiMatcher)", '<pre>'. print_r($results, true) .'</pre>' );
      return NULL;
    }

    // Load contact data (renew variable)
    $cid = $results['values']['0']['contact_id'];
    $query = ['version' => 3, 'sequential' => 1, 'contact_id' => $cid];
    $contact = civicrm_api('Contact', 'get', $query);

    // Gather information
    $probability = 1.0;
    $contribution_ids = [];
    $total_amount = 0.0;
    $max_date = 0;
    $min_date = 9999999999;

    // Contributions
    foreach ($results['values'] as $contribution) {
      $contribution_ids[] = $contribution['id'];
      $total_amount += $contribution['total_amount'];
      $receive_date = strtotime($contribution['receive_date']);
      if ($receive_date > $max_date) {
        $max_date = $receive_date;
      }
      if ($receive_date < $min_date) {
        $min_date = $receive_date;
      }
    }

    // Evaluate the results
    $amount_delta = abs($total_amount - $btx->amount);
    if ($amount_delta != 0) {
      $probability -= $config->amount_penalty;
    }

    $time_range = $max_date - $min_date;
    // TODO: add a penalty for a difference in the receive date?
    $time_delta = abs(strtotime($btx->booking_date) - ($max_date + $min_date) / 2.0);
    // TODO: add a penalty for a difference from the booking date?
    // Create a suggestion
    $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
    $suggestion->setProbability($probability);
    // Gather information
    $suggestion->setId("multi-" . implode('-', $contribution_ids));
    $suggestion->setParameter('contribution_ids', $contribution_ids);
    $suggestion->setParameter('ogm', $data_parsed['ogm']);
    $suggestion->setParameter('cid', $cid);
    $suggestion->setParameter('paynum', $data_parsed['product']);
    $suggestion->setParameter('amount', $btx->amount);
    $suggestion->setParameter('date', $btx->booking_date);
    // Gather information
    $btx->addSuggestion($suggestion);
    // That's it...
    return empty($this->_suggestions) ? NULL : $this->_suggestions;
  }

  /**
   * Handle the different actions, should probably be handled at base class
   * level ...
   *
   * @param object $suggestion
   * @param object $btx
   *
   * @return boolean
   */
  public function execute($suggestion, $btx) {
    $ogm = $suggestion->getParameter('ogm');
    $cid = $suggestion->getParameter('cid');
    $con = $suggestion->getParameter('contribution_ids');

    // Set contribution to completed
    foreach ($con as $contribution) {
      // Log
      // watchdog("CiviCRM banking: (MultiMatcher)", "Called execute for cid=$cid, ogm=$ogm, contribution id: " . $contribution);
      // Set contribution to complete via API call
      try {
        $result = civicrm_api3('Contribution', 'completetransaction', [
          'sequential' => 1,
          'id' => $contribution,
        ]);
      } catch (Exception $e) {
        // watchdog("CiviCRM banking: (MultiMatcher)", '<pre>Caught exception: ' . $e->getMessage() . '</pre>');
      }
    }

    // Set the status of the transaction to complete
    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);
    // Return
    return TRUE;
  }

  /**
   * Generate html code to visualize the given match. The visualization may
   * also provide interactive form elements.
   *
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return string code snippet
   */
  function visualize_match(CRM_Banking_Matcher_Suggestion $match, $btx) {
    // Load the contribution
    $ogm = $match->getParameter('ogm');
    $cid = $match->getParameter('cid');
    $con = $match->getParameter('contribution_ids');
    // Gather information
    $contact_id = "<a href='/civicrm/contact/view?reset=1&cid=$cid' target='blank'>$cid</a>";
    // Loop contributions
    $contribution_ids = [];
    foreach ($con as $contribution) {
        $contribution_ids[] = "<a href='/civicrm/contact/view/contribution?reset=1&id=$contribution&action=view' target='blank'>$contribution</a>";
    }
    // Generate output.
    $text = "<div>The payment can be concealed with the ogm:$ogm (contact: $contact_id) (contribution:" . implode(',', $contribution_ids) . ")</div>";
    // Return
    return $text;
  }

  /**
   * Generate html code to visualize the executed match.
   *
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return string code snippet
   */
  function visualize_execution_info(CRM_Banking_Matcher_Suggestion $match, $btx) {
    // TODO: make nicer...
    // visualize
    $ogm = $match->getParameter('ogm');
    $text = "<p>This payment is associated with the following ogm: " . $ogm . "</p>";
    return $text;
  }
}
