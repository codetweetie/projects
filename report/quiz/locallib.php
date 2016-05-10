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
 * This file contains functions used by the log reports
 *
 * This files lists the functions that are used during the log report generation.
 *
 * @package    report_quiz
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/default.php');
require_once($CFG->dirroot . '/mod/quiz/report/overview/overview_table.php');
require_once($CFG->dirroot . '/mod/quiz/report/overview/overview_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/overview/overview_form.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/overview/report.php');

class quiz_report_overview_options extends quiz_overview_options {
    /**
     * Get the URL to show the report with these options.
     * @return moodle_url the URL.
     */
    public function get_url() {
        return new moodle_url('/report/quiz/index.php', $this->get_url_params());
    }

}

/**
 * Print filter form.
 *
 * @param stdClass $course course object.
 * @param int $roleid Role to be filtered.
 * @param int $quizid Quiz id of course.
 */
function report_quiz_print_filter_form($course, $roleid, $quizid, $canviewquizreports, $gradetype) {
    global $DB;

    // TODO: we need a new list of roles that are visible here.
    //$modinfo = get_fast_modinfo($course);

    //print '<pre>';print_r($modinfo);print '</pre>';

    $quizzes = array();
    /*foreach ($modinfo->instances['quiz'] as $cm) {
        // Skip modules such as label which do not actually have links;
        // this means there's nothing to participate in.
    	  if (!$cm->has_view()) {
	      continue;
        }
	  $quizzes[$cm->instance] = format_string($cm->name);
    }*/
    $sql = "select * from {quiz}";
    $result = $DB->get_records_sql($sql);
    foreach($result as $res){
	$quizzes[$res->id] = format_string($res->name);
	}

    echo '<form class="quizselectform" action="index.php" method="get"><div>'."\n".
        "\n";
    echo '<label style="display:inline-block;" for="menuquizid">'.get_string('selectquiz', 'report_quiz').'</label>'."\n";
    echo html_writer::select($quizzes, 'quizid', $quizid);

    if (!$canviewquizreports) {
    	 echo '<input type="submit" value="'.get_string('go').'" />';
    } else {
       $selected_button_class['avg'] = (($gradetype == 0) ? 'class=btnpressed' : '');
       $selected_button_class['best'] = (($gradetype == 1) ? 'class=btnpressed' : '');
       $selected_button_class['worst'] = (($gradetype == 2) ? 'class=btnpressed' : '');

       echo '<input type="hidden" id="gradetype" name="gradetype" value="'.$gradetype.'" />'."\n";
    	 echo '<br><input type="button" '.$selected_button_class['avg'].' onclick="javascript:gradetypeselect(0);" name="avggradetypebtn" value="'.get_string('avggradetype', 'report_quiz').'" />';
    	 echo '<input type="button" '.$selected_button_class['best'].' onclick="javascript:gradetypeselect(1);"name="bestgradetypebtn" value="'.get_string('bestgradetype', 'report_quiz').'" />';
    	 echo '<input type="button" '.$selected_button_class['worst'].' onclick="javascript:gradetypeselect(2);" name="worstgradetypebtn" value="'.get_string('worstgradetype', 'report_quiz').'" />';
	 echo <<<JS
<style>
input.btnpressed, input.btnpressed:active {background: blue;color: white; }
input[type="button"]:hover {background: grey; color: white;}
</style>
<script>
function gradetypeselect(gradetype) {
	   if (this.document.getElementById("menuquizid").selectedIndex == 0) {
	   	alert('Please select a quiz');
		return false;
	   }
	   this.document.getElementById("gradetype").value = gradetype;
	   this.document.forms[0].submit();
}
</script>
JS;
    }
    echo "\n</div></form>\n";
}

