<?php

//iini_set('memory_limit', '-1');
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

/**
 * Returns CPD Report based on filter.
 *
 * @param object $filter Filters include CPD Year, Date and User
 * @param array $editable If true this will add 'Edit' and 'Delete' columns to the resultset
 * @param array $extra Extra columns include 'user_name'; to add Users' name to the resultset
 * @return array
 */
function get_cpd_records($filter = null, $editable = false, $extra = array()) {
    global $CFG, $USER, $DB, $OUTPUT;
    // Added c.verified and c.notes columns in mdl_cpd table 
    // Updated  install.xml with the same columns 
    // Used the same in the query to get data
    $sql = "
        select	c.id, c.userid, c.objective, c.development_need, att.name as activitytype,c.verified,c.notes, c.activity,
		c.duedate, c.startdate, s.name as status, c.timetaken, c.description, c.ceus, c.cpdyearid, u.firstname, u.lastname
	from 	{cpd} as c
	join	{user} as u
		on c.userid = u.id
	left join 
		{cpd_activity_type} as att
		on c.activitytypeid = att.id
	left join 
		{cpd_status} as s
		on c.statusid = s.id
	";
    $where = null;
    if ($filter) {
        if (!empty($filter->userid)) {
            $where[] = "c.userid = {$filter->userid}";
        }
        if (!empty($filter->activitytypeid)) {
            $where[] = "c.activitytypeid = {$filter->activitytypeid} ";
        }
        if (!empty($filter->cpdyearid)) {
            $where[] = "c.cpdyearid = {$filter->cpdyearid} ";
        }
        if (isset($filter->from) || isset($filter->to)) {
            if ($filter->from && empty($filter->to)) {
                $where[] = "( c.duedate >= {$filter->from} or c.startdate >= {$filter->from} ) ";
            } else if ($filter->to && empty($filter->from)) {
                $to = $filter->to + ((60 * 60 * 24) - 1);
                $where[] = "( c.duedate < {$to} or c.startdate < {$to} ) ";
            } else if ($filter->from && $filter->to) {
                $to = $filter->to + (60 * 60 * 24) - 1;
                if ($filter->from < $to) {
                    $where[] = "( c.duedate between {$filter->from} and {$to} 
			       or c.startdate between {$filter->from} and {$to} ) ";
                }
            }
        }
    }
    if (!is_null($where)) {
        $sql .= " where " . implode(" and ", $where);
    }
    $sql .= " order by u.lastname, u.firstname, c.duedate, c.id ";
    //echo "<pre>$sql</pre>"; exit;
    $results = $DB->get_records_sql($sql);
    //print_r($results);
    $table_data = null;
    if ($results) {
        foreach ($results as $row) {
            $duedate = ($row->duedate) ? date("d-m-Y", $row->duedate) : '';
            $startdate = ($row->startdate) ? date("d-m-Y", $row->startdate) : '';
            //$enddate = ($row->enddate) ? date("d-m-Y", $row->enddate) : ''; COMMENTED OUT ON 11/21
            $timetaken = '';
            if (!empty($row->timetaken)) {
                $minutes = $row->timetaken % 60;
                $hours = ($row->timetaken - $minutes) / 60;
                $timetaken = (($hours) ? $hours : '0') . ':' . (($minutes) ? $minutes : '00');
            }

            $row_data = array();
            if (isset($extra['user_name']) && $extra['user_name']) {
                $row_data[] = "$row->firstname $row->lastname";
            }
            //array_push($row_data, $row->objective, $row->development_need, $row->description, $row->activity, $row->activitytype,  $duedate, $startdate, $enddate, $row->status, $timetaken, $row->ceus);//ADDED 11/16
            $checked = ( $row->verified == 1) ? "checked" : "";
            array_push($row_data, $row->objective, $row->development_need, $row->description, $row->activity, "<input type='checkbox' {$checked} disabled='disabled'> </input>", $row->notes, $row->activitytype, $startdate, $duedate, $row->status, $timetaken, $row->ceus);

            if ($editable) {
                $row_data[] = "<a href=\"$CFG->wwwroot/blocks/cpd_block/edit_activity.php?id={$row->id}&cpdyearid={$row->cpdyearid}\">Edit</a>";
                $row_data[] = "<a onclick=\"return confirm('Are you sure you want to delete?');\" href=\"$CFG->wwwroot/blocks/cpd_block/index.php?delete={$row->id}&cpdyearid={$row->cpdyearid}\">Delete</a>";
            }
            $table_data[] = $row_data;
        }
    }
    return $table_data;
}

