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
  _altedtriggers_civix_civicrm_managed($entities);
}

/**

function altedtriggers_civicrm_pre($op, $objectName, $id, &$params) {
 * echo $objectName . ' pre ' . "\n";
 * }
 *
 * function altedtriggers_civicrm_post( $op, $objectName, $objectId, &$objectRef ) {
 * echo $objectName . ' post ' . "\n";
 * }
 *
 * @throws \CiviCRM_API3_Exception
 * @internal param $objectName
 * @internal param $id
 */

/**
 * Update reviews based on values in review related custom fields.
 *
 * @param string $op
 * @param int $groupID
 * @param int $entityID
 * @param array $params
 *
 * @throws \CiviCRM_API3_Exception
 * @throws \Exception
 */
function altedtriggers_civicrm_custom($op, $groupID, $entityID, &$params) {
  // Declare hard coded fields.
  $targetGroupID = 16;
  $reviewStatusFields = array(
    1 => 139,
    2 => 143,
    3 => 145,
    4 => 147,
  );
  $reviewDateFields = array(
    1 => 138,
    2 => 142,
    3 => 144,
    4 => 146,
  );

  if ($groupID != $targetGroupID) {
    return;
  }
  $fieldValues = _altedtriggers_convert_params_to_fields($params);
  $updatedStatusFields = array_intersect_key(array_flip($reviewStatusFields), $fieldValues);
  if (empty($updatedStatusFields)) {
    return;
  }

  $activityStartDate = civicrm_api3('activity', 'getvalue', array(
    'id' => $entityID,
    'return' => 'activity_date_time',
  ));

  $allCustomValues = _altedtriggers_get_all_custom_values_for_activity($entityID);
  $newCustomParams = array('id' => $entityID);

  // Iterate through the status review date fields & set the most recent status
  // update (field 227) to the most recent (we assume they run in order).
  // If status if 5 set field 226 (date status 5 achieved) to that field too.
  foreach ($reviewStatusFields as $id => $reviewStatusField) {
    if (!empty($allCustomValues[$reviewStatusField])) {
      $statusUpdateDate = $allCustomValues[$reviewDateFields[$id]];
      $statusUpdateValue = $allCustomValues[$reviewStatusField];

      // Update most recent data & status.
      $newCustomParams['custom_227'] = $statusUpdateDate;
      $newCustomParams['custom_225'] = $statusUpdateValue;

      if ($statusUpdateValue == 5 && empty($newCustomParams['custom_226'])) {
        $newCustomParams['custom_226'] = $statusUpdateDate;
        $newCustomParams['status_id'] = 'Completed';
      }
      $updatePeriod = _altedtriggers_calculate_update_period($statusUpdateDate, $activityStartDate);
      switch ($updatePeriod) {
        case 'mid-1' :
          $newCustomParams['custom_230'] = $statusUpdateValue;
          $newCustomParams['custom_233'] = _altedtriggers_get_difference($allCustomValues, 229, $statusUpdateValue, $newCustomParams);
          break;

        case 'end-1' :
          $newCustomParams['custom_234'] = $statusUpdateValue;
          $newCustomParams['custom_232'] = _altedtriggers_get_difference($allCustomValues, 230, $statusUpdateValue, $newCustomParams);
          break;

        case 'mid-2' :
          $newCustomParams['custom_242'] = $statusUpdateValue;
          $newCustomParams['custom_244'] = _altedtriggers_get_difference($allCustomValues, 234, $statusUpdateValue, $newCustomParams);
          break;

        case 'end-2' :
          $newCustomParams['custom_245'] = $statusUpdateValue;
          $newCustomParams['custom_243'] = _altedtriggers_get_difference($allCustomValues, 242, $statusUpdateValue, $newCustomParams);
          break;

        case 'mid-2-2' :
          // In this case we are AFTER the 2nd end of year so we use that as our base.
          $newCustomParams['custom_242'] = $statusUpdateValue;
          $newCustomParams['custom_244'] = _altedtriggers_get_difference($allCustomValues, 245, $statusUpdateValue, $newCustomParams);
          break;

        case 'end-2-2' :
          // In this case second mid term is still in the future we use the first mid-term as our base.
          $newCustomParams['custom_245'] = $statusUpdateValue;
          $newCustomParams['custom_243'] = _altedtriggers_get_difference($allCustomValues, 230, $statusUpdateValue, $newCustomParams);
          break;
      }

    }
  }

  civicrm_api3('activity', 'create', $newCustomParams);

}

/**
 * Check which the period the review falls in.
 *
 * If the activity starts in the middle of the year the end dates will still be
 * the same 2 years but the middle of the year dates will be after, not before
 * the end of year dates.
 *
 * @param string $statusUpdateDate
 * @param string $activityStartDate
 *
 * @return string
 * @throws \Exception
 */
