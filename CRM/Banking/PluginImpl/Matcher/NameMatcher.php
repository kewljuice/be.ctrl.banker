<?php

require_once 'CRM/Banking/Helpers/OptionValue.php';

/**
 * This matcher tries to reconcile the payments with existing contributions.
 * There are two modes:
 *   default      - matches e.g. to pending contributions and changes the
 * status to completed cancellation - matches negative amounts to completed
 * contributions and changes the status to cancelled
 */
class CRM_Banking_PluginImpl_Matcher_NameMatcher extends CRM_Banking_PluginModel_Matcher {

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
    // watchdog("CiviCRM banking: (NameMatcher)", '<pre>' . print_r( $btx, true) . '</pre>');
    $config = $this->_plugin_config;
    $threshold = $config->threshold;
    $data_parsed = $btx->getDataParsed();
    // Check requirements
    foreach ($config->required_values as $required_key) {
      if (!isset($data_parsed[$required_key])) {
        // There is no value given for this key => bail
        return NULL;
      }
    }
    // Define variables
    $cid = "";
    // Attempt to search contact by names
    if (isset($data_parsed['name1']) && isset($data_parsed['name2']) && isset($data_parsed['name3'])) {
      // 1st attempt to find $cid
      $firstname = $data_parsed['name2'];
      $lastname = $data_parsed['name1'];
      $query = [
        'version' => 3,
        'sequential' => 1,
        'first_name' => $firstname,
        'last_name' => $lastname,
      ];
      $results = civicrm_api('Contact', 'getSingle', $query);
      if (isset($results['contact_id'])) {
        $cid = $results['contact_id'];
      }
      // 2nd attempt to find $cid
      if ($cid == "") {
        $firstname = $data_parsed['name2'];
        $lastname = $data_parsed['name3'];
        $query = [
          'version' => 3,
          'sequential' => 1,
          'first_name' => $firstname,
          'last_name' => $lastname,
        ];
        $results = civicrm_api('Contact', 'getSingle', $query);
        if (isset($results['contact_id'])) {
          $cid = $results['contact_id'];
        }
      }
      // 3th attempt to find $cid
      if ($cid == "") {
        $firstname = $data_parsed['name3'];
        $lastname = $data_parsed['name1'] . " " . $data_parsed['name2'];
        $query = [
          'version' => 3,
          'sequential' => 1,
          'first_name' => $firstname,
          'last_name' => $lastname,
        ];
        $results = civicrm_api('Contact', 'getSingle', $query);
        if (isset($results['contact_id'])) {
          $cid = $results['contact_id'];
        }
      }

      // TODO attempt 4 & ...

    }
    // Check for result
    if (!isset($results['contact_id'])) {
      // There is no result for this name combination => bail
      return NULL;
    }
    else {
      // Define display name
      $name = $results['display_name'];
    }
    // Set probability to 25% (cid found)
    $probability = 0.25;
    // Check for contributions
    $result = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contact_id' => $cid,
      'contribution_status_id' => 2,
    ]);
    if ($result['count'] > 0) {
      // Set probability to 80% (pending contributions found)
      $probability = 0.80;
      // Check further
      foreach ($result['values'] as $contrib) {
        $amount1 = $contrib['total_amount'];
        $amount2 = $btx->amount;
        $conid = $contrib['contribution_id'];
        if ($amount1 == $amount2) {
          // Set probability to 85% (pending contribution with same amount found)
          $probability = 0.85;
        }
      }
    }
    // Create a suggestion
    $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
    $suggestion->setProbability($probability);
    // Pass parameters
    $suggestion->setParameter('cid', $cid);
    $suggestion->setParameter('name', $name);
    $suggestion->setParameter('paynum', $data_parsed['product']);
    $suggestion->setParameter('amount', $btx->amount);
    $suggestion->setParameter('date', $btx->booking_date);
    // Save suggestion
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
    // Log
    // watchdog("CiviCRM banking: (NameMatcher)", "Handle NameMatcher as complete");
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
    // Variables
    $cid = $match->getParameter('cid');
    $name = $match->getParameter('name');
    $paynum = $match->getParameter('paynum');
    $amount = $match->getParameter('amount');
    $date = $match->getParameter('date');
    // Visualize
    $text = "";
    $text .= "<br/><div><table border=\"1\"><tr>";
    $text .= "<td><div class=\"btxlabel\">" . ts("Cid") . ":&nbsp;</div></td>";
    $text .= "<td><div class=\"btxlabel\">" . ts("Donor") . ":&nbsp;</div></td>";
    $text .= "<td><div class=\"btxlabel\">" . ts("Number") . ":&nbsp;</div></td>";
    $text .= "<td><div class=\"btxlabel\">" . ts("Amount") . ":&nbsp;</div></td>";
    $text .= "<td><div class=\"btxlabel\">" . ts("Date") . ":&nbsp;</div></td>";
    $text .= "<td align='center'></td>";
    $text .= "</tr><tr>";
    $text .= "<td><div class=\"btxvalue\"><a href='/civicrm/contact/view?reset=1&cid=$cid' target='blank'>" . $cid . "</a></div></td>";
    $text .= "<td><div class=\"btxvalue\">" . $name . "</div></td>";
    $text .= "<td><div class=\"btxvalue\">" . $paynum . "</div></td>";
    $text .= "<td><div class=\"btxvalue\">" . $amount . "</div></td>";
    $text .= "<td><div class=\"btxvalue\">" . $date . "</div></td>";
    $text .= "<td align='center'></td>";
    $text .= "</tr></table></div>";
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
    // Load the registration number
    $name = $match->getParameter('name');
    $text = "<p>This payment was associated with the following name: " . $name . "</p>";
    // Return
    return $text;
  }
}
