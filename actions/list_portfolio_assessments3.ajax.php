<?php
/**
 * Ajax file for List Portfolio Assessments
 *
 * @copyright &copy; 2009-2010 University of London Computer Centre
 * @author http://www.ulcc.ac.uk, http://moodle.ulcc.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package AssMgr
 * @version 2.0
 */

//include moodle config
require_once('../../../config.php');

global $USER, $CFG, $PARSER;

require_once($CFG->dirroot.'/lib/dmllib.php');

//include the moodle library
require_once($CFG->dirroot.'/lib/moodlelib.php');

//include the assessment manager parser class
require_once($CFG->dirroot.'/blocks/assmgr/classes/assmgr_parser.class.php');

//include assessment manager db class
require_once($CFG->dirroot.'/blocks/assmgr/db/assmgr_db.php');

//include the library file
require_once($CFG->dirroot.'/blocks/assmgr/lib.php');

//include the static constants
require_once($CFG->dirroot.'/blocks/assmgr/constants.php');

//include the default class
require_once($CFG->dirroot.'/blocks/assmgr/classes/tables/assmgr_ajax_table.class.php');

//include the progress_bar class
require_once($CFG->dirroot.'/blocks/assmgr/classes/assmgr_progress_bar.class.php');

// db class manager
$dbc = new assmgr_db();

// table unique prefix
$assessments_table_uprefix = "assmgr_assessments";

// get the course id
$course_id = $PARSER->optional_param('course_id', null, PARAM_INT);

// if there is a course_id: fetch the course, or fail if the id is wrong
if(!empty($course_id) && ($course = $dbc->get_course($course_id)) == false) {
    print_error('incorrectcourseid', 'block_assmgr');
}

// get the category from the course, or from the params
$category_id = empty($course->category) ? $PARSER->optional_param('category_id', null, PARAM_INT) : $course->category;

// if there is a category_id: fetch the category, or fail if the id is wrong
if(!empty($category_id) && ($category = $dbc->get_category($category_id)) == false) {
    print_error('incorrectcategoryid', 'block_assmgr');
}

if(isset($USER->access)) {
    $accessinfo = $USER->access;
} else {
    $accessinfo = $USER->access = get_user_access_sitewide($USER->id);
}

//find all courses that this user has the assess portfolio capability on
$allowedcourses = get_user_courses_bycap(
    $USER->id,
    "block/assmgr:assessportfolio",
    $accessinfo,
    true,
    'c.sortorder ASC',
    //'c.shortname ASC',
    array('category', 'fullname', 'groupmode', 'defaultgroupingid')
);

// if there is a specific course then check the user permissions against that course
if(!empty($course_id)) {
    // get the current course context
    $coursecontext = get_context_instance(CONTEXT_COURSE, $course_id);

    // bail if we couldn't find the course context
    if(!$coursecontext) {
        print_error('incorrectcourseid', 'block_assmgr');
    }

    // user is an assessor if they can assess portfolios (admins are assessors)
    $access_isassessor = has_capability('block/assmgr:assessportfolio', $coursecontext);

    // can the user see the user photos
    $access_canviewuserdetails = has_capability('moodle/user:viewdetails', $coursecontext);

    $groupcourse = $course;
} else {
    // the user is an assessor if they have at least one course they can assess
    $access_isassessor = !empty($allowedcourses);

    // get the first course
    $groupcourse = $allowedcourses[0];

    // get the course context of the first course
    $coursecontext = get_context_instance(CONTEXT_COURSE, $groupcourse->id);

    // can the user see the user photos
    $access_canviewuserdetails = has_capability('moodle/user:viewdetails', $coursecontext);
}

// check to see if groups are being used in this course
$group_id = groups_get_course_group($groupcourse, true);

if(!$access_isassessor) {
    print_error('nopageaccess', 'block_assmgr');
}

// get the unique list of categories from that course list
$categories = array();

foreach($allowedcourses as $allowedcourse) {
    if(empty($categories[$allowedcourse->category])) {
        $categories[$allowedcourse->category] = $dbc->get_category_by_course($allowedcourse->id);
        $categories[$allowedcourse->category]->course_id = $allowedcourse->id;
    }
}

