<?php
/**
 * This answer to the submissions  page.
 *
 * @copyright &copy; 2009-2010 University of London Computer Centre
 * @author http://www.ulcc.ac.uk, http://moodle.ulcc.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package AssMgr
 * @version 2.0
 */

//include moodle config
//require_once(dirname(__FILE__).'/../../../config.php');

// remove this when testing is complete
$path_to_config = dirname($_SERVER['SCRIPT_FILENAME']).'/../../../config.php';
while (($collapsed = preg_replace('|/[^/]+/\.\./|','/',$path_to_config,1)) !== $path_to_config) {
    $path_to_config = $collapsed;
}
// nkowald - 2011-02-16 - Was causing an error
//require_once($path_to_config);

require_once('../../../config.php');

global $USER, $CFG, $PARSER;

// Meta includes
require_once($CFG->dirroot.'/blocks/assmgr/actions_includes.php');

//include the submission_table class
require_once($CFG->dirroot.'/blocks/assmgr/classes/tables/assmgr_submission_table.class.php');

//inlcude the gradelib
require_once($CFG->libdir.'/gradelib.php');

//get the verificiation id if it exists
$verification_id = $PARSER->optional_param('verification_id', null, PARAM_INT);

if (!empty($verification_id)) {

    if (!$access_isverifier) {
        print_error('nosubmissionverify', 'block_assmgr');
    }
} else {
    $access_isverifier = false;
}

// db class manager
$dbc = new assmgr_db();

// get the page params
$course_id    = $PARSER->required_param('course_id', PARAM_INT);
$candidate_id = $PARSER->optional_param('candidate_id', $USER->id, PARAM_INT);

// get the portfolio id
$portfolio = $dbc->get_portfolio($candidate_id, $course_id);
$portfolio_id = $portfolio->id;

// get the portfolio scale
$scale = $dbc->get_portfolio_scale($portfolio_id);

// create the flexible table for displaying the records
$flextable = new assmgr_submission_table("submissions_tablecourse_id{$course_id}candidate_id{$candidate_id}");
$flextable->define_baseurl($CFG->wwwroot."/blocks/assmgr/actions/edit_portfolio.php?course_id={$course_id}&amp;candidate_id={$candidate_id}");
$flextable->define_ajaxurl($CFG->wwwroot."/blocks/assmgr/actions/view_submissions.ajax.php?course_id={$course_id}&amp;candidate_id={$candidate_id}");

$flextable->define_fragment('submittedevidence');

// set some table params
$flextable->access_isassessor = $access_isassessor;
$flextable->access_isverifier = $access_isverifier;
$flextable->course_id = $course_id;
$flextable->candidate_id = $candidate_id;
$flextable->scale = $scale;
$flextable->header_class = 'item catlevel1 cell';

// set the basic details to dispaly in the table
$columns = array('name');
$headers = array(get_string('name', 'block_assmgr'));

// add the details columns
if($flextable->get_filter('show_details')) {
    $columns[] = 'description';
    $columns[] = 'submission_date';
    $columns[] = 'confirmation';
    $columns[] = 'assorevid';

    $headers[] = get_string('description', 'block_assmgr');
    $headers[] = get_string('submissiondate', 'block_assmgr');
    $headers[] = "<img src='{$CFG->wwwroot}/blocks/assmgr/pix/icons/unknown.gif' alt='".get_string('confirmation', 'block_assmgr')."' title='".get_string('confirmationstatus', 'block_assmgr')."' />";
    $headers[] = "<img src='{$CFG->wwwroot}/blocks/assmgr/pix/icons/assessor.gif' alt='".get_string('assessor', 'block_assmgr')."' title='".get_string('submissionmadebyassessor', 'block_assmgr')."' />";
}

