<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Displays reports for the quizzes under the selected course.
 *
 * @package    report_quiz
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/lib/tablelib.php');
require_once($CFG->dirroot.'/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot.'/report/quiz/locallib.php');

$id          = optional_param('id', 0, PARAM_INT);// Course ID.
$user        = optional_param('user', 0, PARAM_INT); // User to display.
$roleid      = optional_param('roleid', '0', PARAM_INT);     // Role of the logged in user.
$quizid      = optional_param('quizid', '0', PARAM_INT);     // Which quiz results to show.

// 0 - avg, 1 - best, 2 - worst
$gradetype   = optional_param('gradetype', '0', PARAM_INT);     // Which grade type (worst/avg/best) results of the chosen quiz to show.
$choosequiz  = optional_param('choosequiz', false, PARAM_BOOL);

$params = array();
if ($id !== 0) {
    $params['id'] = $id;
}
if ($user !== 0) {
    $params['user'] = $user;
}
if ($quizid !== 0) {
    $params['quizid'] = $quizid;
}

$params['gradetype'] = $gradetype;

$url = new moodle_url("/report/quiz/index.php", $params);
$PAGE->set_url('/report/quiz/index.php', array('id' => $id));
$PAGE->set_pagelayout('admin');

// Get course details.
/*$course = null;
if ($id) {
    $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
    require_login($course);
    $context = context_course::instance($course->id);
} else {
    require_login();
    $context = context_system::instance();
    $PAGE->set_context($context);
}*/

$userid = $USER->id;  // Owner of the page
$context = context_system::instance();
$PAGE->set_context($context);
$course = null;
$canviewquizreports = $canviewownquizreport = false;
//print '<pre>access=';print_r($USER->access);print '</pre>';

if ($quizid != 0) {

	if (!$quiz = $DB->get_record('quiz', array('id' => $quizid))) {
		print_error('invalidquizid', 'quiz');
	}
	//print '<pre>quiz=';print_r($quiz);print '</pre>';
   //prepare for the table data
   if (!$course = $DB->get_record('course', array('id' => $quiz->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("quiz", $quiz->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    $context = context_course::instance($course->id);

	$canviewownquizreport = has_capability('report/quiz:viewownreport', $context);
	$canviewquizreports = has_capability('mod/quiz:viewreports', $context);

	if (!$canviewquizreports || !$canviewownquizreport) {// minimum capability required is a student mode
	   require_capability('report/quiz:viewownreport', $context);
	}
}
// Before we close session, make sure we have editing information in session.
$adminediting = optional_param('adminedit', -1, PARAM_BOOL);
if ($PAGE->user_allowed_editing() && $adminediting != -1) {
    $USER->editing = $adminediting;
}

$strquiz = get_string('quiz', 'report_quiz');
$strquizreport = get_string('pluginname', 'report_quiz');

$stradministration = get_string('administration');
$strreports = get_string('reports');

$PAGE->set_title($SITE->shortname .': '. $strquizreport);
$PAGE->set_heading($SITE->shortname .': '. $strquizreport );

echo $OUTPUT->header();

// Print first controls.
report_quiz_print_filter_form($course, $roleid, $quizid, $canviewquizreports, $gradetype); //prints the select tag

if ($quizid != 0) {

   // When user choose to view quiz report then only trigger event.
   if ($choosequiz) {
   	// Trigger a report viewed event.
   	$event = \report_quiz\event\report_viewed::create(array('context' => $context,
   		 'other' => array('roleid' => $roleid, 'userid' => $user, 'quizid' => $quizid)));

   	$event->trigger();
   }

   $mode = '';
	if (!$canviewquizreports) { //teacher mode
		$mode = 'own_';
	}

   $reportclassname = 'quiz_' . $mode . 'attempts_overview_report';

   if (!class_exists($reportclassname)) {
		print_error('preprocesserror', 'quiz');
   }

   $report = new $reportclassname();
   $report->gradetype = $params['gradetype'];
   $report->display($quiz, $cm, $course);

}

echo $OUTPUT->footer();
