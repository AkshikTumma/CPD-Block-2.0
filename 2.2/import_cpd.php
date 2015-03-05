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
require('../../config.php');
require_once ($CFG->libdir . '/adminlib.php');
require_once ($CFG->libdir . '/formslib.php');
require_once ($CFG->libdir . '/csvlib.class.php');
require_once ('cpd_read_form.php');
require_once ('lib.php');

global $CFG, $USER, $PAGE, $DB;

// Check permissions.
require_login();
$systemcontext = CONTEXT_SYSTEM::instance();
$PAGE->set_context($systemcontext);
require_capability('report/cpd:adminview', $systemcontext);

$cpdyearid = required_param('cpdyearid', PARAM_INT);
$importid = optional_param('importid', '', PARAM_INT);

$uploadFlag = optional_param('uploadFlag', '', PARAM_INT);
$previewrows = optional_param('previewrows', 10, PARAM_INT);

$PAGE_SIZE = 5;
core_php_time_limit::raise(60 * 60); // 1 hour should be enough
raise_memory_limit(MEMORY_HUGE);

$redirect = "$CFG->wwwroot/blocks/cpd_block/index.php?cpdyearid=$cpdyearid"; /* Redirect back to user CPDs */
$returnurl = new moodle_url($redirect);

$context = CONTEXT_USER::instance($USER->id, IGNORE_MISSING);
//
$userid = $USER->id;

if (!empty($cpdyearid)) {
    if (!$cpdyear = $DB->get_record('cpd_year', array('id' => $cpdyearid))) { //get cpd year start and end
        print_error('Invalid CPD Year');
    }
}
$cpdStartDate = $cpdyear->startdate;
$cpdEndDate = $cpdyear->enddate;

//
$PAGE->set_url($CFG->wwwroot . '/blocks/cpd_block/import_cpd.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title("Upload CPD Records");
$PAGE->set_heading("Upload CPD Records");
$PAGE->navbar->add('CPD Forms', $redirect);
$PAGE->navbar->add('Upload CPDs');
// Array of all valid fields for validation
$STD_FIELDS = array('firstname', 'middle', 'lastname', 'Attendance Code',
    'Course Title', 'Course name',
    'Course start date', 'Course end date', 'Trainer',
    'Location', 'City', 'CEUs', 'Training hours',);

// Include all name fields.
$STD_FIELDS = array_merge($STD_FIELDS, get_all_user_name_fields());
$PRF_FIELDS = array();
// Get data
$activity_types = get_cpd_menu('activity_types');
$statuses = get_cpd_menu('statuses');
$userEmail = get_user_email($userid);

if (isset($errors)) {
    echo '<div class="box errorbox errorboxcontent">' . implode('<br />', $errors) . '</div>';
}
if (empty($importid)) {

    $mform1 = new cpd_read_form(null, array('cpdyearid' => $cpdyearid, 'importid' => $importid));

    if ($formdata = $mform1->get_data()) {
        $importid = csv_import_reader::get_new_iid('uploadcpd');
        $cir = new csv_import_reader($importid, 'uploadcpd');
        $content = $mform1->get_file_content('cpdfile');
        $readcount = $cir->load_csv_content($content, $formdata->encoding, $formdata->delimiter_name);
        $csverror = $cir->get_error();
        if (!is_null($csverror)) {
            if ($readcount === false) {
                print_error('csvfileerror', 'block_cpd_block', $returnurl, $csverror);
            } else if ($readcount == 0) {
                print_error('csvemptyfile', 'error', $returnurl, $csverror);
            } else {
                print_error('csvloaderror', '', $returnurl, $csverror);
            }
        }
        // Test if correct format of csv file is uploaded
        $filecolumns = validate_cpd_columns($cir, $STD_FIELDS, $PRF_FIELDS, $returnurl);
    } else {
        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string('uploadcpd', 'block_cpd_block'), 'uploadcpd', 'block_cpd_block');
        $mform1->display();
        echo $OUTPUT->footer();
        die;
    }
} else {
    $cir = new csv_import_reader($importid, 'uploadcpd');
    $filecolumns = validate_cpd_columns($cir, $STD_FIELDS, $PRF_FIELDS, $returnurl);
}
//
$data = array();
$cir->init();
$linenum = 1; //column header is first line
while ($fields = $cir->next()) {
    $linenum++;
    $rowcols = array();
    $rowcols['line'] = $linenum;
    foreach ($fields as $key => $field) {
        $rowcols[$key] = s(trim($field));
    }
    $data[] = $rowcols;
}
$cir->close();