/**
 * Quiz report subclass for the teacher overview of students attempts (grades) report.
 *
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_attempts_overview_report extends quiz_attempts_report {

	public $gradetype;

	public $grade;

    /**
     * Get the base URL for this report.
     * @return moodle_url the URL.
     */
    public function get_base_url() {
        return new moodle_url('/report/quiz/index.php',
                array('id' => $this->context->instanceid, 'mode' => $this->mode));
    }

    /**
     * Add all the user-related columns to the $columns and $headers arrays.
     * @param table_sql $table the table being constructed.
     * @param array $columns the list of columns. Added to.
     * @param array $headers the columns headings. Added to.
     */
    public function add_user_columns($table, &$columns, &$headers) {
        global $CFG;

            $columns[] = 'fullname';
            $headers[] = get_string('name');
    }

    public function display($quiz, $cm, $course) {
        global $CFG, $DB, $OUTPUT, $PAGE;

        list($currentgroup, $students, $groupstudents, $allowed) =
                $this->init('overview', 'quiz_overview_settings_form', $quiz, $cm, $course);
		//print '<pre> allowed=';print_r($allowed); print '</pre>';               
        $options = new quiz_report_overview_options('overview', $quiz, $cm, $course);

        $this->form->set_data($options->get_initial_form_data());

        if ($options->attempts == self::ALL_WITH) {
            // This option is only available to users who can access all groups in
            // groups mode, so setting allowed to empty (which means all quiz attempts
            // are accessible, is not a security porblem.
            $allowed = array();
        }

        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                array('context' => context_course::instance($course->id)));
        $table = new quiz_report_overview_table($quiz, $this->context, $this->qmsubselect,
                $options, $groupstudents, $students, $questions, $options->get_url());

        $this->course = $course; // Hack to make this available in process_actions.
        $this->process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $options->get_url());

        $hasquestions = quiz_has_questions($quiz->id);
        if (!$table->is_downloading()) {
            if (!$hasquestions) {
                echo quiz_no_questions_message($quiz, $cm, $this->context);
            } else if (!$students) {
                echo $OUTPUT->notification(get_string('nostudentsyet'));
            } else if ($currentgroup && !$groupstudents) {
                echo $OUTPUT->notification(get_string('nostudentsingroup'));
            }
        }

        $hasstudents = $students && (!$currentgroup || $groupstudents);
        if ($hasquestions && ($hasstudents || $options->attempts == self::ALL_WITH)) {
            // Construct the SQL.
            $fields = $DB->sql_concat('u.id', "'#'", 'COALESCE(quiza.attempt, 0)') .
                    ' AS uniqueid, ';

            list($fields, $from, $where, $params) = $table->base_sql($allowed);

	$this->grade = array( 0 => get_string('avggradetype', 'report_quiz'),
		 get_string('bestgradetype', 'report_quiz'),
		 get_string('worstgradetype', 'report_quiz')
		 );

		echo "<h3>".$this->grade[$this->gradetype]."</h3>";

		//print '<pre>quiz=';print_r($quiz);print '</pre>';
		$worst_grade = ($quiz->sumgrades * 40/100);
		$avg_grade = ($quiz->sumgrades * 75/100);
		$best_grade = $quiz->sumgrades;

		$worst_grade_where = '(quiza.sumgrades <'.$worst_grade.')';
		$avg_grade_where = '(quiza.sumgrades BETWEEN '.$worst_grade.' AND '.$avg_grade.')';
		$best_grade_where = '(quiza.sumgrades BETWEEN '.$avg_grade.' AND '.$best_grade.')';

		switch ($this->gradetype) {
	  	    case 1:
		        $grade_where = $best_grade_where;
			  break;
		    case 2:
		        $grade_where = $worst_grade_where;
			  break;
		    default:
		        $grade_where = $avg_grade_where;
			  break;
		}
		$where .= ' AND '.$grade_where;
            $table->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);

            // Test to see if there are any regraded attempts to be listed.
            $fields .= ", COALESCE((
                                SELECT MAX(qqr.regraded)
                                  FROM {quiz_overview_regrades} qqr
                                 WHERE qqr.questionusageid = quiza.uniqueid
                          ), -1) AS regraded";
            if ($options->onlyregraded) {
                $where .= " AND COALESCE((
                                    SELECT MAX(qqr.regraded)
                                      FROM {quiz_overview_regrades} qqr
                                     WHERE qqr.questionusageid = quiza.uniqueid
                                ), -1) <> -1";
            }

		//print '<pre>"'.$this->gradetype.'" gradewhr='.$grade_where.' params=';print_r($params);print '</pre>';

            $table->set_sql($fields, $from, $where, $params);

            // Define table columns.
            $columns = array();
            $headers = array();

            $this->add_user_columns($table, $columns, $headers);
            $this->add_grade_columns($quiz, $options->usercanseegrades, $columns, $headers, false);

            if ($options->slotmarks) {
                foreach ($questions as $slot => $question) {
                    // Ignore questions of zero length.
                    $columns[] = 'qsgrade' . $slot;
                    $headers[] = get_string('qbrief', 'quiz', $question->number);
                }
            }

		//print '<pre>cols=';print_r($columns);print '</pre>';		print '<pre>headers=';print_r($headers);print '</pre>';

            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $options, false);
            $table->set_attribute('class', 'generaltable generalbox grades');
            $table->out($options->pagesize, true);
        }
        return true;
    }
}

class quiz_report_overview_table extends quiz_overview_table {

	public function add_average_row($label, $users) {
		return ''; //no need overall average row
	}

