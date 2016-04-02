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
 * This file renders the quiz overview graph.
 *
 * @package   quiz_overview
 * @copyright 2008 Jamie Pratt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/*
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/graphlib.php');

$graph = new graph(900,600);
$graph->parameter['path_to_fonts'] = 'fonts/';
$graph->parameter['title']         = 'Line and Area Chart';
$graph->parameter['x_label']       = 'Day of the Week';
$graph->parameter['y_label_left']  = 'Totals';
$graph->parameter['legend']        = 'top-left';
$graph->parameter['legend_border'] = 'black';
$graph->parameter['legend_offset'] = 4;
$graph->parameter['x_offset']      = 0; // offset of x axis ticks from y_axis. can be set to zero as there are no bars.

//$graph->x_data                 = array('Fri', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri');
$graph->x_data                 = array('Fri', 'Mon', 'Tue', 'Wed', 'Thu');

$graph->y_data['alpha']        = array(2, 3,  4,  0,  1);
//$graph->y_data['alpha']        = array(6, 7,  5,  5,  8,  9);
//$graph->y_data['beta']         = array(2, 2,  4,  3,  4,  5);
//$graph->y_data['total']        = array(8, 9 , 9 , 8 , 12, 14);
//$graph->y_data['cummulative']  = array(8, 17, 26, 34, 46, 60);

// can add new colours like this.
$graph->colour['new_colour1'] = ImageColorAllocate ($graph->image, 0xFF, 0xFF, 0x66);
$graph->colour['new_colour2'] = ImageColorAllocate ($graph->image, 0xFF, 0xFF, 0xCC);

// format for each data set
$graph->y_format['alpha']        =  array('colour' => 'blue', 'line' => 'line', 'legend' => 'Alpha');
//$graph->y_format['beta']         =  array('colour' => 'red',  'line' => 'line', 'legend' => 'Beta');
//$graph->y_format['total']        =  array('colour' => 'new_colour1', 'area' => 'fill', 'legend' => 'Unit Total');
//$graph->y_format['cummulative']  =  array('colour' => 'new_colour2', 'area' => 'fill', 'legend' => 'Cummulative');

$graph->parameter['shadow'] = 'none'; // set default shadow for all data sets.
$graph->parameter['brush_size'] = 2; // set default shadow for all data sets.

//$graph->y_order = array('cummulative', 'total', 'alpha', 'beta'); // order in which to draw data sets.
$graph->y_order = array('alpha'); // order in which to draw data sets.

$graph->parameter['x_axis_angle']      = 60; // rotate x_axis text to 60 degrees.
$graph->parameter['y_min_left']        = 0;
$graph->parameter['y_resolution_left'] = 0;
$graph->parameter['y_decimal_left']    = 0;
$graph->parameter['y_grid']            = 'line';
$graph->parameter['x_grid']            = 'none';  // no x grid
$graph->parameter['y_ticks_colour']    = 'none'; // no y axis ticks
$graph->parameter['inner_border']      = 'none';

// draw it.
$graph->draw();
exit;
*/
//test graph above here

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/graphlib.php');
require_once($CFG->dirroot . '/report/quiz/locallib.php');
$DB->set_debug(false);
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');

class quiz_report_graph_attempts_overview_report extends quiz_attempts_report {

	public $questions;
    public function display($quiz, $cm, $course) {
        global $CFG, $DB, $OUTPUT, $PAGE, $USER;

        list($currentgroup, $students, $groupstudents, $allowed) =
                $this->init('overview', 'quiz_overview_settings_form', $quiz, $cm, $course);

		$allowed = $students = array($USER->id);
		//print 'students = ';print_r($students);
		//print 'allowed = ';print_r($allowed);
        $options = new quiz_report_overview_options('overview', $quiz, $cm, $course);

        if ($options->attempts == self::ALL_WITH) {
            // This option is only available to users who can access all groups in
            // groups mode, so setting allowed to empty (which means all quiz attempts
            // are accessible, is not a security porblem.
            $allowed = array();
        }

        // Load the required questions.
        $this->questions = $questions = quiz_report_get_significant_questions($quiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                array('context' => context_course::instance($course->id)));
        $table = new quiz_report_overview_table($quiz, $this->context, $this->qmsubselect,
                $options, $groupstudents, $students, $questions, $options->get_url());

        $this->course = $course; // Hack to make this available in process_actions.

        $hasquestions = quiz_has_questions($quiz->id);

        $hasstudents = $students && (!$currentgroup || $groupstudents);
        if ($hasquestions && ($hasstudents || $options->attempts == self::ALL_WITH)) {
            // Construct the SQL.
			//print '<pre>allowed='; print_r($allowed); print '</pre>';
            list($fields, $from, $where, $params) = $table->base_sql($allowed);

            $table->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);

			$table->from = $from;
			$table->where = $where;
			$table->params = $params;

			$this->lateststeps = $table->load_question_latest_steps();
			//print '<pre>lateststeps';print_r($this->lateststeps);print 'after now </pre><br>';

			return $this->lateststeps;
		}
		return false;
	}
}