function _altedtriggers_calculate_update_period($statusUpdateDate, $activityStartDate) {
  $activityStartYear = date('Y', strtotime($activityStartDate));
  $midYearDate = _altedtriggers_get_alted_mid_year_date($activityStartYear, $activityStartDate);
  $endOfYearDate = '31 December ' . $activityStartYear;
  $secondMidYearDate = _altedtriggers_get_alted_mid_year_date(date('Y', strtotime($midYearDate)) + 1);
  $secondEndOfYearDate = '31 December ' . ($activityStartYear + 1);

  if ($activityStartYear == date('Y', strtotime($midYearDate))) {
    $checkingOrder = array(
      'mid-1' => strtotime($midYearDate),
      'end-1' => strtotime($endOfYearDate),
      'mid-2' => strtotime($secondMidYearDate),
      'end-2' => strtotime($secondEndOfYearDate),
    );
  }
  else {
    $checkingOrder = array(
      'end-1' => strtotime($endOfYearDate),
      'mid-1' => strtotime($midYearDate),
      'end-2-2' => strtotime($secondEndOfYearDate),
      'mid-2-2' => strtotime($secondMidYearDate),
    );
  }
  foreach ($checkingOrder as $key => $value) {
    if (strtotime($statusUpdateDate) < $value) {
      return $key;
    }
  }
  throw new Exception('review is not within a valid time period');
}

/**
 * Calculate the difference (improvement) since last review.
 *
 * @param array $allCustomValues
 * @param int $customFieldID
 * @param int $newValue
 *
 * @param array $newCustomParams
 *   Calculated custom parameters (we give these precedence over DB ones).
 *
 * @return int
 */
function _altedtriggers_get_difference($allCustomValues, $customFieldID, $newValue, $newCustomParams) {
  $customFieldName = 'custom_' . $customFieldID;
  $originalValue = !empty($newCustomParams[$customFieldName]) ? $newCustomParams[$customFieldName] : CRM_Utils_Array::value($customFieldID, $allCustomValues, 1);
  $difference = $newValue - $originalValue;
  if ($difference > 0) {
    return $difference;
  }
  return 0;
}

/**
 * Get the mid year cut off date.
 *
 * This is unknown for future years at the moment and we have an 'educated guess'
 * but the array will need formatting as it becomes clear.
 *
 * @param int $year
 *
 * @param string|null $activityStartDate
 *   Activity start date. If this is after the middle of the year we push
 *   forwards a year with our mid date.
 *
 * @return string
 */
function _altedtriggers_get_alted_mid_year_date($year, $activityStartDate = NULL) {

  $midYearDates = array(
    2015 => '24 June 2015',
    2016 => '24 June 2016',
    2017 => '24 June 2017',
  );
  if (($midYearDate = $midYearDates[$year]) == FALSE) {
    $midYearDate = '24 June ' . $year;
  }
  if (strtotime($activityStartDate) > strtotime($midYearDate)) {
    return _altedtriggers_get_alted_mid_year_date($year + 1);
  }
  return $midYearDate;
}

/**
 * Get all custom values for an activity in 4 => value type array.
 *
 * @param int $activityID
 *
 * @return array
 * @throws \CiviCRM_API3_Exception
 */
function _altedtriggers_get_all_custom_values_for_activity($activityID) {
  return _altedtriggers_convert_custom_values_to_fields(civicrm_api3('custom_value', 'get', array(
    'entity_id' => $activityID,
    'entity_table' => 'Activity',
  )));
}
/**
 * Convert values to a key => value format.
 *
 * @param array $params
 *   Parameters as passed to _custom hook.
 *
 * @return array
 *   Custom fields in params in flattened array.
 */
function _altedtriggers_convert_params_to_fields($params) {
  $values = array();
  foreach ($params as $field) {
    $values[$field['custom_field_id']] = $field['value'];
  }
  return $values;
}

/**
 * Convert values to a key => value format.
 *
 * @param array $params
 *   Parameters as retrieved from custom value api.
 *
 * @return array
 *   Custom fields in params in flattened array.
 */
function _altedtriggers_convert_custom_values_to_fields($params) {
  $values = array();
  foreach ($params['values'] as $field) {
    if (isset($field['id']) && $field['latest'] != NULL) {
      $values[$field['id']] = $field['latest'];
    }
  }
  return $values;
}

/**
 *
 */
function altedtriggers_updates() {

  // Step 2 Set most recent review date based on the most recent of the review dates
  //
  // Step 3 if any of the progress statuses are equal to 5 then set the
  // date when status 5 achieved to that date
  //
  // Step 4 Set the most recent status to the status on the most recent date
  // Which is not equal to 0
  //
  // Step 5 set mid year status (for the year) to the most recent status that is not 6 & has a review date
  // prior to the defined mid year status (14 July 2014) -
  // 24 June 2015
  // 31 December 2015
  //
  // Step 6  Set mid year improvement to being equal to the mid year status less the initial status,
  // providing the value is not less than 0
  //
  // Step 7. Set the end of year status to be the most recent review status.
  //
  // Step 8. Set end of year improvement to being end of year - mid year where end of year <> 6
  //

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
  /*
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
  */
}
