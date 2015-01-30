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
require_once "$CFG->libdir/formslib.php";
require_once('edit_activity_form.php');
require_once('lib.php');

require_login(SITEID, false);
//
$systemcontext = CONTEXT_SYSTEM::instance();
require_capability('report/cpd:adminview', $systemcontext);
global $USER, $CFG, $DB;

$cpdyearid = required_param('cpdyearid', PARAM_INT);
$cpdid = optional_param('id', NULL, PARAM_INT);
$cpd_record = null;

$redirect = "$CFG->wwwroot/blocks/cpd_block/index.php?cpdyearid=$cpdyearid"; /* Redirect back to user CPDs */

if (!empty($cpdyearid)) {
    if (!$cpdyear = $DB->get_record('cpd_year', array('id' => $cpdyearid))) { //get cpd year start and end
        error('Invalid CPD Year');
    }
}

if (!empty($cpdid)) {
    if (!$cpd_record = $DB->get_record('cpd', array('id' => $cpdid))) {
        error('Invalid CPD Activity');
    }
}

//get data
$activity_types = get_cpd_menu('activity_types');
$statuses = get_cpd_menu('statuses');

// Print the header.
$PAGE->set_url($CFG->wwwroot . '/blocks/cpd_block/edit_activity.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title("Edit CPD Form");
$PAGE->set_heading("Edit CPD Form");
$PAGE->navbar->add('CPD Forms', $redirect);
$PAGE->navbar->add('Edit Form');
echo $OUTPUT->header();

if (isset($errors)) {
    echo '<div class="box errorbox errorboxcontent">' . implode('<br />', $errors) . '</div>';
}

$mform = new edit_activity_form("edit_activity.php", compact('activity_types', 'statuses', 'cpdid', 'cpdyearid', 'cpdyear'));
$frmdata = $mform->get_data();
if (!empty($frmdata)) {
    $errors = process_activity_form($frmdata, $redirect);
} else if ($cpd_record) {
    $cpd_record = (array) $cpd_record;
    if ($cpd_record['duedate']) {
        $cpd_record['duedate[d]'] = date('d', $cpd_record['duedate']);
        $cpd_record['duedate[m]'] = date('m', $cpd_record['duedate']);
        $cpd_record['duedate[Y]'] = date('Y', $cpd_record['duedate']);
        unset($cpd_record['duedate']);
    }
    if ($cpd_record['startdate']) {
        $cpd_record['startdate[d]'] = date('d', $cpd_record['startdate']);
        $cpd_record['startdate[m]'] = date('m', $cpd_record['startdate']);
        $cpd_record['startdate[Y]'] = date('Y', $cpd_record['startdate']);
        unset($cpd_record['startdate']);
    }
    if ($cpd_record['timetaken']) {
        $cpd_record['timetaken[minutes]'] = $cpd_record['timetaken'] % 60;
        $cpd_record['timetaken[hours]'] = ($cpd_record['timetaken'] - $cpd_record['timetaken[minutes]']) / 60;
        unset($cpd_record['timetaken']);
    }
    $mform->set_data($cpd_record);
} else {
    //Set due and start dates to today
    $dates['duedate[d]'] = date('d', $cpdyear->enddate);
    $dates['duedate[m]'] = date('m', $cpdyear->enddate);
    $dates['duedate[Y]'] = date('Y', $cpdyear->enddate);
    $dates['startdate[d]'] = date('d');
    $dates['startdate[m]'] = date('m');
    $dates['startdate[Y]'] = date('Y');
    $mform->set_data($dates);
}

$mform->display();

echo $OUTPUT->footer();
