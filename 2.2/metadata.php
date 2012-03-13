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
 * This page is used to add, modify or delete CPD Years, Activity types, or Statuses.
 *
 * You can also change the display order of the Statuses. 
 * You cannot delete or modify status 'Objective Met', because End Date of a CPD Activity is set when
 * the status is changed to this.
 *
 * @package   cpd-block                                       
 * @copyright 2010 Kineo open Source                                         
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');

global $DB;

// Check permissions.
require_login(SITEID, false);
$systemcontext = get_context_instance(CONTEXT_SYSTEM);
require_capability('report/cpd:superadminview', $systemcontext);

$id = optional_param('id', NULL, PARAM_INT);
$errors = null;
$edit = null;

if( $process = optional_param('process', NULL, PARAM_RAW) )
{
	$errors = process_meta_form($process);
}
else if ( $table = optional_param('delete', NULL, PARAM_RAW) )
{
	delete_meta_record($table, $id);
}
else if ( $table = optional_param('edit', NULL, PARAM_RAW) )
{
	if ($result = get_meta_records($table, $id))
	{
		$edit[$table] = $result;
	}
}
else if ( $table = optional_param('moveup', NULL, PARAM_RAW) )
{
	change_display_order($table, $id, 'up');
}
else if ( $table = optional_param('movedown', NULL, PARAM_RAW) )
{
	change_display_order($table, $id, 'down');
}

$activity_types = $DB->get_records('cpd_activity_type', array(), 'name asc');
$years = $DB->get_records('cpd_year', array(), 'startdate asc, enddate asc');
$statuses = $DB->get_records('cpd_status', array(), 'display_order asc');

// Print the header.
$PAGE->set_pagelayout('standard');
$PAGE->set_title("CPD Settings");
$PAGE->set_heading('CPD plugin settings');
$PAGE->set_url($CFG->wwwroot.'/blocks/cpd_block/metadata.php');
$PAGE->navbar->add('CPD Settings');
echo $OUTPUT->header();

global $CFG;
if (isset($errors))
{
	echo '<div class="box errorbox errorboxcontent">'. implode('<br />' , $errors) .'</div>';
}
?>
<table class="cpd_settings" cellpadding="8" border="0" />
	<tr>
		<th colspan="2">Activity Types</th>
	</tr>
	<tr>
		<td class="itemlist">
			<table class="cpd_list" cellpadding="0" cellspacing="0" border="0">
				<?php
				if ($activity_types)
				{
					foreach ($activity_types as $activity_type)
					{
				?>
				<tr>
					<td><?php echo $activity_type->name ?></td>
					<td>
						<a href="<?php echo "$CFG->wwwroot/blocks/cpd_block/metadata.php?edit=activitytype&id=$activity_type->id" ?>"><img src="<?php echo $OUTPUT->pix_url('/t/edit')?>" alt="edit" /></a>
						<a onclick="return confirm('Are you sure you want to delete?');" href="<?php echo "$CFG->wwwroot/blocks/cpd_block/metadata.php?delete=activitytype&id=$activity_type->id" ?>"><img src="<?php echo $OUTPUT->pix_url('/t/delete')?>" alt="delete" /></a>
					</td>
				</tr>
				<?php
					}
				}
				?>
			</table>
		</td>
		<td class="itemform">
			<h3><?php echo (isset($edit['activitytype'])) ? 'Update' : 'Add new' ?> type</h3>
			<form action="" method="post" name="frmactivitytype">
			<?php
				$activity_name = '';
				if (isset($edit['activitytype']))
				{
					echo '<input type="hidden" name="frmid" value="'. $edit['activitytype']->id .'" />';
					$activity_name = $edit['activitytype']->name;
				}
			?>
				<input type="hidden" name="process" value="activitytype" />
				<input type="text" value="<?php echo $activity_name ?>" name="activitytype" />
				<input type="submit" value="<?php echo (isset($edit['activitytype'])) ? 'Update' : 'Add' ?>" />
			</form>
		</td>
	</tr>
