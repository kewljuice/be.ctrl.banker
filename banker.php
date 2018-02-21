<?php

require_once 'banker.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function banker_civicrm_config(&$config) {
  _banker_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function banker_civicrm_xmlMenu(&$files) {
  _banker_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function banker_civicrm_install() {
  _banker_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function banker_civicrm_postInstall() {
  _banker_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function banker_civicrm_uninstall() {
  _banker_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function banker_civicrm_enable() {
  _banker_civix_civicrm_enable();

  // Add our 'matcher_multi' Plugin to the list.
  banker_civicrm_addPluginToList('matcher_multi', 'Matcher Multi', 'CRM_Banking_PluginImpl_Matcher_MultiMatcher');

  // Add our 'matcher_name' Plugin to the list.
  banker_civicrm_addPluginToList('matcher_name', 'Matcher Name', 'CRM_Banking_PluginImpl_Matcher_NameMatcher');

}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function banker_civicrm_disable() {
  // TODO remove plugin(s).
  _banker_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function banker_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _banker_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function banker_civicrm_managed(&$entities) {
  _banker_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function banker_civicrm_caseTypes(&$caseTypes) {
  _banker_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function banker_civicrm_angularModules(&$angularModules) {
  _banker_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function banker_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _banker_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Creates a CiviBanking Plugin.
 *
 * @param string $name CiviBanking Plugin name
 * @param string $label CiviBanking Plugin label
 * @param string $value CiviBanking plugin class
 *
 */
function banker_civicrm_addPluginToList($name, $label, $value) {
  // Add our plugin to the list.
  try {
    $plugin_types = civicrm_api3('OptionGroup', 'get', ['name' => ('civicrm_banking.plugin_types')]);
    if (!empty($plugin_types['id'])) {
      $optionValue = civicrm_api3('OptionValue', 'get', [
        'sequential' => 1,
        'name' => $name,
        'option_group_id' => "civicrm_banking.plugin_types",
      ]);
      if (empty($optionValue['id'])) {
        // Doesn't exist yet.
        civicrm_api3('OptionValue', 'create', [
          'option_group_id' => $plugin_types['id'],
          'name' => $name,
          'label' => $label,
          'value' => $value,
          'is_default' => 0,
        ]);
      }
    }
  } Catch (Exception $e) {
    // TODO: log Exception.
  }
}