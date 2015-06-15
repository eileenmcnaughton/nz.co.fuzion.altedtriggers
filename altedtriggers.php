<?php

require_once 'altedtriggers.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function altedtriggers_civicrm_config(&$config) {
  _altedtriggers_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function altedtriggers_civicrm_xmlMenu(&$files) {
  _altedtriggers_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function altedtriggers_civicrm_install() {
  return _altedtriggers_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function altedtriggers_civicrm_uninstall() {
  return _altedtriggers_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function altedtriggers_civicrm_enable() {
  return _altedtriggers_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function altedtriggers_civicrm_disable() {
  return _altedtriggers_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function altedtriggers_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _altedtriggers_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function altedtriggers_civicrm_managed(&$entities) {
  return _altedtriggers_civix_civicrm_managed($entities);
}

/**
 * hook_civicrm_triggerInfo()
 *
 * Add trigger to update custom altED calculated fields
 *  1) - updated civicrm_value_goal_and_reviews_16.most_recent_status_225 to hold value of most recent progress status
 *
 * @param array $info (reference) array of triggers to be created
 * @param string $tableName - not sure how this bit works
 *
 **/

function altedtriggers_civicrm_triggerInfo(&$info, $tableName) {
  $sql = "
REPLACE INTO civicrm_value_goal_and_reviews_16 (entity_id, most_recent_status_225)
SELECT contact_id, b.region
FROM
civicrm_address a INNER JOIN $zipTable b ON a.postal_code = b.zip
WHERE a.contact_id = NEW.contact_id
ORDER BY is_primary DESC, FIELD(location_type_id, $locationPriorityOrder )
) as regionlist
;
";
  $sql_field_parts = array();

  $info[] = array(
    'table' => $sourceTable,
    'when' => 'AFTER',
    'event' => 'INSERT',
    'sql' => $sql,
  );
  $info[] = array(
    'table' => $sourceTable,
    'when' => 'AFTER',
    'event' => 'UPDATE',
    'sql' => $sql,
  );
  // For delete, we reference OLD.contact_id instead of NEW.contact_id
  $sql = str_replace('NEW.contact_id', 'OLD.contact_id', $sql);
  $info[] = array(
    'table' => $sourceTable,
    'when' => 'AFTER',
    'event' => 'DELETE',
    'sql' => $sql,
  );
}