/**
 * Validates filters
 *
 * @param object $filter Filters include CPD Year, Date and User
 * @return array
 */
function validate_filter(&$filter) {
    if (!$filter) {
        return false;
    }
    $errors = null;
    if (!empty($filter->from) && !empty($filter->to)) {
        if ($filter->from > $filter->to) {
            $errors[] = 'Date from cannot be more than date to.';
        }
    }
    return $errors;
}

/**
 * Deletes the specified CPD Activity
 *
 * @param int $id CPD Activity id
 * @return array
 */
function delete_cpd_record($id) {
    global $USER, $DB;
    return $DB->delete_records('cpd', array('id' => $id, 'userid' => $USER->id));
}

/**
 * Creates and Downloads a CSV file 
 *
 * @param string $filename Name of the CSV file. Do not include .csv extension.
 * @param int $headers CPD Activity id
 * @param int $data CPD Report Dataset
 * @return array
 */
function download_csv($filename, $headers, $data) {
    $filename .= ".csv";

    header("Content-Type: application/download\n");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Expires: 0");
    header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
    header("Pragma: public");

    $header = '"' . implode('","', $headers) . '"';
    echo $header . "\n";
    // Added code snippet to display exact values of verified flag
    foreach ((array) $data as $row) {
        for ($i = 0; $i < count($row); $i++) {
            if (strpos($row[$i], 'input')) {
                if (strpos($row[$i], 'checked'))
                    $row[$i] = 'True';
                else
                    $row[$i] = 'False';
            }
        }
        $text = '"' . implode('","', $row) . '"';
        echo $text . "\n";
    }
    exit;
}

/**
 * Creates and Downloads XLS file 
 *
 * @param string $filename Name of the XLS file. Do not include .xml extension.
 * @param mixed $headers Headers
 * @param int $data CPD Report Dataset
 * @return array
 */
function download_xls($filename, $headers, $data) {
    global $CFG;
    require_once($CFG->dirroot . '/lib/excellib.class.php');

    $wb = new MoodleExcelWorkbook("-");
    $wb->send($filename . '.xls');

    $ws = $wb->add_worksheet($filename);

    $n = 0;
    foreach ((array) $headers as $j => $value) {
        $ws->write_string(0, $n, $value);
        $n = $n + 1;
    }
    // Added code snippet to display exact values of verified flag
    foreach ((array) $data as $i => $row) {
        foreach ($row as $j => $value) {
            if (strpos($value, 'input')) {
                if (strpos($value, 'checked')) {
                    $ws->write_string($i + 1, $j, 'True');
                } else {
                    $ws->write_string($i + 1, $j, 'False');
                }
            } else {
                $ws->write_string($i + 1, $j, $value);
            }
        }
    }
    $wb->close();
}

/**
 * Returns a CPD Metadata item as a list of id=>name pairs
 *
 * @param string $name	CPD Metadata item name
 * @return array
 */
function get_cpd_menu($name) {
    global $DB;
    $cpd_menu = null;
    switch ($name) {
        case 'years':
            $cpd_years = $DB->get_records('cpd_year', array(), 'startdate asc, enddate asc');
            if ($cpd_years) {
                foreach ($cpd_years as $year) {
                    $cpd_menu[$year->id] = date("d/m/Y", $year->startdate) . " - " . date("d/m/Y", $year->enddate);
                }
            }
            break;
        case 'activity_types':
            $cpd_activity_types = $DB->get_records('cpd_activity_type', array(), 'name asc');
            if ($cpd_activity_types) {
                //$cpd_menu = records_to_menu($cpd_activity_types, 'id', 'name');
                $cpd_menu = array();
                foreach ($cpd_activity_types as $cpd_type) {
                    $cpd_menu[$cpd_type->id] = $cpd_type->name;
                }
            }
            break;
        case 'statuses':
            $cpd_statuses = $DB->get_records('cpd_status', array(), 'display_order asc');
            if ($cpd_statuses) {
                //$cpd_menu = records_to_menu($cpd_statuses, 'id', 'name');
                $cpd_menu = array();
                foreach ($cpd_statuses as $cpd_status) {
                    $cpd_menu[$cpd_status->id] = $cpd_status->name;
                }
            }
            break;
    }
    return $cpd_menu;
}

