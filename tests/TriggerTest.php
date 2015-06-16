<?php
/**
 * @file
 * Created by PhpStorm.
 * User: eileen
 * Date: 16/06/2015
 * Time: 11:08 AM
 */

class TriggerTest extends PHPUnit_Framework_TestCase {
  /**
   * Contact created in original set up.
   *
   * @var int
   */
  protected $individualID;

  /**
   * Activity created in original set up.
   *
   * @var int
   */
  protected $activityID;

  /**
   * Config to only run once.
   */
  static function setUpBeforeClass() {
    static $hasRun;
    if ($hasRun) {
      return;
    }
    $hasRun = TRUE;
    if (!defined('DRUPAL_ROOT')) {
      define('DRUPAL_ROOT', '../../../../..');
    }
    require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    // Bootstrap Drupal.
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
    civicrm_initialize(TRUE);
    require_once('../../../civicrm.settings.php');
    require_once('../../../../all/modules/civicrm/api/api.php');
    error_reporting(E_ALL);
  }

  function setup() {
    $this->ensureFieldsExist();
    $individual = civicrm_api3('contact', 'get', array(
      'display_name' => 'test contact',
    ));
    if (empty($individual['id'])) {
      $individual = civicrm_api3('contact', 'create', array(
        'contact_type' => 'Individual',
        'display_name' => 'test contact',
      ));
    }
    $this->individualID = $individual['id'];
    $activity = civicrm_api3('activity', 'create', array(
      'activity_type_id' => 52,
      'source_contact_id' => $this->individualID,
      'activity_date_time' => '1 Feb 2015',
    ));
    $this->activityID = $activity['id'];
  }

  function tearDown() {
    civicrm_api3('activity', 'delete', array('id' => $this->activityID));
  }

  function ensureFieldsExist() {
    $group = civicrm_api3('custom_group', 'get', array());
    if (!$group['count']) {
      throw new Exception('Missing custom data');
    }
    elseif ($group['values'][16]['name'] != 'Goal_and_Reviews') {
      throw new Exception('DB has other custom data');
    }
  }