// are we showing outcomes or evidence types
if($flextable->get_filter('show_outcomes')) {
    // get the list of outcomes for this portfolio
 	
	$outcomes = $dbc->get_outcomes_set($portfolio, $flextable);
    $outcomes = (!$outcomes) ? array() : $outcomes;
	
	if (empty($outcomes)) echo "NO OUTCOMES";

    // apply the horizontal pagination
    $outcomes = $flextable->limitcols($outcomes, $flextable->get_filter('show_details') ? get_config('block_assmgr', 'maxoutcomesshort') : get_config('block_assmgr', 'maxoutcomeslong'));
    $flextable->hoz_string = 'displayingoutcomes';

    // get all the portfolio grades (i.e. achieved outcomes)
    $flextable->grades = $dbc->get_portfolio_outcome_grades($portfolio_id);

    // add the selected outcomes as column headers to the table
    foreach($outcomes as $id => $outcome) {

        $columns[] = "outcome{$id}";
        $headers[] = limit_length($outcome->shortname, 15, $outcome->description);
    }

    $evidincetypes = array();
} else {
    // get the list of evidence types
    $evidincetypes = $dbc->get_evidence_types();
    $evidincetypes = (!$evidincetypes) ? array() : $evidincetypes;

    // apply the horizontal pagination
    $evidincetypes = $flextable->limitcols($evidincetypes, $flextable->get_filter('show_details') ? get_config('block_assmgr', 'maxevidtypesshort') : get_config('block_assmgr', 'maxevidtypeslong'));
    $flextable->hoz_string = 'displayingevidtypes';

    // add them as column headers to the table
    foreach($evidincetypes as $id => $type) {
        $columns[] = "subevty{$id}";
        $headers[] = limit_length(get_string($type->name, 'block_assmgr'), 15, get_string($type->description, 'block_assmgr'));
    }

    $outcomes = array();
}


// set the outcomes and evidincetypes in the flextable
$flextable->outcomes = $outcomes;
$flextable->evidincetypes = $evidincetypes;

$flextable->define_columns($columns);
$flextable->define_headers($headers);

// setup the options for the table
$flextable->sortable(true, 'name', 'ASC');

// use the same styling as the gradebook
$flextable->set_attribute('id', 'user-grades');
$flextable->set_attribute('cellspacing', '0');
$flextable->set_attribute('class', 'gradestable flexible boxaligncenter generaltable');
$flextable->set_attribute('summary', get_string('submittedevidence', 'block_assmgr'));

$flextable->column_class('name', 'leftalign');
$flextable->column_class('description', 'leftalign');
$flextable->column_class('submission_date', 'leftalign');

// setup the table - now we can use it
$flextable->setup();

// get the submissions and their outcomes
$matrix = $dbc->get_submission_matrix($portfolio_id, $candidate_id, $flextable, $outcomes);

$oddrow = true;

$scales = array();

//if(empty($matrix))$matrix = array ( 12825 => stdClass::__set_state(array( 'portfolio_id' => '11425', 'course_id' => '26110', 'candidate_id' => '31789', 'evidence_id' => '12850', 'name' => 'P5 Assessment', 'hidden' => '0', 'outcome22707' => NULL, 'claim22707' => NULL, 'outcome22708' => NULL, 'claim22708' => NULL, 'outcome22709' => NULL, 'claim22709' => NULL, 'outcome22710' => NULL, 'claim22710' => NULL, 'outcome22711' => NULL, 'claim22711' => NULL, 'outcome22712' => NULL, 'claim22712' => NULL, 'outcome22713' => NULL, 'claim22713' => NULL, 'outcome22714' => NULL, 'claim22714' => NULL, 'submission_id' => '12825')));

if(empty($matrix)) {
    
	$m = new stdClass; 
	
	$m->portfolio_id = ''; //'11425';
	$m->course_id = ''; //'26110';
	$m->candidate_id = ''; //'31789';
	$m->evidence_id = 0; //'12851';
	$m->name = ''; //'P5 Assessment;';
	$m->hidden = ''; //0;
	$m->submission_id = ''; //12825;
	
	$matrix = array (0 => $m);
}

//if($USER->username=='ezoneadmin')print_object($matrix);
		