/**
 * Returns current cpd year id
 *
 * @return int or false if the CPD Years table is empty
 */
function get_current_cpd_year() {
    global $CFG, $DB;
    $sql = "	select	id from	{cpd_year} where unix_timestamp() between startdate and enddate
		order by startdate asc	 limit 	1 ";
    $results = $DB->get_records_sql($sql);
    if (empty($results)) {
        // If current cpd year is in the past
        $sql = " select	id from {cpd_year} order by enddate desc limit 	1 ";
        $results = $DB->get_records_sql($sql);
    }

    if (empty($results)) {
        return false; // Return false if still empty
    } else {
        return current($results)->id;
    }
}

/**
 * Prints a button used to print a CPD Report
 *
 * @param string $page Page name
 * @param object $filter_data Current filter data
 */
function print_print_button($page, $filter_data = null) {
    global $CFG;
    $link = $page;
    if (!empty($filter_data)) {
        $link .= '?' . get_query_string(array("print" => 1) + ((array) $filter_data));
    }

    echo '<form action="' . $page . '" method="get" target="_blank" onsubmit="return false;">';
    echo '<input type="hidden" name="print" value="1" />';
    echo '<input id="print_button" type="submit" value="Print" onclick="window.open(\'' . $link . '\', \'\', \'resizable=yes toolbar=no, location=no\');" />';
    echo '</form>';
}

/**
 * Helper function which returns a URL query string of the specified params
 *
 * @param array $params
 * @return string
 */
function get_query_string($params = array()) {
    $arr = array();
    foreach ($params as $key => $val) {
        $arr[] = urlencode($key) . "=" . urlencode($val);
    }
    return implode($arr, "&amp;");
}

/**
 * Processes a CPD Metadata item form.
 *
 * @param string $frm	CPD Metadata form name
 * @return boolean Result
 */
function process_meta_form($frm) {
    global $CFG, $DB;
    switch ($frm) {
        case 'activitytype':
            $name = optional_param('activitytype', NULL, PARAM_RAW);
            if (empty($name)) {
                $errors[] = "Activity type cannot be empty.";
                break;
            }
            $data = new stdClass;
            $data->name = $name;
            $frmid = optional_param('frmid', NULL, PARAM_INT);
            if (empty($frmid)) {
                $DB->insert_record('cpd_activity_type', $data);
            } else {
                $data->id = $frmid;
                $DB->update_record('cpd_activity_type', $data);
            }
            break;
        case 'cpdyears':
            $startday = optional_param('startday', NULL, PARAM_RAW);
            $startmonth = optional_param('startmonth', NULL, PARAM_RAW);
            $startyear = optional_param('startyear', NULL, PARAM_RAW);
            $endday = optional_param('endday', NULL, PARAM_RAW);
            $endmonth = optional_param('endmonth', NULL, PARAM_RAW);
            $endyear = optional_param('endyear', NULL, PARAM_RAW);

            $starttime = strtotime("{$startday}-{$startmonth}-{$startyear}");
            $endtime = strtotime("{$endday}-{$endmonth}-{$endyear}");

            if ($starttime && $endtime) {
                $endtime += ((60 * 60 * 24) - 1); //Add 23:59:59
                if ($starttime > $endtime) {
                    $errors[] = "Start date cannot be more than end date.";
                    break;
                }
                $data = new stdClass;
                $data->startdate = $starttime;
                $data->enddate = $endtime;
                $frmid = optional_param('frmid', NULL, PARAM_INT);
                if (empty($frmid)) {
                    $DB->insert_record('cpd_year', $data);
                } else {
                    $data->id = $frmid;
                    $DB->update_record('cpd_year', $data);
                }
            } else {
                $errors[] = "Date(s) invalid.";
            }
            break;
        case 'status':
            $name = optional_param('status', NULL, PARAM_RAW);
            if (empty($name)) {
                $errors[] = "Status name cannot be empty.";
                break;
            } else if ($old_status = $DB->get_record('cpd_status', array('name' => $name))) {
                $errors[] = "Status name must be unique.";
                break;
            }
            $data = new stdClass;
            $data->name = $name;
            $frmid = optional_param('frmid', NULL, PARAM_INT);
            if (empty($frmid)) {
                // Set display order as well
                $results = $DB->get_records_sql("select (max(display_order) + 1) as bottom from {cpd_status}");
                $data->display_order = current($results) ? current($results)->bottom : 1;
                $DB->insert_record('cpd_status', $data);
            } else {
                $data->id = $frmid;
                $DB->update_record('cpd_status', $data);
            }
    }

    if (isset($errors)) {
        return $errors;
    }
}

