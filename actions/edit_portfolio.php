<?php
/**
 * This page displays all the submissions made in a portfolio, from either
 * the candidate's or the assessor's perspective, depending on the candidate_id
 * and the user's capabilities
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
require_once('../../../config.php');

global $USER, $CFG, $PARSER;

// Meta includes
require_once($CFG->dirroot.'/blocks/assmgr/actions_includes.php');

// include the evidence resource class
require_once($CFG->dirroot.'/blocks/assmgr/classes/resources/assmgr_resource.php');

//get the id of the course that is currently being used
$course_id = $PARSER->required_param('course_id', PARAM_INT);

if ($course_id == SITEID) {
    print_error('errorinsitecourse','block_assmgr');
}

$candidate_id = $PARSER->optional_param('candidate_id', $USER->id, PARAM_INT);

// folder ID
$folder_id = $PARSER->optional_param('folder_id', 0, PARAM_INT);

//get the verificiation id if it exists
$verification_id = $PARSER->optional_param('verification_id', null, PARAM_INT);

// instantiate the db
$dbc = new assmgr_db();

/*
if (!empty($verification_id)) {
    if (!$access_isverifier) {
        print_error('nosubmissionverify', 'block_assmgr');
    }
    $verification = $dbc->get_verification($verification_id);
} else {
    $access_isverifier = false;
}
*/

/*
// you must be either a candidate or an assessor to edit a portfolio
if(!$access_iscandidate && !$access_isassessor) {
    print_error('noeditportfoliopermission','block_assmgr');
}
*/

// nkowald - 2011-06-23 - Instead of checking logged in user != candidate, use a role
/*
if($access_iscandidate && $USER->id != $candidate_id) {
//if($access_iscandidate && !has_capability('block/assmgr:verifyportfolio', $coursecontext, $USER->id)) {
    // candidates can't edit someone else's portfolio
    print_error('noeditothersportfolio', 'block_assmgr');
}
*/

/*
if($access_isassessor) {
    // assessors can't assess their own portfolio
    if($USER->id == $candidate_id) {
        print_error('cantassessownportfolio', 'block_assmgr');
    }

    // make sure the candidate is actually a candidate in this context
    $iscandidate = has_capability('block/assmgr:creddelevidenceforself', $coursecontext, $candidate_id, false);

    if(!$iscandidate) {
        print_error('portfolionotincourse', 'block_assmgr');
    }
}
*/

// get the candidate, course and category
$candidate = $dbc->get_user($candidate_id);
$course = $dbc->get_course($course_id);
$coursecat = $dbc->get_category($course->category);

// get the portfolio if it exists
$portfolio_id = check_portfolio($candidate_id, $course_id);

// get the configuration for this instance
$config = $dbc->get_instance_config($course_id);

// is the current portfolio locked?
if($dbc->lock_exists($portfolio_id)) {
    // renew the lock if it belongs to the current user
    if($dbc->lock_exists($portfolio_id, $USER->id)) {
        $dbc->renew_lock($portfolio_id, $USER->id);
    } else {
        // otherwise throw an error
        print_error('portfolioinuse', 'block_assmgr');
    }
} else {
    // create a new lock
    $dbc->create_lock($portfolio_id, $USER->id);
}

// update imported evidence
assmgr_resource::update_resources($course_id, $candidate_id, false);

if($access_isassessor || $access_isverifier) {
    // references to the candidate should be in the 3rd person
    $page_heading = get_string('candidateportfolio', 'block_assmgr', fullname($candidate));
} else {
    // references to the candidate should be in the 1st person
    //$page_heading = get_string('myportfolio', 'block_assmgr');
    print_error('noeditportfoliopermission','block_assmgr');
}

// setup the navigation breadcrumbs
$navlinks[] = array('name' => get_string('blockname', 'block_assmgr'), 'link' => null, 'type' => 'title');

if($access_isassessor && !$access_isverifier) {
    // assessor breadcrumbs
	$course_link = $CFG->wwwroot . '/course/view.php?id='.$course_id;
    $navlinks[] = array('name' => $coursecat->name, 'link' => $CFG->wwwroot."/blocks/assmgr/actions/list_portfolio_assessments.php?category_id={$coursecat->id}", 'type' => 'title');
    $navlinks[] = array('name' => $course->shortname, 'link' => $course_link, 'type' => 'title');
    $navlinks[] = array('name' => 'Portfolios', 'link' => $CFG->wwwroot."/blocks/assmgr/actions/list_portfolio_assessments.php?course_id={$course->id}", 'type' => 'title');
    $navlinks[] = array('name' => fullname($candidate), 'link' => '', 'type' => 'title');
} elseif ($access_isverifier) {
    // verifier breadcrumbs
    $navlinks[] = array('name' => get_string('listverifications', 'block_assmgr'),    'link' => $CFG->wwwroot.'/blocks/assmgr/actions/list_verifications.php?course_id='.$course_id, 'type' => 'title');
    $navlinks[] = array('name' => userdate($verification->timecreated, get_string('strftimedate', 'langconfig')),  'link' => null, 'type' => 'title');
    $navlinks[] = array('name' => get_string('verificationsample', 'block_assmgr'),   'link' => $CFG->wwwroot.'/blocks/assmgr/actions/edit_verification.php?course_id='.$course_id.'&amp;verification_id='.$verification_id, 'type' => 'title');
    $navlinks[] = array('name' => get_string('conductverification', 'block_assmgr'),  'link' => $CFG->wwwroot.'/blocks/assmgr/actions/view_verification.php?course_id='.$course_id.'&amp;verification_id='.$verification_id, 'type' => 'title');
} else {
    // candidate breadcrumbs
    $navlinks[] = array('name' => get_string('myportfolio', 'block_assmgr'), 'link' => '', 'type' => 'title');
}

// setup the page title and heading
$PAGE->title = $course->shortname.': '.get_string('blockname','block_assmgr');
$PAGE->set_heading($course->fullname);
$PAGE->set_navigation = assmgr_build_navigation($navlinks);
// nkowald - 2011-02-21 - Changed the relative path to include /VLE/ - needed on live environment
$PAGE->set_url($CFG->wwwroot .'/blocks/assmgr/actions/edit_portfolio.php', $PARSER->get_params());

if($folder_id == 0) {
    // if a folder is not specified,
    // I have to select the folder with the same name of the course
    // IF ANY
    $folder = $dbc->get_default_folder($course_id, $candidate_id);

    if($folder) {
        $folder_id = $folder->id;
    }
}

//MOODLE LOG candidate portfolio viewed
$log_action = get_string('logportfolioview', 'block_assmgr');
$log_url = "edit_portfolio.php?course_id={$course_id}&amp;candidate_id={$candidate_id}";
$logstrings = new stdClass;
$logstrings->name = fullname($candidate);
$logstrings->course = $course->shortname;
$log_info = get_string('logportfolioviewinfo', 'block_assmgr', $logstrings);
assmgr_add_to_log($course_id, $log_action, $log_url, $log_info);

$param = $dbc->get_instance_config($course->id);

require_once($CFG->dirroot.'/blocks/assmgr/views/edit_portfolio.html');
?>