// get the list of course id => name pairs, and find the unique list of users
// that have the capability to submit evidence for themselves (i.e. candidates)
// within those courses
$courselist = array();
$candidatelist = array();
if(!empty($allowedcourses)) {

	// nkowald - 2011-06-20 - Exclude E-learning technologists from showing in assessment manager
	// Exclude elts
	$excludes_csv = '';
	$excludes = array();
	$query = "SELECT DISTINCT userid FROM mdl_role_assignments WHERE roleid = 17"; // E-learning technologists
	if ($elts = get_records_sql($query)) {
		foreach ($elts as $elt) {
			$excludes[] = $elt->userid;
		}
	}
	// convert to CSV format for use in IN query.
	$excludes_csv = implode(',', $excludes);
	
    foreach($allowedcourses as $allowedcourse) {
        // filter out courses not in the chosen category, if there is one
        if(empty($category_id) || $category_id == $allowedcourse->category) {
            $courselist[$allowedcourse->id] = $allowedcourse;

            // get the current course context
            $coursecontext = get_context_instance(CONTEXT_COURSE, $allowedcourse->id);
			
            // get the candidates for this course context
            $candidates = get_users_by_capability(
                $coursecontext,
                'block/assmgr:creddelevidenceforself',
                'u.id', 
				'', 
				'', 
				'', 
				'', 
				$excludes_csv,
                false
            );

            // add the list of candidates for this course context
            $courselist[$allowedcourse->id]->candidates = array();

            if(!empty($candidates)) {
                // add them to the list, overwriting duplicates
                foreach($candidates as $candidate) {
                    $candidatelist[$candidate->id] = $candidate->id;
                    $courselist[$allowedcourse->id]->candidates[] = $candidate->id;
                }
            }
        }
    }
}

foreach ($courselist as $c_id => $course_ob) {
    $block_instance = $dbc->get_block_course_ids($c_id);
    if (empty($block_instance)) {
        unset($courselist[$c_id]);
    }
}

// set up the flexible table for displaying the portfolios
$flextable = new assmgr_ajax_table($assessments_table_uprefix);

$flextable->define_baseurl($CFG->wwwroot."/blocks/assmgr/actions/list_portfolio_assessments3.php?category_id={$category_id}&amp;course_id={$course_id}&amp;group_id={$group_id}");
$flextable->define_ajaxurl($CFG->wwwroot."/blocks/assmgr/actions/list_portfolio_assessments3.ajax.php?category_id={$category_id}&amp;course_id={$course_id}&amp;group_id={$group_id}");

// apply the horizontal pagination
$courses = (empty($course_id)) ? $flextable->limitcols($courselist, get_config('block_assmgr', 'maxunits')) : array($course->id => $courselist[$course->id]);

$flextable->hoz_string = 'displaypingunits';

// if we've filtered by a single course then only show candidates in that course
$candidatelist = (empty($course_id)) ? $candidatelist : $courselist[$course->id]->candidates;

// set the basic details to dispaly in the table
$columns = array('fullname');
foreach(array_keys($courses) as $id) {
    $columns[] = 'course'.$id;
}

// determine how long each course name can be based on how many columns there are
$maxlength = ($flextable->hozcols > 1) ? (100 / $flextable->hozcols) : 100;

$headers = array('');
foreach($courses as $courseobj) {
    $headers[] = limit_length($courseobj->shortname, $maxlength, $courseobj->fullname);
}

$flextable->define_columns($columns);
$flextable->define_headers($headers);

// make the table sortable
$flextable->sortable(true, 'lastname', 'DESC');
$flextable->initialbars(true);

// MODIFY THIS
$flextable->set_attribute('summary', get_string('listportfolios', 'block_assmgr'));

$flextable->set_attribute('cellspacing', '0');
$flextable->set_attribute('class', 'generaltable fit');

$flextable->setup();

// fetch all the candidates needing assessment
$matrix = $dbc->get_portfolio_matrix($candidatelist, $courses, $flextable, $group_id);

// instantiate the progress_bar
$progress = new assmgr_progress_bar();
    
