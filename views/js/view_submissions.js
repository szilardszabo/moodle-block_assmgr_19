/**
 * Javascript for the onchange functions in the submissions table
 *
 * @copyright &copy; 2009-2010 University of London Computer Centre
 * @author http://www.ulcc.ac.uk, http://moodle.ulcc.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package AssMgr
 * @version 1.0
 */

M.assmgr.view_submissions = (function() {

    // keeps track of the outcome that the last request was sent for so that the returned grade
    // can be put in the right place

    var ajaxinprogress = false;
    

    function ajax_outcome_save(outcomeid, outcomescaleitem) {

        // get course_id and candidate_id from the form
        var form = document.getElementById('outassessform');
        var course_id = form.course_id.value;
        var candidate_id = form.candidate_id.value;
        ajaxinprogress = true;

		// nkowald - 2011-02-21 - Changed the relative path to include /VLE/ - needed on live environment
        YAHOO.util.Connect.asyncRequest('POST',
                                        '/blocks/assmgr/actions/save_outcomes_assessment.php',
                                        M.assmgr.view_submissions.callback,
                                        'ajax=true&course_id='+course_id+'&candidate_id='+candidate_id+'&outcomes['+outcomeid+']='+outcomescaleitem);

    }

    function removeselect(outcomeid) {

        var editicon = document.getElementById('editicon'+outcomeid);
        var grade = document.getElementById('columngrade'+outcomeid);
        var select = document.getElementById('columnselect'+outcomeid);

        M.assmgr.view_submissions.hideelement(select);
        M.assmgr.view_submissions.showelement(grade);
        M.assmgr.view_submissions.showelement(editicon);


    }

    return {

        outcomedivholder : '',

        /**
         * Hides the select elements when they lose focus
         */
        blurhandler: function(event) {

            var target = YAHOO.util.Event.getTarget(event);

            if ((typeof(target) != 'undefined') &&
                (target.tagName.toUpperCase() == 'SELECT') &&
                (target.name.toUpperCase().substr(0, 8) == 'OUTCOMES')) {
                  
                var select = target.parentNode;
                var outcomeid = select.id.substr(12);

                removeselect(outcomeid);

            }
        },

        /**
         * When the edit icon is clicked, this will unhide the select thing and show the current DB grade.
         * Needs to use two rules as some elements are hidden to start with and others are visible, so we need
         * to cover both cases
         */
        showelement : function(element) {
            YAHOO.util.Dom.removeClass(element, 'hidden');
            YAHOO.util.Dom.addClass(element, 'nothidden');
        },

        /**
         * hides the select again once the edit icon is clicked again. If event is a string, it is
         * coming from the ajax call
         */
        hideelement : function(element) {
            YAHOO.util.Dom.addClass(element, 'hidden');
            YAHOO.util.Dom.removeClass(element, 'nothidden');
        },

        uncheck_box : function(checkbox) {
            checkbox.checked = false;
        },

        add_loader_icon : function(div) {
            div.innerHTML = '<img src="/pix/i/loading_small.gif" />';
        },

        remove_loader_icon : function(div) {
            div.innerHTML = '';
        },

        add_error_icon : function(div) {
            div.innerHTML = '<img src="/pix/i/cross_red_big.gif" />';
        },

        add_style : function(classname, style) {
            var S1 = document.createElement('style');
            S1.type = 'text/css';
            var T = classname+' { '+style+'; }';
            T = document.createTextNode(T);
            S1.appendChild(T);
            document.body.appendChild(S1);
        },

        /**
         * Add listener to the element that contains the ajaxed-in table
         */
        submission_listener : function(event, target) {

            var target = YAHOO.util.Event.getTarget(event);

            // check that the right thing in the table has been clicked
            if (typeof(target) == 'undefined') {
                return false;
            }

            if ((target.tagName.toUpperCase() == 'IMG') &&
                (target.id.toUpperCase().search('EDITICON') != -1) &&
                (ajaxinprogress == false)) {

                // a checkbox has been clicked so we toggle the display of the current grade and
                // the select
                var editicon = target;
                var outcomeid = editicon.id.substr(8);
                var select = document.getElementById('columnselect'+outcomeid);
                var grade = document.getElementById('columngrade'+outcomeid);

                if (YAHOO.util.Dom.getStyle(select, 'display') == 'none') {

                    // show the select, but hide everything else
                    M.assmgr.view_submissions.showelement(select);
                    // focus on the actual select element, so that the blur listener to hide it again works
                    document.getElementById('columnselect'+outcomeid+'select').focus();
                    M.assmgr.view_submissions.hideelement(grade);
                    M.assmgr.view_submissions.hideelement(editicon);

                } else {
                    // only works if you click the edit icon whilst the select is visible - not used.
                    M.assmgr.view_submissions.hideelement(select);
                    M.assmgr.view_submissions.showelement(grade);
                }

            } else if ((target.tagName.toUpperCase() == 'SELECT') &&
                       (target.name.toUpperCase().substr(0, 8) == 'OUTCOMES')) {

                // A select has changed (not the pagination one) so we save using ajax
                var outcome_id = target.name.substr(9); // remove 'outcomes['
                outcome_id = outcome_id.substr(0, outcome_id.length-1); // remove ']'
                var outcomescaleitem = target.selectedIndex;

                M.assmgr.view_submissions.outcomeidholder = outcome_id;

                var loaderdiv = document.getElementById('columnloader'+outcome_id);
                M.assmgr.view_submissions.add_loader_icon(loaderdiv);

                ajax_outcome_save(outcome_id, outcomescaleitem);
            }
           // return false;
        },

        /**
         * AJAX callback object
         */
        callback :  {
            
            success : function(o) {

                // Put the returned grade HTML into the div and show it
                var grade = document.getElementById('columngrade'+M.assmgr.view_submissions.outcomeidholder);
                grade.innerHTML = o.responseText;
                M.assmgr.view_submissions.showelement(grade);

                // Hide the select element
                var select = document.getElementById('columnselect'+M.assmgr.view_submissions.outcomeidholder);
                M.assmgr.view_submissions.hideelement(select);

                // Remove the loader icon
                var loaderdiv = document.getElementById('columnloader'+M.assmgr.view_submissions.outcomeidholder);
                M.assmgr.view_submissions.remove_loader_icon(loaderdiv);

                var editicon = document.getElementById('editicon'+M.assmgr.view_submissions.outcomeidholder);
                M.assmgr.view_submissions.showelement(editicon);

                ajaxinprogress = false;
                
            },

            failure : function() {
                // Remove the loader icon and replace it with an error
                var loaderdiv = document.getElementById('columnloader'+M.assmgr.view_submissions.outcomeidholder);
                M.assmgr.view_submissions.add_error_icon(loaderdiv);
            }
            
        },

        /**
         * Makes the columns switch from non-js visibility to grade+edit icon when the table loads
         */
        hidecolumns : function() {

            var outcomedivs = YAHOO.util.Dom.getElementsByClassName('assmgroutcomediv');
            var length = outcomedivs.length;
            var outcomeid = 0;

            for (var i=0; i<length; i++) {
                outcomeid = outcomedivs[i].id.substr(10);
                removeselect(outcomeid);
                YAHOO.util.Event.addListener('columnselect'+outcomeid+'select', 'change', M.assmgr.view_submissions.submission_listener);
            }
        },

        init : function() {
            
            YAHOO.util.Event.addListener('submittedevidence_container', 'click', M.assmgr.view_submissions.submission_listener);
            
            YAHOO.util.Event.addBlurListener('submittedevidence_container', M.assmgr.view_submissions.blurhandler);
            // TODO update the above line so it is as per the line below when YUI goes beyone 2.8 in core Moodle
            // YAHOO.util.Event.addListener('submittedevidence_container', 'focusin', M.assmgr.view_submissions.blurhandler);

            // Need to loop through all outcomes and hide the select elements, leaving the grade and
            // the edit icon
            
        }

    };


})();