/**
 * Deletes the specified CPD Metadata item
 *
 * @param string $table Metadata table name
 * @param string $id Metadata item id
 * @return boolean Result
 */
function delete_meta_record($table, $id) {
    global $DB;

    if (empty($id)) {
        return false;
    }
    $result = false;

    switch ($table) {
        case 'activitytype':
            $result = $DB->delete_records('cpd_activity_type', array('id' => $id));
            break;
        case 'year':
            $result = $DB->delete_records('cpd_year', array('id' => $id));
            break;
        case 'status':
            $result = $DB->delete_records('cpd_status', array('id' => $id));
            break;
    }
    return $result;
}

/**
 * Changes display order of a CPD Metadata item
 *
 * @param string $table Metadata table name
 * @param string $table Metadata item id
 * @param string $move should be 'up' or 'down'
 */
// TODO: Should be applied to all Metadata items. This only works with Statuses for the moment
function change_display_order($table, $id, $move) {
    global $DB;

    if (empty($id)) {
        return false;
    }

    $table_name = NULL;
    switch ($table) {
        case 'status':
            $table_name = 'cpd_' . $table;
            $results = $DB->get_records($table_name, array(), 'display_order asc');
            $update_row1 = NULL;
            $update_row2 = NULL;
            $row = current($results);
            while ($row) {
                if ($row->id == $id) {
                    $update_row1 = $row;
                    if ($move == 'up') {
                        $update_row2 = prev($results);
                    } else if ($move == 'down') {
                        $update_row2 = next($results);
                    }
                    break;
                }
                $row = next($results);
            }
    }

    if (!empty($table_name) && !empty($update_row1) && !empty($update_row2)) {
        $new_display_order = $update_row2->display_order;
        // Swap the order
        $update_row2->display_order = $update_row1->display_order;
        $update_row1->display_order = $new_display_order;

        $DB->update_record($table_name, $update_row1);
        $DB->update_record($table_name, $update_row2);
    }
}

/**
 * Returns a specified Metadata item
 *
 * @param string $table Metadata item table name
 * @param int $id Metadata item id
 * @return array
 */
function get_meta_records($table, $id) {
    global $DB;

    if (empty($id)) {
        return false;
    }

    switch ($table) {
        case 'activitytype':
            return $DB->get_record('cpd_activity_type', array('id' => $id));
        case 'year':
            return $DB->get_record('cpd_year', array('id' => $id));
        case 'status':
            return $DB->get_record('cpd_status', array('id' => $id));
    }
}

/**
 * Processes CPD Activity form
 *
 * @param object $data CPD Activity form data
 * @param string $redirect Redirects to this URL if the form was processed successfully.
 * @return array An array of errors (if any)
 */