/*
 * Reading data from csv file and checking for date validations. 
 * Checking if the date falls in cpd year startdate and duedate range.
 * Setting activity type and status type to '1' by default. 
 * User can later modify the records in the edit section individually 
 * 
 */
$newdata = array();
$cpddata = array();
$duplicateHash = array();
$rowcount = 0;
foreach ($data as $i => $row) {
    $rowcount++;
    // Split string to date format : Start Date
    $syear = substr($row[6], 0, 4);
    $smonth = substr($row[6], 4, 2);
    $sday = substr($row[6], 6, 2);
    $sdate = $sday . "-" . $smonth . "-" . $syear;
    $stime = strtotime($sdate);
    $startDate = date('d-m-Y', $stime);
    // Split string to date format : End Date
    $eyear = substr($row[7], 0, 4);
    $emonth = substr($row[7], 4, 2);
    $eday = substr($row[7], 6, 2);
    $edate = $eday . "-" . $emonth . "-" . $eyear;
    $etime = strtotime($edate);
    $endDate = date('d-m-Y', $etime);
    // Start date and End date validations w.r.t cpdyear Start date and End date
    if (checkdate($emonth, $eday, $eyear)) {
        if ($etime < $cpdStartDate || $etime > $cpdEndDate) {
            print_error('Due date must be within the CPD year (' . date("d M Y", $cpdStartDate) . ' - ' . date("d M Y", $cpdEndDate) . ').');
        }
    }
    if (checkdate($smonth, $sday, $syear)) {
        if ($stime < $cpdStartDate || $stime > $cpdEndDate) {
            print_error('Start date must be within the CPD year (' . date("d M Y", $cpdStartDate) . ' - ' . date("d M Y", $cpdEndDate) . ').');
        }
    }
    $rowcols = array($row[4], $row[9], $row[5], $row[8], "CPD-02", $startDate, $endDate, "Status CPD-01", $row[12], $row[11]);
    $cpddatacols = array(
        "userid" => $userid,
        "objective" => $row[4],
        "development_need" => $row[9],
        "activitytypeid" => "1",
        "verified" => "0",
        "notes" => "",
        "activity" => $row[8],
        "duedate" => strtotime($endDate),
        "startdate" => strtotime($startDate),
        "statusid" => "1",
        "cpdyearid" => $cpdyearid,
        "timetaken" => $row[12] * 60,
        "description" => $row[5],
        "ceus" => $row[11],
    );
    $hash = $row[4] . "-" . $row[9] . "-" . $row[8] . "-" . strtotime($endDate) . "-" . strtotime($startDate) . "-" . ($row[12] * 60) . "-" . $row[5] . "-" . $row[11];
    $hash = str_replace(" ", "", $hash);
    // Remove duplicate records from csv file
    if (!array_key_exists($hash, $duplicateHash)) {
        // Limit the view of the rows in Table to the preview no. of rows set by user.
        if ($rowcount <= $previewrows) {
            $newdata[] = $rowcols;
        }
        $cpddata[] = $cpddatacols;
        $duplicateHash[$hash] = 1;
    }
}

// Print Table
$table = new html_table();
$header = array();
$header[] = get_string('title', 'block_cpd_block');
$header[] = get_string('location', 'block_cpd_block');
$header[] = get_string('desc', 'block_cpd_block');
$header[] = get_string('instructor', 'block_cpd_block');
$header[] = get_string('acttype', 'block_cpd_block');
$header[] = get_string('startdate', 'block_cpd_block');
$header[] = get_string('completiondate', 'block_cpd_block');
$header[] = get_string('status', 'block_cpd_block');
$header[] = get_string('timetaken', 'block_cpd_block');
$header[] = get_string('ceus', 'block_cpd_block');
//
$table->head = $header;
// To indicate that there are more rows but only $previewrow value of rows are displayed
if ($rowcount > $previewrows) {
    $newdata[] = array_fill(0, count($header), '...');
}
$table->data = $newdata;

$mform2 = new cpd_read_form_2(null, array('cpdyearid' => $cpdyearid, 'importid' => $importid));

if (empty($uploadFlag)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('uploadcpdpreview', 'block_cpd_block'));
    echo html_writer::start_tag('div', array('class' => 'no-overflow'));
    echo html_writer::table($table);
    $mform2->display();
    echo $OUTPUT->footer();
    die();
} else {
    //If a file has been uploaded, then process it
    if ($mform2->is_cancelled()) {
        $cir->cleanup(true);
        redirect($returnurl);
    } else if ($formdata = $mform2->get_data()) {
        $errors = upload_cpd_data($cpddata, $returnurl);
    }
}



