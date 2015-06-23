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
      define('DRUPAL_ROOT', self::testPath(array(
        dirname(__FILE__) . '/../../../../..',
        dirname(__FILE__) . '/../../../../../..',
      ), '/includes/bootstrap.inc'));
    }

    require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    // Bootstrap Drupal.
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
    civicrm_initialize(TRUE);
    self::testPath(array(
      dirname(__FILE__) . '/../../..',
      dirname(__FILE__) . '/../../../..',
    ), '/civicrm.settings.php');

    self::testPath(array(
      dirname(__FILE__) . '/../../../../all/modules/civicrm/api',
      dirname(__FILE__) . '/../../../../../all/modules/civicrm/api',
    ), '/api.php');

    error_reporting(E_ALL);
  }

  /**
   * Select correct path from a bunch of candidates.
   *
   * @param array $candidates
   * @param string $file
   *
   * @return string|bool
   */
  public static function testPath($candidates, $file) {
    foreach ($candidates as $candidate) {
      if (file_exists($candidate . DIRECTORY_SEPARATOR . $file)) {
        require_once ($candidate . DIRECTORY_SEPARATOR . $file);
        return $candidate;
      }
    }
    return NULL;
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
      'activity_date_time' => '1 Jan 2015',
      'status_id' => 1,
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
      $this->getCustomField('review-status-1') => 2,
      'custom_138' => '1 April 2015',
    ));

    $activity = civicrm_api3('activity', 'getsingle', array(
      'id' => $this->activityID,
      'return' => 'custom_227,custom_139,status_id',
      'return.custom' => TRUE,
    ));
    $this->assertEquals(strtotime('1 April 2015'), strtotime($activity['custom_227']));
    $this->assertEquals(1, $activity['status_id']);
  }

  /**
   * Test that updating status to ceased sets correct progress.
   *
   * @throws \CiviCRM_API3_Exception
   */
  function testActivityUpdateStatusCeased() {
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      $this->getCustomField('review-status-1') => 2,
      'custom_138' => '1 April 2015',
      'custom_143' => 6,
      'custom_142' => '1 Oct 2015',
    ));

    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        // Mid year 1 status.
        'custom_230' => 2,
        // End of year 1 status.
        'custom_234' => 6,
        // End of year 1 improvement.
        $this->getCustomField('end-year-1-improvement') => 0,
      )
    );
  }

  /**
   * Test that updating status to ceased sets correct progress.
   *
   * With this data there has been some progress in the half they were ceased
   *
   * @throws \CiviCRM_API3_Exception
   */
  function testActivityUpdateStatusCeasedSomeProgress() {
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      $this->getCustomField('review-status-1') => 2,
      'custom_138' => '1 April 2015',
      'custom_143' => 6,
      'custom_142' => '1 May 2015',
    ));

    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        // Mid year 1 status.
        'custom_230' => 6,
        $this->getCustomField('mid-year-1-improvement') => 1,
      )
    );
  }

  /**
   * Test that updating status to ceased sets correct progress.
   *
   * With this data there has been some progress in the half they were ceased
   *
   * @throws \CiviCRM_API3_Exception
   */
  function testActivityUpdateStatusCeasedAlternativeValues() {
    civicrm_api3('activity', 'create', array(
      'activity_date_time' => '17 Oct 2014',
      'id' => $this->activityID,
      $this->getCustomField('review-status-1') => 2,
      'custom_138' => '2 Dec 2014',
      $this->getCustomField('review-status-2') => 6,
      'custom_142' => '29 Jan 2015',
    ));

    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        // Mid year 1 status.
        $this->getCustomField('mid-year-1-status') => 6,
        $this->getCustomField('end-year-1-improvement') => 1,
        $this->getCustomField('mid-year-1-improvement') => 0,
      )
    );
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
      $this->getCustomField('review-status-1') => 2,
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
      'return' => 'custom_227,custom_139,custom_226,status_id',
    ));

    $this->assertEquals(strtotime('1 May 2015'), strtotime($activity['custom_227']));
    $this->assertEquals(strtotime('1 May 2015'), strtotime($activity['custom_226']));
    $this->assertEquals(2, $activity['status_id']);
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
      $this->getCustomField('review-status-1') => 5,
      'custom_138' => '1 April 2015',
    ));

    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'custom_143' => 5,
      'custom_142' => '1 May 2015',
    ));

    $activity = civicrm_api3('activity', 'getsingle', array(
      'id' => $this->activityID,
      'return' => 'custom_227,custom_139,custom_226,status_id',
    ));

    $this->assertEquals(strtotime('1 May 2015'), strtotime($activity['custom_227']));
    $this->assertEquals(strtotime('1 April 2015'), strtotime($activity['custom_226']));
    $this->assertEquals(2, $activity['status_id']);
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
      $this->getCustomField('review-status-1') => 3,
      'custom_138' => '1 April 2015',
    ));

    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        'custom_230' => 3,
        'custom_225' => 3,
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
        'custom_225' => 4,
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
        $this->getCustomField('end-year-1-improvement') => 2,
        'custom_234' => 5,
        'status_id' => 2,
        'custom_225' => 5,
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
      $this->getCustomField('review-status-1') => 1,
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
        // Mid year 1 status.
        'custom_230' => 1,
        // End of year 1 status.
        'custom_234' => 2,
        // Mid year status - year 2.
        'custom_242' => 3,
        // End of  year status - year 2.
        'custom_245' => 5,

        // Year 2 mid-year improvement
        'custom_233' => 0,
        // End of year 1 improvement.
        $this->getCustomField('end-year-1-improvement') => 1,
        // Year 2 mid-year improvement
        'custom_244' => 1,
        // Year 2 end of year improvement
        'custom_243' => 2,

        'custom_225' => 5,
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
   * Test if a half year passes with no review the end of half year status is set.
   *
   * @throws \CiviCRM_API3_Exception
   */
  function testActivityUpdateSkipHalfYearStartDate() {
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'activity_date_time' => '1 Sep 2015',
      'custom_138' => '1 Feb 2016',
      $this->getCustomField('review-status-1') => 3,
    ));

    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        // End of year 1 status.
        'custom_234' => 1,
        // End of year improvement
        $this->getCustomField('end-year-1-improvement') => 0,
        // Mid year status
        'custom_230' => 3,
        // Mid year improvement
        $this->getCustomField('mid-year-1-improvement') => 2,
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
  function testActivityUpdateSetsTermTwoStartDate() {
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'activity_date_time' => '1 Sep 2015',
      'custom_138' => '1 Nov 2015',
      $this->getCustomField('review-status-1') => 1,
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
        // Mid year status
        'custom_230' => 2,
        // End of year 2 status
        'custom_245' => 3,
        // Mid year 2 status
        'custom_242' => 5,

        // End of year improvement
        $this->getCustomField('end-year-1-improvement') => 0,
        // Mid year improvement
        'custom_233' => 1,
        // End of year 2 improvement
        'custom_243' => 1,
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
   * Checks a date variant Gemma identified as not working.
   *
   * We also do this as 2 separate ones to test.
   *
   * @throws \CiviCRM_API3_Exception
   */
  function testActivityUpdateTermTwoStartAlternateDetails() {
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'activity_date_time' => '22 Oct 2014',
      'custom_138' => '20 Nov 2014 ',
      $this->getCustomField('review-status-1') => 2,
    ));
    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        // End of year status.
        'custom_234' => 2,
        // Mid year status
        'custom_230' => NULL,
    ));
    civicrm_api3('activity', 'create', array(
      'id' => $this->activityID,
      'custom_142' => '8th Apr 2015',
      'custom_143' => 3,
    ));


    $this->assertActivityCustomValues(
      $this->activityID,
      array(
        // End of year status.
        'custom_234' => 2,
        // Mid year status
        'custom_230' => 3,

        // End of year improvement
        $this->getCustomField('end-year-1-improvement') => 1,
        // Mid year improvement
        'custom_233' => 1,
      )
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
        $this->assertEquals($activity[$key], $value, 'Failed on ' . $key. ' ' . $this->getCustomFieldFunction($key));
      }
    }
  }

  /**
   * Get the relevant custom field for the function.
   *
   * @param string $function
   * @param string $prefix
   *   String to prepend, generally 'custom_' or ''.
   *
   * @return string
   */
  function getCustomField($function, $prefix = 'custom_') {
    $fields = $this->getFieldMap();
    return $prefix . $fields[$function];
  }

  /**
   * Get the relevant custom field for the function.
   *
   * @param int $id
   * @param string $prefix
   *   String to prepend, generally 'custom_' or ''.
   *
   * @return string
   */
  function getCustomFieldFunction($id, $prefix = 'custom_') {
    $fields = array_flip($this->getFieldMap());
    return $fields[str_replace($prefix, '', $id)];
  }

  /**
   * @return array
   */
  protected function getFieldMap() {
    $fields = array(
      'review-status-1' => 139,
      'review-status-2' => 143,
      'mid-year-1-status' => 230,
      'end-year-1-status' => 234,
      'mid-year-1-improvement' => 233,
      'end-year-1-improvement' => 232,
    );
    return $fields;
  }

}