function process_activity_form(&$data, $redirect) {
    global $USER, $CFG, $DB;

    $data->userid = $USER->id;

    if (!$cpdyear = $DB->get_record('cpd_year', array('id' => $data->cpdyearid))) {
        print_error('Invalid CPD Year');
    }

    if ($status = $DB->get_record('cpd_status', array('id' => $data->statusid))) {
        if (strtoupper($status->name) == 'OBJECTIVE MET' && empty($data->enddate)) {
            $data->enddate = time(); // Set end date to today
        } else {
            $data->enddate = NULL; // Set end date to null if Status isn't 'completed'
        }
    }
    if (checkdate($data->duedate['m'], $data->duedate['d'], $data->duedate['Y'])) {
        $data->duedate = strtotime("{$data->duedate['Y']}-{$data->duedate['m']}-{$data->duedate['d']}");
        if ($data->duedate < $cpdyear->startdate || $data->duedate > $cpdyear->enddate) {
            $errors[] = 'Due date must be within the CPD year (' . date("d M Y", $cpdyear->startdate) . ' - ' . date("d M Y", $cpdyear->enddate) . ').';
        }
    }
    if (checkdate($data->startdate['m'], $data->startdate['d'], $data->startdate['Y'])) {
        $data->startdate = strtotime("{$data->startdate['Y']}-{$data->startdate['m']}-{$data->startdate['d']}");
        if ($data->startdate < $cpdyear->startdate || $data->startdate > $cpdyear->enddate) {
            $errors[] = 'Start date must be within the CPD year (' . date("d M Y", $cpdyear->startdate) . ' - ' . date("d M Y", $cpdyear->enddate) . ').';
        }
    }
    if (!empty($data->timetaken['hours']) || !empty($data->timetaken['minutes'])) {
        // Covert to minutes
        $data->timetaken = ($data->timetaken['hours'] * 60) + $data->timetaken['minutes'];
    } else {
        $data->timetaken = null;
    }
    if (empty($errors)) {
        if (!empty($data->id)) { // Just make sure
            $result = $DB->update_record('cpd', $data);
        } else {
            $result = $DB->insert_record('cpd', $data);
        }
        if ($result) {
//            redirect($redirect); // Interrputs the page navigation for a small time
            echo"<script>window.location = '$redirect'</script>";
            exit;
        } else {
            $errors[] = 'Unable to update records.';
        }
    }
    return $errors;
}

/**
 * Validation callback function - verified the column line of csv file.
 * Converts standard column names to lowercase.
 * @param csv_import_reader $cir
 * @param array $stdfields standard user fields
 * @param array $profilefields custom profile fields
 * @param moodle_url $returnurl return url in case of any error
 * @return array list of fields
 */
function validate_cpd_columns(csv_import_reader $cir, $stdfields, $profilefields, moodle_url $returnurl) {
    $columns = $cir->get_columns();

    if (empty($columns)) {
        $cir->close();
        $cir->cleanup();
        print_error('cannotreadtmpfile', 'error', $returnurl);
    }
    if (count($columns) < 2) {
        $cir->close();
        $cir->cleanup();
        print_error('csvfewcolumns', 'error', $returnurl);
    }
    // Valdiate no. of columns
    $processed = array();
    foreach ($columns as $key => $unused) {
        $field = $columns[$key];
        $lcfield = core_text::strtolower($field);
        if (in_array($field, $stdfields) or in_array($lcfield, $stdfields)) {
            // standard fields are only lowercase
            $newfield = $lcfield;
        } else if (in_array($field, $profilefields)) {
            // exact profile field name match - these are case sensitive
            $newfield = $field;
        } else if (in_array($lcfield, $profilefields)) {
            // hack: somebody wrote uppercase in csv file, but the system knows only lowercase profile field
            $newfield = $lcfield;
        } else {
            $cir->close();
            $cir->cleanup();
            print_error('invalidfieldname', 'error', $returnurl, $field);
        }
        if (in_array($newfield, $processed)) {
            $cir->close();
            $cir->cleanup();
            print_error('duplicatefieldname', 'error', $returnurl, $newfield);
        }
        $processed[$key] = $newfield;
    }
    return $processed;
}

/**
 * 
 * @global type $USER
 * @global type $DB
 * @param type $cpddata CPD  data to update in mdl_cpd table
 * @param type $redirect Redirects to this URL if the form was processed successfully.
 * @return string retun any errors during updating records to database.
 */
function upload_cpd_data(&$cpddata, $redirect) {

    global $USER, $DB;
    $errors = array();
    // Insert distinct records to mdl_cpd table
    if (!empty($cpddata)) {
        foreach ($cpddata as $k => $data) {
            if (!empty($data)) {
                $result = $DB->insert_record('cpd', $data);
                if (!$result) {
                    $errors[] = 'Error updating cpd records.';
                    break;
                }
            }
        }
        if ($result) {
            echo"<script>window.location = '$redirect'</script>";
            exit;
        }
    }
    return $errors;
}

/**
 * 
 * @global type $DB
 * @param type $userid userid of the current logged in user
 * @return string user email
 */
function get_user_email(&$userid) {
    global $DB;
    $user = $DB->get_record('user', array('id' => $userid));
    if ($user) {
        return $user->email;
    } else {
        return "Invalid User";
    }
}