</table>
<table class="cpd_settings" cellpadding="8" border="0" />
	<tr>
		<th colspan="2">CPD Years</th>
	</tr>
	<tr>
		<td class="itemlist">
			<table class="cpd_list" cellpadding="0" cellspacing="0" border="0">
				<?php
				if ($years)
				{
					foreach ($years as $year)
					{
				?>
				<tr>
					<td><?php echo date("d/m/Y", $year->startdate) . " - " . date("d/m/Y", $year->enddate) ?></td>
					<td>
						<a href="<?php echo "$CFG->wwwroot/blocks/cpd_block/metadata.php?edit=year&id=$year->id" ?>"><img src="<?php echo $OUTPUT->pix_url('/t/edit')?>" alt="edit" /></a>
						<a onclick="return confirm('Are you sure you want to delete?');" href="<?php echo "$CFG->wwwroot/blocks/cpd_block/metadata.php?delete=year&id=$year->id" ?>"><img src="<?php echo $OUTPUT->pix_url('/t/delete')?>" alt="delete" /></a>
					</td>
				</tr>
				<?php
					}
				}
				?>
			</table>
		</td>
		<td class="itemform">
			<h3><?php echo (isset($edit['year'])) ? 'Update' : 'Add' ?> CPD years</h3>
			<form action="" method="post" name="frmcpdyears">
			<?php
				$year_startdate = null;
				$year_enddate = null;
				if (isset($edit['year']))
				{
					echo '<input type="hidden" name="frmid" value="'. $edit['year']->id .'" />';
					$year_startdate = $edit['year']->startdate;
					$year_enddate = $edit['year']->enddate;
				}
			?>
				<input type="hidden" name="process" value="cpdyears" />
				<label for="menustartday">Start:</label>
				<?php
                                
                                $dayselector = html_writer::select_time('days', 'startday', $year_startdate);
                                $monthselector = html_writer::select_time('months', 'startmonth', $year_startdate);
                                $yearselector = html_writer::select_time('years', 'startyear', $year_startdate);

                                echo $dayselector . $monthselector . $yearselector;
    
                                ?><br/>
				<label for="menuendday">End:</label>
				<?php 
                                $dayselector = html_writer::select_time('days', 'endday', $year_enddate);
                                $monthselector = html_writer::select_time('months', 'endmonth', $year_enddate);
                                $yearselector = html_writer::select_time('years', 'endyear', $year_enddate);

                                echo $dayselector . $monthselector . $yearselector;
                                
                                ?>
				<input type="submit" value="<?php echo (isset($edit['year'])) ? 'Update' : 'Add' ?>" />
			</form>
		</td>
	</tr>
</table>
<table class="cpd_settings" cellpadding="8" border="0" />
	<tr>
		<th colspan="2">Status</th>
	</tr>
	<tr>
		<td class="itemlist">
			<table class="cpd_list" cellpadding="0" cellspacing="0" border="0">
				<?php
				if ($statuses)
				{
					foreach ($statuses as $status)
					{
				?>
				<tr>
					<td><?php echo $status->name ?></td>
					<td>
						<a href="<?php echo "$CFG->wwwroot/blocks/cpd_block/metadata.php?moveup=status&id=$status->id" ?>">
							<img src="<?php echo $OUTPUT->pix_url('/t/up')?>" alt="up" /></a>
						<a href="<?php echo "$CFG->wwwroot/blocks/cpd_block/metadata.php?movedown=status&id=$status->id" ?>">
							<img src="<?php echo $OUTPUT->pix_url('/t/down')?>" alt="down" /></a>
					<?php 
						if (! in_array( strtoupper($status->name), array('OBJECTIVE MET')) )
						{
					?>
						<a href="<?php echo "$CFG->wwwroot/blocks/cpd_block/metadata.php?edit=status&id=$status->id" ?>">
									<img src="<?php echo $OUTPUT->pix_url('/t/edit')?>" alt="edit" /></a>
						<a onclick="return confirm('Are you sure you want to delete?');" href="<?php echo "$CFG->wwwroot/blocks/cpd_block/metadata.php?delete=status&id=$status->id" ?>">
									<img src="<?php echo $OUTPUT->pix_url('/t/delete')?>" alt="delete" /></a>
					<?php 
						}
					?>
					</td>
				</tr>
				<?php
					}
				}
				?>
			</table>
		</td>
		<td class="itemform">
			<h3><?php echo (isset($edit['status'])) ? 'Update' : 'Add new' ?> status</h3>
			<form action="" method="post" name="frmstatus">
			<?php
				$status_name = '';
				if (isset($edit['status']))
				{
					echo '<input type="hidden" name="frmid" value="'. $edit['status']->id .'" />';
					$status_name = $edit['status']->name;
				}
			?>
				<input type="hidden" name="process" value="status" />
				<input type="text" value="<?php echo $status_name ?>" name="status" />
				<input type="submit" value="<?php echo (isset($edit['status'])) ? 'Update' : 'Add' ?>" />
			</form>
		</td>
	</tr>
</table>
<?php

echo $OUTPUT->footer();