	public function col_fullname($row) {

		 $html = flexible_table::col_fullname($row);
		 return $html;
    }
public function get_row_class($attempt) {
            return '';
    }
public function get_sort_columns() {

	 if (has_capability('mod/quiz:viewreports', $this->context)) {
	     $sortcolumns = parent::get_sort_columns();
	 } else {
        // Add attemptid as a final tie-break to the sort. This ensures that
        // Attempts by the same student appear in order when just sorting by name.
        //$sortcolumns = parent::get_sort_columns();
           $sortcolumns['slot.slot'] = SORT_ASC;
	  }
        return $sortcolumns;
    }

    public function get_qubaids_condition() {
	     if (has_capability('mod/quiz:viewreports', $this->context)) {

		  return parent::get_qubaids_condition();
	     }
    	     global $DB;

    	     $rawdata = $DB->get_records_sql("
		SELECT quiza.uniqueid questionusageid
		  FROM ".$this->from." WHERE ".$this->where." ", $this->params);

        $qubaids = array();
        foreach ($rawdata as $attempt) {
            if ($attempt->questionusageid > 0) {
                $qubaids[] = $attempt->questionusageid;
            }
        }

        return new qubaid_list($qubaids);

    }

    public function load_question_latest_steps(qubaid_condition $qubaids = null) {
        if ($qubaids === null) {
            $qubaids = $this->get_qubaids_condition();
        }
        $dm = new question_engine_data_mapper();
        $latesstepdata = $dm->load_questions_usages_latest_steps(
                $qubaids, array_keys($this->questions));

        $lateststeps = array();
        foreach ($latesstepdata as $step) {
            $lateststeps[$step->questionusageid][$step->slot] = $step;
        }

	  //print '<pre>$lateststeps=';print_r($lateststeps);print '</pre>';
        return $lateststeps;
    }

    public function col_questionnumbertxt($attempt) {
        if (!is_null($attempt->id)) {
           return get_string('qbrief', 'quiz', $attempt->slot);
        } else {
            return  '-';
        }
    }

    /**
     * @param string $colname the name of the column.
     * @param object $attempt the row of data - see the SQL in display() in
     * mod/quiz/report/overview/report.php to see what fields are present,
     * and what they are called.
     * @return string the contents of the cell.
     */
    public function other_cols($colname, $attempt) {
	 if (has_capability('mod/quiz:viewreports', $this->context)) {
	    return parent::other_cols($colname, $attempt);
	 }

        if (!preg_match('/^qsgrade(\d+)$/', $colname, $matches)) {
            return null;
        }
        $usageid = $matches[1];

        $slot = $attempt->slot;
	  //echo 'usageid='.$usageid.' slot='.$slot.'<br>';

	  foreach ($this->lateststeps as $tmpusageid => $questionattempt) {
	  	    //print'<pre>';var_dump($questionattempt);print '</pre>';
	  	if (!isset($questionattempt[$slot])) {
               return '-';
        	}
		else {
		     $questionattempt[$slot]->attempt = $questionattempt[$slot]->questionattemptid;
		     $questionattempt[$slot]->usageid = $usageid;
		     break;
		}
	  }

	  $stepdata = $this->lateststeps[$usageid][$slot];
	  $state = question_state::get($stepdata->state);

	  if ($attempt->maxmark == 0) {
		$grade = '-';
	  } else if (is_null($stepdata->fraction)) {
		if ($state == question_state::$needsgrading) {
		    $grade = get_string('requiresgrading', 'question');
		} else {
		    $grade = '-';
		}
	  } else {
		$grade = quiz_rescale_grade(
			  $stepdata->fraction * $attempt->maxmark, $this->quiz, 'question');
	  }

	  if ($this->is_downloading()) {
		return $grade;
	  }

	  if (isset($this->regradedqs[$usageid][$slot])) {
		$gradefromdb = $grade;
		$newgrade = quiz_rescale_grade(
			  $this->regradedqs[$usageid][$slot]->newfraction * $attempt->maxmark,
			  $this->quiz, 'question');
		$oldgrade = quiz_rescale_grade(
			  $this->regradedqs[$usageid][$slot]->oldfraction * $attempt->maxmark,
			  $this->quiz, 'question');

		$grade = html_writer::tag('del', $oldgrade) . '/' .
			  html_writer::empty_tag('br') . $newgrade;
	  }

	  //print '<pre> attempt=';print_r($questionattempt); print '</pre>';
	  return $this->make_review_link($grade, $questionattempt[$slot], $slot);
    }
}

/**
 * Quiz report subclass for the student to overview his own attempts (grades) report.
 *
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_own_attempts_overview_report extends quiz_attempts_report  {

    /**
     * Get the base URL for this report.
     * @return moodle_url the URL.
     */
    public function get_base_url() {
        return new moodle_url('/report/quiz/index.php',
                array('id' => $this->context->instanceid, 'mode' => $this->mode));
    }


