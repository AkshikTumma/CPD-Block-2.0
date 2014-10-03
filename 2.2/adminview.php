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
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/lib/tablelib.php');
require_once "$CFG->libdir/formslib.php";
require_once('cpd_filter_form.php');
require_once('lib.php');

global $CFG, $USER, $PAGE, $DB;
$PAGE_SIZE = 5;

// Check permissions.
require_login();
//$systemcontext = get_context_instance(CONTEXT_SYSTEM);
$systemcontext = CONTEXT_SYSTEM::instance();
$PAGE->set_context($systemcontext);
require_capability('report/cpd:adminview', $systemcontext);

$print = optional_param('print', false, PARAM_BOOL);
$download = optional_param('download', false, PARAM_BOOL);
$xls = optional_param('xls', false, PARAM_BOOL);

// Extra columns
$extra_columns['user_name'] = true;

// CPD Report headers
//DK
//Replace old variable name to new one.
//Add Short Description and CEUs
$columns = array(
    'name' => 'Name',
    'objective' => 'Training/Event Title',
    'development_need' => 'Location',
    'description' => 'Short Description',
    'activity' => 'Instructor(s)',
    'activity_type' => 'Activity type',
    'start_date' => 'Start date',
    'due_date' => 'Completion Date',
    'status' => 'Status',
    'timetaken' => 'Total Time Spent',
    'ceus' => 'CEUs'
);

$filter_data = NULL;

if (!empty($download) || !empty($print) || !empty($xls)) {
    // Filter object
    $filter_data = new stdClass;
    $filter_data->cpdyearid = optional_param('cpdyearid', NULL, PARAM_INT);
    $filter_data->filterbydate = optional_param('filterbydate', false, PARAM_BOOL);
    if (!empty($filter_data->filterbydate)) {
        $filter_data->from = optional_param('from', NULL, PARAM_RAW); //TODO CHOOSE PARAM TYPE
        $filter_data->to = optional_param('to', NULL, PARAM_RAW);
    }
    $filter_data->activitytypeid = optional_param('activitytypeid', NULL, PARAM_INT);
    $filter_data->userid = optional_param('userid', NULL, PARAM_INT);

    $cpd_records = get_cpd_records($filter_data, false, $extra_columns);
    if ($cpd_records && !empty($download)) {
        download_csv('cpd_record', $columns, $cpd_records);
        exit;
    } else if ($cpd_records && !empty($xls)) {
        download_xls('cpd_record', $columns, $cpd_records);
        exit;
    }
}

$cpd_years = get_cpd_menu('years');
$activity_types = get_cpd_menu('activity_types');
$users = $DB->get_records('user'); // Get all users

$PAGE->set_url($CFG->wwwroot . '/blocks/cpd_block/adminview.php');

// Print the header.
if (has_capability('report/cpd:superadminview', $systemcontext) && empty($print)) {
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title("CPD reports");
    $PAGE->set_heading("CPD reports");
    $PAGE->navbar->add("CPD Reports");

    echo $OUTPUT->header();
} else {
    if (empty($print)) {
        echo $OUTPUT->header();
    } else {
        $module = array('name' => 'yui_dom-event', 'fullpath' => '/blocks/cpd_block/js/print.js'); //ADDED 11/21
        $PAGE->requires->js_module($module);
        echo $OUTPUT->header();
    }
}

$OUTPUT->heading('CPD Development Report');
$filter = new cpd_filter_form('adminview.php', compact('cpd_years', 'activity_types', 'users'), 'post', '', array('class' => 'cpdfilter'));

if (empty($cpd_records)) {
    $filter_data = $filter->get_data();
    if (empty($filter_data)) {
        $filter_data = new stdClass;
        $filter_data->userid = optional_param('userid', null, PARAM_INT);
        $filter_data->filterbydate = optional_param('filterbydate', false, PARAM_BOOL);
        $filter_data->activitytypeid = optional_param('activitytypeid', null, PARAM_INT);
        $filter_data->cpdyearid = optional_param('cpdyearid', null, PARAM_INT);
        if (!empty($filter_data->filterbydate)) {
            $filter_data->from = optional_param('from', null, PARAM_INT);
            $filter_data->to = optional_param('to', null, PARAM_INT);
        }
        $filter->set_data((array) $filter_data);
    }
    if (!($errors = validate_filter($filter_data))) {
        $cpd_records = get_cpd_records($filter_data, false, $extra_columns);
    }
}
if (isset($errors)) {
    echo '<div class="box errorbox errorboxcontent">' . implode('<br />', $errors) . '</div>';
}

$filter->display();
if (!empty($cpd_records)) {
    $table = new flexible_table('cpd');
    $table->define_columns(array_keys($columns));
    $table->define_headers(array_values($columns));

    $table->sortable(false);
    $table->collapsible(false);
    if (empty($print)) { //Setup paging if not printing
        $table->pageable(true);
        $table->pagesize($PAGE_SIZE, count($cpd_records));
    }
    $table->column_style_all('white-space', 'normal');
    $table->set_attribute('cellspacing', '0');
    $table->set_attribute('id', 'attempts');
    $table->set_attribute('class', 'generaltable boxalignleft cpd');

    $args = '';
    foreach ($filter_data as $key => $val) {
        if (!empty($val))
            $args .= $key . '=' . $val . '&';
    }
    $table->define_baseurl(new moodle_url($CFG->wwwroot . '/blocks/cpd_block/adminview.php?' . $args));
    $table->setup();

    if (!empty($print)) {
        $loopend = count($cpd_records);
        for ($i = 0; $i < $loopend; $i = $i + 1) {
            $table->add_data($cpd_records[$i]);
        }
    } else {
        $start_i = $table->currpage * $PAGE_SIZE;
        $loopend = min($start_i + $PAGE_SIZE, count($cpd_records));
        for ($i = $start_i; $i < $loopend; $i = $i + 1) {
            $table->add_data($cpd_records[$i]);
        }
    }
    $table->print_html();

    if (empty($print)) {
        echo '<table class="boxalignleft"><tr><td>';
        echo $OUTPUT->single_button(new moodle_url('/blocks/cpd_block/adminview.php', array('download' => 1) + ((array) $filter_data)), "Export as CSV", 'get');
        echo '</td><td>';
        echo $OUTPUT->single_button(new moodle_url('/blocks/cpd_block/adminview.php', array('xls' => 1) + ((array) $filter_data)), "Export as XLS", 'get');
        echo '</td><td>';
        print_print_button('adminview.php', $filter_data);
        echo '</td></tr></table>';
    }
}

if (has_capability('report/cpd:superadminview', $systemcontext) && empty($print)) {
    echo $OUTPUT->footer();
} else {
    echo $OUTPUT->footer();
}