if(!empty($matrix)) {

    // iterate through the result set
    foreach ($matrix as $row) {
	
		$data = array();

        // get the evidence name and make it a link to the resource
        $data['name'] = $flextable->get_evidence_resource_link2($row);	//ssz(250113): modified function call to make it possible to show the grades even if there are no submissions

		if($data['name'] != '') {

			// add the action links
			$data['name'] .= '<span class="commands">';
		
			// add an edit link
			if ($access_isassessor) {
				$data['name'] .= $flextable->get_edit_assessment_link($row);
			} else {
				$data['name'] .= $flextable->get_edit_claim_link($row);
			}

			// if the submission is not locked then show the editing options
			$graded = $dbc->has_submission_grades($row->submission_id);
			$mine   = $dbc->is_submission_mine($row->submission_id);

			// add a delete link
			$data['name'] .= $flextable->get_delete_submission_link($row);

			if(!$access_isassessor) {
				// add a hide/show link
				$data['name'] .= $flextable->get_hidden_submission_link($row);
			}

			$data['name'] .= '</span>';
		}
		
        if ($flextable->get_filter('show_details')) {
            // get the evidence description
            $data['description'] = limit_length($row->description, 50);

            // get the timestamp of the evidence
            $data['submission_date'] = userdate($row->submission_date, get_string('strftimedate', 'langconfig'));

            // does it need confirmation
            if ($row->confirmation) {
                $data['confirmation'] = confirmation_status($row->confirmation, true);
            } else {
                $title = get_string('confirmationunecessary', 'block_assmgr');
                $data['confirmation'] = "<img class='tick' src='{$CFG->wwwroot}/blocks/assmgr/pix/icons/adv.gif' alt='".get_string('greenstar', 'block_assmgr')."' title='{$title}' />";
            }

            // was it submitted by an assessor
            if ($row->assorevid) {
                $title = get_string('submittedbyanassessor', 'block_assmgr');
                $data['assorevid'] = "<img class='tick' src='{$CFG->wwwroot}/blocks/assmgr/pix/icons/tick_green_small.gif  ' alt='".get_string('greenticksmall', 'block_assmgr')."' title='{$title}' />";
            }
        }

        // has the submission been assessed (may have been but achieved no outcomes)
        $is_assessed = $dbc->has_submission_grades($row->submission_id);

        // add the outcomes and claims
        foreach($outcomes as $id => $outcome) {
            $outfield = "outcome{$id}";
            $clmfield = "claim{$id}";

            // get the scale for the outcome
            if (empty($scales[$outcome->scaleid])) {
                $scales[$outcome->scaleid] = $dbc->get_scale($outcome->scaleid, $outcome->gradepass);
            }

            $scale = $scales[$outcome->scaleid];

            $tick = '';

            if ($is_assessed) {

                if (!empty($row->$outfield)) {
                    $tick = $scale->render_scale_item($row->$outfield);
                } else {
                    // if it has been assessed as 'no outcome', we will end up here
                    $tick = $scale->render_scale_item();
                }
            } else {
                // the claims are only shown if the submission has not been assessed
                if (!empty($row->$clmfield)) {
                    $tick = $scale->render_scale_item($row->$clmfield, true);
                }
            }

            $data[$outfield] = $tick;
        }

        // add the evidence types and claims
        foreach($evidincetypes as $id => $type) {
            $subevty = "subevty{$id}";
            $clmevty = "clmevty{$id}";

            $tick = '';

            if($is_assessed) {
                if(!empty($row->$subevty)) {
                    $tick = "<img class='tick' src='{$CFG->wwwroot}/blocks/assmgr/pix/assessorcrit_small.png' alt='".get_string('greenticksmalllight', 'block_assmgr')."' title='{$row->$subevty}' />";
                }
            } else {
                if(!empty($row->$clmevty)) {
                    $tick = "<img class='tick' src='{$CFG->wwwroot}/blocks/assmgr/pix/candidatecrit_small.png' alt='".get_string('blueticksmall', 'block_assmgr')."' title='".get_string('claim', 'block_assmgr')."'/>";
                }
            }

            $data[$subevty] = $tick;
        }

        // toggle the row classes
        $rowclass = ($oddrow) ? 'odd' : 'even';
        $oddrow = $oddrow ? false : true;

        if($row->hidden) {
            $rowclass = ' dimmed_text dimmed';
        }

        $onclick = "set_row(this.parentNode.rowIndex);";

        // add the rows to the table
        $flextable->add_data_keyed($data, $rowclass, $onclick);
    }
}

$flextable->print_html();