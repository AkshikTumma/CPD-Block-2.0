<?php

// This file is part of CPD Report for Moodle
//
// CPD Report for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CPD Report for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CPD Report for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
 * This page is a part of CPD Block, a CPD Report module conversion for Moodle 2.1.
 * It was done by Konstiantyn Kononenkov and sponsored by Iowa State University 
 * Child Welfare Research and Training Project.
 * 
 * @package   cpd-block
 * @copyright 2011 Child Welfare Research and Training Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

class block_cpd_block extends block_base
{
    public function init()
    {
       $this->title = get_string('cpd_block', 'block_cpd_block');
    }
    
    /*
     * Creating the content of the block depending on the permissions of
     * the user (whether admin permissions are set or not)
     */
    public function get_content()
    {
        global $CFG, $USER;
        
        if( $this->content !== NULL )
        {
            return $this->content;
        }
        
        $this->content = new stdClass;
        $context = get_context_instance(CONTEXT_USER, $USER->id, IGNORE_MISSING); //context_user::instance($USER->id, IGNORE_MISSING); 
        
        if( $context )
        {
            if(has_capability('report/cpd:userview', $context))
            {
                $this->content->text .= '<table><tr><td><a href=\''.$CFG->wwwroot.'/blocks/cpd_block/index.php\'>'.get_string('enter_view_report','block_cpd_block').'</a></td></tr>';
                if(has_capability('report/cpd:adminview', $context))
                {
                    $this->content->text .= '<tr><td><a href=\''.$CFG->wwwroot.'/blocks/cpd_block/adminview.php\'>'.get_string('cpd_reports','block_cpd_block').'</a></td></tr>';

                    if(has_capability('report/cpd:superadminview', $context))
                    {
                        $this->content->text .= '<tr><td><a href=\''.$CFG->wwwroot.'/blocks/cpd_block/metadata.php\'>'.get_string('settings_str','block_cpd_block').'</a></td></tr></table>';
                    }
                    else
                    {
                        $this->content->text .= '</table>';
                    }
                }
                else
                {
                    $this->content->text .= '</table>';
                }

                $this->content->footer = '';//No info in footer
            }
        }
        
        return $this->content;
    }
    
    /*
     * Add css class attribute to block members, in case that appearance 
     * will need to be changed
     */
    public function html_attiributes()
    {
        $attributes = parent::html_attributes();
        $attributes['class'] .= ' block_' . $this->name();
        
        return $attributes;
    }
    
    /*
     * The block is currently available only at the frontpage of
     * the website
     */
    public function applicable_formats()
    {
        return array(
            'site' => true,
        );
    }
    
    public function instance_allow_config()
    {
        return false;
    }
    
    /*
     * Allow the configuration of the block; make the settings page appear in 
     * admin tree
     */
    public function has_config()
    {
        return true;
    }
}