    public function display($quiz, $cm, $course) {
        global $CFG, $DB, $OUTPUT, $PAGE, $USER;

        list($currentgroup, $students, $groupstudents, $allowed) =
                $this->init('overview', 'quiz_overview_settings_form', $quiz, $cm, $course);

	  $allowed = $students = array($USER->id);
	  //print 'students = ';print_r($students);
	  //print 'allowed = ';print_r($allowed);
        $options = new quiz_report_overview_options('overview', $quiz, $cm, $course);

        if ($fromform = $this->form->get_data()) {
            $options->process_settings_from_form($fromform);

        } else {
            $options->process_settings_from_params();
        }

        $this->form->set_data($options->get_initial_form_data());

        if ($options->attempts == self::ALL_WITH) {
            // This option is only available to users who can access all groups in
            // groups mode, so setting allowed to empty (which means all quiz attempts
            // are accessible, is not a security porblem.
            $allowed = array();
        }

        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                array('context' => context_course::instance($course->id)));
        $table = new quiz_report_overview_table($quiz, $this->context, $this->qmsubselect,
                $options, $groupstudents, $students, $questions, $options->get_url());

        $this->course = $course; // Hack to make this available in process_actions.

        $hasquestions = quiz_has_questions($quiz->id);
        if (!$table->is_downloading()) {
            if (!$hasquestions) {
                echo quiz_no_questions_message($quiz, $cm, $this->context);
            } else if (!$students) {
                echo $OUTPUT->notification(get_string('nostudentsyet'));
            } else if ($currentgroup && !$groupstudents) {
                echo $OUTPUT->notification(get_string('nostudentsingroup'));
            }
        }

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

		$attempts = quiz_report_get_attempts($from, $where, $params);
		//print '<pre>attempts =';print_r($attempts); print '</pre>';
		if (!isset($attempts) || count($attempts) <= 0) {
		   echo $OUTPUT->notification(get_string('noattempts', 'report_quiz'));
		   return;
		}

            // Define table columns.
            $columns = array();
            $headers = array();

		$headers[] = null;
		$columns[] = 'questionnumbertxt';
		foreach ($attempts as $attemptnumber => $attemptid) {
			  $headers[] = get_string('attemptbrief', 'report_quiz', $attemptnumber);
			  $columns[] = 'qsgrade'.$attemptid;
		}

		$fields = "slot.slot, q.id, q.length, slot.maxmark";
		$from = "{question} q JOIN {quiz_slots} slot ON slot.questionid = q.id";
		$where = "slot.quizid = ? AND q.length > 0";
            $table->set_sql($fields, $from, $where, array($quiz->id));

		//print '<pre>headers';print_r($headers);print '</pre>';
		//print '<pre>columns';print_r($columns);print '</pre>';
            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $options, false);
            $table->set_attribute('class', 'generaltable generalbox grades');
            $table->out($options->pagesize, true);
        }

        if (!$table->is_downloading() && $options->usercanseegrades) {
            $OUTPUT = $PAGE->get_renderer('report_quiz');
			/*
            if ($currentgroup && $groupstudents) {
                list($usql, $params) = $DB->get_in_or_equal($groupstudents);
                $params[] = $quiz->id;
                if ($DB->record_exists_select('quiz_grades', "userid $usql AND quiz = ?",
                        $params)) {
                    $imageurl = new moodle_url('/mod/quiz/report/overview/overviewgraph.php',
                            array('id' => $quiz->id, 'groupid' => $currentgroup));
                    $graphname = get_string('overviewreportgraphgroup', 'quiz_overview',
                            groups_get_group_name($currentgroup));
                    echo $output->graph($imageurl, $graphname);
                }
            }
            */

            if ($DB->record_exists('quiz_grades', array('quiz'=> $quiz->id))) {
                $imageurl = new moodle_url('/report/quiz/reportgraph.php',
                        array('id' => $quiz->id));
                $graphname = get_string('reportquizgraph', 'report_quiz');
                echo $OUTPUT->graph($imageurl, $graphname);
            }
        }
        return true;
    }

}

/**
 * Get the slots of real questions (not descriptions) in this quiz, in order.
 * @param object $quiz the quiz.
 * @return array of slot => $question object with fields
 *      ->slot, ->id, ->maxmark, ->number, ->length.
 */
function quiz_report_get_attempts($from, $where, $params) {
    global $DB;

    $attemptsdata = $DB->get_records_sql("
		SELECT quiza.id
		  FROM $from WHERE $where ORDER BY quiza.id", $params);

    $number = 1;
    $attempts = [];
    if (count($attemptsdata) > 0) {
        foreach ($attemptsdata as $attempt) {
    	      $attempts[$number] = $attempt->id;
    		$number++;
    	  }
   }

    return $attempts;
}
