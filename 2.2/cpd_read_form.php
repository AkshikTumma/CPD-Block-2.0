<?php

/**
 * This page is a part of CPD Block, a CPD Report module conversion for Moodle 2.1.
 * It was done by Konstiantyn Kononenkov and sponsored by Iowa State University 
 * Child Welfare Research and Training Project.
 *
 * @package   cpd-block                                             
 * @copyright 2010 Kineo open Source                                         
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

 * Upload a file CVS file with cpd records information.
 *
 */
defined('MOODLE_INTERNAL') || die();

require_once ($CFG->libdir . '/formslib.php');

class cpd_read_form extends moodleform {

    function definition() {

        $mform = $this->_form;
        $mform->addElement('header', 'settingsheader', get_string('upload'));
        $mform->addElement('filepicker', 'cpdfile', get_string('cpdfile', 'block_cpd_block'));
        $mform->addRule('cpdfile', null, 'required');
        $mform->addHelpButton('cpdfile', 'cpdfile', 'block_cpd_block');

        $mform->addElement('hidden', 'cpdyearid', $this->_customdata['cpdyearid']);
        $mform->setType('cpdyearid', PARAM_INT);

        $mform->addElement('hidden', 'importid', $this->_customdata['importid']);
        $mform->setType('importid', PARAM_INT);

        $dchoices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'block_cpd_block'), $dchoices);
        if (array_key_exists('cfg', $dchoices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }
        $mform->addHelpButton('delimiter_name', 'csvdelimiter', 'block_cpd_block');

        $echoices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'block_cpd_block'), $echoices);
        $mform->setDefault('encoding', 'UTF-8');
        $mform->addHelpButton('encoding', 'encoding', 'block_cpd_block');

        $pchoices = array('10' => 10, '20' => 20, '100' => 100, '1000' => 1000, '100000' => 100000);
        $mform->addElement('select', 'previewrows', get_string('rowpreviewnum', 'block_cpd_block'), $pchoices);
        $mform->setType('previewrows', PARAM_INT);
        $mform->addHelpButton('previewrows', 'rowpreviewnum', 'block_cpd_block');

        $this->add_action_buttons(false, get_string('uploadcpd', 'block_cpd_block'));
    }

}

class cpd_read_form_2 extends moodleform {

    function definition() {
        $mform = & $this->_form;

        $mform->addElement('hidden', 'cpdyearid', $this->_customdata['cpdyearid']);
        $mform->setType('cpdyearid', PARAM_INT);

        $mform->addElement('hidden', 'importid', $this->_customdata['importid']);
        $mform->setType('importid', PARAM_INT);

        $mform->addElement('hidden', 'uploadFlag', 1);
        $mform->setType('uploadFlag', PARAM_INT);

        $this->add_action_buttons(true, get_string('uploadcpd', 'block_cpd_block'));
    }

}