$quizid = required_param('id', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

$quiz = $DB->get_record('quiz', array('id' => $quizid));
$course = $DB->get_record('course', array('id' => $quiz->course));
$cm = get_coursemodule_from_instance('quiz', $quizid);

require_login($course, false, $cm);
$modcontext = context_module::instance($cm->id);
require_capability('report/quiz:viewownreport', $modcontext);

$line = new graph(800, 600);
$line->parameter['title'] = get_string('graphtitle', 'report_quiz');
$line->parameter['y_label_left'] = get_string('correctanswers', 'report_quiz');
$line->parameter['x_label'] = get_string('attemptnumber', 'report_quiz');
$line->parameter['y_label_angle'] = 90;
$line->parameter['x_label_angle'] = 0;
$line->parameter['x_axis_angle'] = 0;
$line->parameter['x_offset'] = 0;
$line->parameter['shadow'] = 'none'; // set default shadow for all data sets.
$line->parameter['brush_size'] = 2; // set default shadow for all data sets.

$line->parameter['legend']        = 'top-left';
$line->parameter['legend_border'] = 'black';
$line->parameter['legend_offset'] = 15;

//$line->parameter['x_offset']      = 0; // offset of x axis ticks from y_axis. can be set to zero as there are no bars.


// The following two lines seem to silence notice warnings from graphlib.php.
$line->y_tick_labels = null;
$line->offset_relation = null;

// We will make size > 1 to get an overlap effect when showing groups.
//$line->parameter['bar_size'] = 1;
// Don't forget to increase spacing so that graph doesn't become one big block of colour.
//$line->parameter['bar_spacing'] = 10;

$report = new quiz_report_graph_attempts_overview_report();
$attemptsdata = $report->display($quiz, $cm, $course);

$questionusageids = $xdata = array();
$quid_cnt = 1;
foreach ($attemptsdata as $questionusageid => $attemptdata) {
	$xdata[] = $quid_cnt++;
	$questionusageids[] = $questionusageid;
}
$line->x_data = $xdata;

$line->y_format['ownattempt'] = array(
    'colour' => 'red',
    'line' => 'line',
    'legend' => get_string('yourresult', 'report_quiz')
);

$questions = $report->questions;
//print '<pre>ques=';print_r($questions); print'</pre>';
$students = array($USER->id);
$line->y_data['ownattempt'] = report_quiz_correct_answers_count($quizid, $students, $questionusageids);
//print '<pre>ydata=';print_r($line->y_data['ownattempt']); print'</pre>';

$line->parameter['y_axis_gridlines'] = max($line->y_data['ownattempt']) + 1;
$line->y_order = array('ownattempt');
//$line->parameter['inner_border']      = 'none';
$line->parameter['y_min_left']        = 0;
$line->parameter['y_resolution_left'] = 0;
$line->parameter['y_decimal_left']    = 0;
$line->parameter['y_grid']            = 'line';
$line->parameter['x_grid']            = 'none';  // no x grid
$line->parameter['y_ticks_colour']    = 'none'; // no y axis ticks


//$ymax = max($line->y_data['ownattempt']);
//$line->parameter['y_min_left'] = 0;
//$line->parameter['y_max_left'] = $ymax;
//$line->parameter['y_decimal_left'] = 0;

/* //dont understand what is grid lines, i dont think its necessary for line graph so commenting it for now
// Pick a sensible number of gridlines depending on max value on graph.
$gridlines = $ymax;
while ($gridlines >= 10) {
    if ($gridlines >= 50) {
        $gridlines /= 5;
    } else {
        $gridlines /= 2;
    }
}

$line->parameter['y_axis_gridlines'] = $gridlines + 1;
*/
$line->draw();


function report_quiz_correct_answers_count($quizid, $students, $questionusageids) {
	global $DB;

	//SELECT qa.id, questionusageid, count(questionid) FROM mdl_question_attempts qa JOIN mdl_question_attempt_steps qas ON qas.questionattemptid = qa.id AND qas.sequencenumber = ( SELECT MAX(sequencenumber) FROM mdl_question_attempt_steps WHERE questionattemptid = qa.id ) WHERE fraction > 0.000 AND qa.questionusageid IN (1, 3, 7, 8, 9) AND qa.slot IN (1,2,3,4,5) and qas.userid in (3) group by questionusageid;

	//print '<pre>stud=';print_r($students); print'</pre>';
	//print '<pre>questionusageids=';print_r($questionusageids); print'</pre>';
	
	list($questionusageid_in, $ques_params) = $DB->get_in_or_equal($questionusageids, SQL_PARAMS_NAMED, 'questionusageid');
	list($students_in, $params  ) = $DB->get_in_or_equal($students, SQL_PARAMS_NAMED, 'student');
	//print '<pre>params=';print_r($params); print'</pre>';

	$qsbyslot = $DB->get_records_sql("
            SELECT questionusageid, count(questionid) correct_answer_count

              FROM mdl_question_attempts qa JOIN mdl_question_attempt_steps qas ON qas.questionattemptid = qa.id AND qas.sequencenumber = ( SELECT MAX(sequencenumber) FROM mdl_question_attempt_steps WHERE questionattemptid = qa.id )

             WHERE 
				fraction > 0.000 
				AND qa.questionusageid $questionusageid_in
				AND qas.userid $students_in

          GROUP BY questionusageid", 
          $params + $ques_params);

	//print '<pre>$qsbyslot=';print_r($qsbyslot); print'</pre>';
    $correct_answer_count = array();

	foreach($questionusageids as $index => $quid) {
		if (isset($qsbyslot[$quid])) {
			$correct_answer_count[] = $qsbyslot[$quid]->correct_answer_count;
		} else {
			$correct_answer_count[] = 0;
		}
	}
    
	//print '<pre>correct_answer_count=';print_r($correct_answer_count); print'</pre>';

    return $correct_answer_count;

}