// TODO this needs a view
if(!empty($categories)) {

	$sql = "select id,name
			from mdl_course_categories
			where parent=0";
	$rootCategories = get_records_sql($sql);
	
	$categSelect = array();
	
	foreach ($categories as $categ){
		$path = explode('/', $categ->path);
		$categ->rootId = $path[1];
		$rootName = $rootCategories[$categ->rootId]->name;
		$categSelect[$rootName][$categ->name] = $categ;
	}
	
?>
    <form id="switch_category" action="<?php echo $flextable->baseurl;?>" method="get" class="mform">
        <div class="fitem">
            <div class="fitemtitle">
                <label for="switch_category_id">
                    <?php echo get_string('qualification', 'block_assmgr'); ?>
                </label>
            </div>
            <div class="felement fselect">
                <select name="category_id" id="switch_category_id" onchange="document.getElementById('switch_category').submit();">
                    <option value="0"><?php echo get_string('allmyqualifications', 'block_assmgr'); ?></option>
                    <?php
                    /*
                    foreach ($categories as $q) {
                        $selected = ($q->id == $category_id) ? 'selected="selected"' : ''; ?>
                        <option value="<?php echo $q->id; ?>" <?php echo $selected; ?>>
                            <?php echo $q->name; ?>
                        </option>
                        <?php
                    } 
                    */
                    foreach($categSelect as $direct => $categ) {
                        echo "<optgroup label='$direct'>";
                        ksort(&$categ, SORT_STRING);
						foreach ($categ as $q) {
							$selected = ($q->id == $category_id) ? 'selected="selected"' : ''; ?>
							<option value="<?php echo $q->id; ?>" <?php echo $selected; ?>>
								<?php echo $q->name; ?>
							</option><?php
						} 
                        echo "</optgroup>";						
					}
                    ?>
                </select>
            </div>
        </div>
    </form>
    <!--form id="switch_course" action="<?php echo $flextable->baseurl;?>" method="get" class="mform"-->
    <form id="switch_course" action="/grade/report/grader/portfolios.php?plugin=grader" method="get" class="mform">
        <div class="fitem">
            <div class="fitemtitle">
                <label for="switch_course_id">
                    <?php echo get_string('course', 'block_assmgr'); ?>
                </label>
            </div>
            <div class="felement fselect">
                <!--input type="hidden" name="category_id" value="<?php echo $category_id; ?>" /-->
                <!--select name="course_id" id="switch_course_id" onchange="document.getElementById('switch_course').submit();"-->
                <select name="id" id="id" onchange="document.getElementById('switch_course').submit();">
                    <option value="0"><?php echo get_string('allmycourses', 'block_assmgr'); ?></option>
                    <?php
                    foreach ($courselist as $c) {
                        $selected = ($c->id == $course_id) ? 'selected="selected"' : ''; ?>
                        <option value="<?php echo $c->id; ?>" <?php echo $selected; ?>>
                            <?php echo $c->fullname; ?>
                        </option>
                        <?php
                    } ?>
                </select>
            </div>
        </div>
    </form>
    <?php
}

// print the group selector
groups_print_course_menu($groupcourse, $flextable->baseurl);

if(!empty($matrix)) {
    foreach($matrix as $candidate) {
        // build the row
        $data = array();

        $user_course_id = empty($course_id) ? $SITE->id : $course_id;

        if ($access_canviewuserdetails) {
            $data['fullname'] = print_user_picture($dbc->get_user($candidate->candidate_id), $user_course_id, null, 0, true)."<a href='{$CFG->wwwroot}/user/view.php?id={$candidate->candidate_id}&amp;course={$user_course_id}' class=\"userlink\">".fullname($candidate)."</a>";
        } else {
            $data['fullname'] = print_user_picture($dbc->get_user($candidate->candidate_id), $user_course_id, null, 0, true, false).fullname($candidate);
        }

        // iterate through all the columns in the row
        foreach($courses as $id => $courseobj) {

            // does the current portoflio need assessment
            $needsassess = "needsassess{$id}";
            $needsassess = (bool) $candidate->$needsassess;

            $achieved = "course{$id}";
            if(is_null($candidate->$achieved)) {
                // if the value is null then this candidate is not enrolled in thie course
                $cell = get_string('notenrolled', 'block_assmgr');

                $highlight = null;
                
            } else {
				
                // get the progress bar
                $progbar = $progress->get_unit_progress($candidate->candidate_id, $courseobj->id, $access_isassessor, 'small', $candidate->$achieved);

                // get the portfolio grade
                $portgrade = $dbc->get_portfolio_grade($courseobj->id, $candidate->candidate_id);
                $finalgrade = !empty($portgrade->grade) ? $portgrade->str_grade : '';

                // make a link to edit the portfolio
                $linkstr = ($needsassess) ? get_string('assess', 'block_assmgr') : get_string('view', 'block_assmgr') ;
                $link = "<a href='edit_portfolio.php?course_id={$id}&amp;candidate_id={$candidate->candidate_id}#submittedevidence'>{$linkstr}</a>";

                $eventstr = get_string('setdate', 'block_assmgr');
                $eventlink = "<a href='list_assess_dates.php?course_id={$id}&amp;candidate_id={$candidate->candidate_id}&amp;group_id={$group_id}'>".get_string('setdate', 'block_assmgr')."</a>";

                $gradestr = (!empty($finalgrade)) ? get_string('grade','block_assmgr').':'.$finalgrade : '';

                $cell = $progbar; //.' '.$link.' | '.$eventlink;
                
                //$cell = (!empty($gradestr)) ? $cell.' | '.$gradestr : $cell;
                $cell = (!empty($gradestr)) ? $cell.$gradestr : $cell;

                $highlight = ($needsassess) ? 'highlight' : null;
            }

            $data['course'.$id] = "<div class='progress_bar_cell {$highlight}'>{$cell}</div>";
        }
        
        $flextable->add_data_keyed($data);
    }
}

$flextable->print_html();