  /**
   * Test that updating first progress check (custom 139) updates other fields.
   *
   *  - most recent review date (227) should be the same date - provided later fields
   *    do not contain data.
   *  - custom 225 should be updated to the same date - provided later fields do
   *    not contain data.
   *
   * @throws \CiviCRM_API3_Exception
   */
  function testActivityUpdate() {
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'custom_139' => 2,
      'custom_138' => '1 April 2015',
    ));

    $activity = civicrm_api3('activity', 'getsingle', array(
      'id' => $this->activityID,
      'return' => 'custom_227,custom_139',
      'return.custom' => TRUE,
    ));
    $this->assertEquals(strtotime('1 April 2015'), strtotime($activity['custom_227']));
  }

  /**
   * Test that updating first progress check (custom 139) updates other fields.
   *
   *  - if status = 5 AND custom 226 is empty alter custom 226 to be same date.
   *  - custom 225 should be updated to the same date - provided later fields do
   *    not contain data.
   *
   * @throws \CiviCRM_API3_Exception
   */
  function testActivityUpdateStatusFive() {
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'custom_139' => 2,
      'custom_138' => '1 April 2015',
    ));
    $activity = civicrm_api3('activity', 'getsingle', array(
      'id' => $this->activityID,
      'return' => 'custom_227,custom_139,custom_226',
    ));

    $this->assertEmpty($activity['custom_226']);
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'custom_143' => 5,
      'custom_142' => '1 May 2015',
    ));

    $activity = civicrm_api3('activity', 'getsingle', array(
      'id' => $this->activityID,
      'return' => 'custom_227,custom_139,custom_226',
    ));

    $this->assertEquals(strtotime('1 May 2015'), strtotime($activity['custom_227']));
    $this->assertEquals(strtotime('1 May 2015'), strtotime($activity['custom_226']));
  }

  /**
   * Test that updating first progress check (custom 139) updates other fields.
   *
   *  Check that once the status-5 date is set it doesn't get extended
   * if altered again.
   *
   * @throws \CiviCRM_API3_Exception
   */
  function testActivityUpdateStatusFiveOnlyOnce() {
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'custom_139' => 5,
      'custom_138' => '1 April 2015',
    ));

    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'custom_143' => 5,
      'custom_142' => '1 May 2015',
    ));

    $activity = civicrm_api3('activity', 'getsingle', array(
      'id' => $this->activityID,
      'return' => 'custom_227,custom_139,custom_226',
    ));

    $this->assertEquals(strtotime('1 May 2015'), strtotime($activity['custom_227']));
    $this->assertEquals(strtotime('1 April 2015'), strtotime($activity['custom_226']));
  }

  /**
   * Test that updating first progress check (custom 139) updates other fields.
   *
   *  Check that once the status-5 date is set it doesn't get extended
   * if altered again.
   *
   * @throws \CiviCRM_API3_Exception
   */
  function testActivityUpdateSetsNextHalfAndFullYearReview() {
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'custom_139' => 3,
      'custom_138' => '1 April 2015',
    ));

    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        'custom_230' => 3,
      )
    );

    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'custom_143' => 4,
      'custom_142' => '1 May 2015',
    ));

    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        'custom_230' => 4,
      )
    );

    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'custom_143' => 5,
      'custom_142' => '1 Sep 2015',
    ));

    // This is a bit of an odd one. By changing the date we actually move
    // the review out of the half-year & it gets set back based on the last
    // review.
    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        'custom_230' => 3,
        'custom_232' => 2,
        'custom_234' => 5,
      )
    );
  }

  /**
   * Test that updating first progress check (custom 139) updates other fields.
   *
   *  Check that once the status-5 date is set it doesn't get extended
   * if altered again.
   *
   * @throws \CiviCRM_API3_Exception
   */
  function testActivityUpdateSetsTwoYearHalfAndFullYearReview() {
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'custom_139' => 1,
      'custom_138' => '1 April 2015',
      'custom_143' => 2,
      'custom_142' => '1 Oct 2015',
      'custom_144' => '1 Feb 2016',
      'custom_145' => 3,
      'custom_146' => '1 Oct 2016',
      'custom_147' => 5,
    ));

    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        'custom_230' => 1,
        'custom_232' => 1,
        'custom_234' => 2,
        'custom_244' => 1,
        'custom_242' => 3,
        'custom_243' => 2,
        'custom_245' => 5,
      )
    );
    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        'custom_226' => '1 Oct 2016',
      ),
      'date'
    );
  }

  /**
   * Test that updating first progress check (custom 139) updates other fields.
   *
   *  Check that once the status-5 date is set it doesn't get extended
   * if altered again.
   *
   * @throws \CiviCRM_API3_Exception
   */
  function testActivityUpdateSetsTermTwoStartDate() {
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'activity_date_time' => '1 Sep 2015',
      'custom_138' => '1 Nov 2015',
      'custom_139' => 1,
      'custom_142' => '1 Feb 2016',
      'custom_143' => 2,
      'custom_144' => '1 Oct 2016',
      'custom_145' => 3,
      'custom_146' => '1 Feb 2017',
      'custom_147' => 5,
    ));

    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        // End of year status.
        'custom_234' => 1,
        // End of year improvement
        'custom_232' => 0,
        // Mid year status
        'custom_230' => 2,
        // Mid year improvement
        'custom_233' => 1,
        // End of year 2 status
        'custom_245' => 3,
        // End of year 2 improvement
        'custom_243' => 1,
        // Mid year 2 status
        'custom_242' => 5,
        // Mid Year 2 improvement
        'custom_244' => 2,
      )
    );
    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        'custom_226' => '1 Feb 2017',
      ),
      'date'
    );
  }

  /**
   * Retrieve activity and check custom values on it.
   *
   * @param $activityID
   * @param $values
   * @param null $format
   *
   * @throws \CiviCRM_API3_Exception
   */
  function assertActivityCustomValues($activityID, $values, $format = NULL) {
    $activity = civicrm_api3('activity', 'getsingle', array(
      'id' => $activityID,
      'return' => array_keys($values),
    ));

    foreach ($values as $key => $value) {
      if ($format == 'date') {
        $this->assertEquals(strtotime($activity[$key]), strtotime($value), 'Failed on ' . $key);
      }
      else {
        $this->assertEquals($activity[$key], $value, 'Failed on ' . $key);
      }
    }
  }
}
