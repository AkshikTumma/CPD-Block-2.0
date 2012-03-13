<?php

// This file is part of CPD Block for Moodle 2.1+
//
// CPD Block for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CPD Block for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CPD Block for Moodle.  If not, see <http://www.gnu.org/licenses/>.
 
 
/**
 * This page is a part of CPD Block, a CPD Report module conversion for Moodle 2.1.
 * It was done by Konstiantyn Kononenkov and sponsored by Iowa State University 
 * Child Welfare Research and Training Project.
 *
 * @package   cpd-block                              
 * @copyright 2010 Kineo open Source                                         
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->libdir.'/formslib.php');
require_once('cpd_filter_form.php');
require_once('lib.php');

global $CFG, $USER, $PAGE, $OUTPUT;

// Check permissions.
require_login(SITEID, false);
$systemcontext = get_context_instance(CONTEXT_SYSTEM);
require_capability('report/cpd:userview', $systemcontext);

// Log request
add_to_log(SITEID, "admin", "report capability", "report/cpd/index.php");

if ($delete_id = optional_param('delete', NULL, PARAM_INT))
{
	delete_cpd_record($delete_id);
}

$cpdyearid = optional_param('cpdyearid', NULL, PARAM_INT); // Current CPD year id
$download = optional_param('download', NULL, PARAM_RAW);
$print = optional_param('print', NULL, PARAM_RAW);
$xls= optional_param('xls', NULL, PARAM_RAW);

// CPD Report headers
$columns = array (
		'objective' => 'Training/Event Title',
		'development_need' => 'Location',
		'description' => 'Short Description',
		'activity' => 'Instructor(s)',
		'activity_type' => 'Activity type',
                'start_date' => 'Start date',
		'due_date' => 'Completion Date',
		'status' => 'Status',
		'timetaken' => 'Time taken',
		'ceus' => 'CEUs'
	);

if (!empty($download) || !empty($print) || !empty($xls))
{
	// Filter object
	$filter_data = new stdClass;
	$filter_data->cpdyearid = $cpdyearid;
	$filter_data->from = optional_param('from', NULL, PARAM_RAW);
	$filter_data->to = optional_param('to', NULL, PARAM_RAW);
	$filter_data->userid = $USER->id;
	
	if (($cpd_records = get_cpd_records($filter_data)) && !empty($download))
	{
		// Add disclaimer
		$cpd_records[] = array();
		$cpd_records[] = array('I confirm that the above is a true record of CPD undertaken by me:', '');
		$cpd_records[] = array('Date:', '');
		download_csv('cpd_record', $columns, $cpd_records);
		exit;
	}
        else if($cpd_records && !empty($xls))
        {
		download_xls('cpd_record', $columns, $cpd_records);
        }
}
else
{
	$columns['edit'] = 'Edit';
	$columns['delete'] = 'Delete';
}

$cpd_years = get_cpd_menu('years');
$userid = $USER->id;

$PAGE->set_url($CFG->wwwroot.'/blocks/cpd_block/index.php?user='.$userid);//ADDED 11/10
if (empty($print))
{
    // Print the header.
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title("Enter/View CPD Forms");
    $PAGE->set_heading("Enter/View CPD Forms");
    $PAGE->navbar->add("Enter/View CPD report");
    echo $OUTPUT->header();
}
else
{
    $module = array('name' => 'yui_dom-event', 'fullpath' => '/blocks/cpd_block/js/print.js'); //ADDED 11/08
    $PAGE->requires->js_module( $module ); //ADDED 11/08
    echo $OUTPUT->header();
}

if (! empty($errors))
{
	echo '<div class="box errorbox errorboxcontent">'. implode('<br />' , $errors) .'</div>';
}

$filter = new cpd_filter_form('index.php', compact('cpd_years', 'userid'), 'post', '', array('class' => 'cpdfilter'));
if (empty($cpd_records)) {
	$filter_data = $filter->get_data();
	if (empty($filter_data))
	{
		// Filter object
		$filter_data = new stdClass;
		$filter_data->userid = $USER->id;
		$cpdyearid = empty($cpdyearid) ? get_current_cpd_year() : $cpdyearid;
		$filter_data->cpdyearid = $cpdyearid; // Set cpd year id always needs to be set
		$filter_data->from = null;
		$filter_data->to = null;
	}
	if (! ($errors = validate_filter($filter_data)) )
	{
		$cpd_records = get_cpd_records($filter_data, true);
	}
	$filter->set_data(compact('cpdyearid'));
} else {
	$filter->set_data((array)$filter_data);
}

$filter->display();

// Add activity button
if ($cpd_years && $cpdyearid)
{
	echo '<form name="addcpd" method="get" action="edit_activity.php">';
	echo '<input type="hidden" name="cpdyearid" value="'.$cpdyearid.'">';
	echo '<input type="submit" value="Add Activity">';
	echo '</form>';
}

if ($cpd_records)
{
    if (!empty($cpd_years[$cpdyearid]))
    {
        echo $OUTPUT->heading("CPD Year: {$cpd_years[$cpdyearid]}", 4, 'printonly');
    }
    echo $OUTPUT->heading("$USER->firstname $USER->lastname", 3, 'printonly');
	
    $outputtableheader = array();
    $outputtableheader[] = get_string('title','block_cpd_block');
    $outputtableheader[] = get_string('location','block_cpd_block');
    $outputtableheader[] = get_string('desc','block_cpd_block');
    $outputtableheader[] = get_string('instructor','block_cpd_block');
    $outputtableheader[] = get_string('acttype','block_cpd_block');
    $outputtableheader[] = get_string('startdate','block_cpd_block');
    $outputtableheader[] = get_string('completiondate','block_cpd_block');
    $outputtableheader[] = get_string('status','block_cpd_block');
    $outputtableheader[] = get_string('timetaken','block_cpd_block');
    $outputtableheader[] = get_string('ceus','block_cpd_block');
    if( empty($print))
    {
        $outputtableheader[] = get_string('edit','block_cpd_block');
        $outputtableheader[] = get_string('delete','block_cpd_block');
    }

    $outputtable = new html_table();
    $outputtable->head = $outputtableheader;
    $outputtable->data = $cpd_records;
    $outputtable->head = $outputtableheader;
    echo html_writer::table($outputtable);
	
	
    if (!empty($print))
    {
		// Disclaimer
		echo '	<table class="disclaimer" cellpadding="0" cellspacing="5" border="0">
				<tr>
					<td class="name">I confirm that the above is a true record of CPD undertaken by me.</td>
					<td class="fillbox">&nbsp;</td>
					<td class="date">Date</td>
					<td class="fillbox date">&nbsp;</td>
					</tr>
			</table>';
    }
	
    echo '<table class="boxalignleft"><tr class="tr_btn">';
    echo '<td class="td_btn">';
    echo $OUTPUT->single_button(new moodle_url('/blocks/cpd_block/index.php', array('download' => 1) + ((array)$filter_data)), "Export as CSV", 'get');
    echo '</td><td class="td_btn>';
       
    //ADDED 11/22
    echo '<td class="td_btn">';
    echo $OUTPUT->single_button(new moodle_url('/blocks/cpd_block/index.php', array('xls' => 1) + ((array)$filter_data)), "Export as XLS", 'get');
    echo '</td><td class="td_btn>';

    print_print_button('index.php', $filter_data);
    echo '</td></tr></table>';
}

echo $OUTPUT->footer();
