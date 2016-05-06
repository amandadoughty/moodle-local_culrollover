<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Step 2: select_courses_form
 *
 *
 * @package    local_culrollover
 * @copyright  2016 Amanda Doughty
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/local/culrollover/constants.php');

class select_courses_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $PAGE;

        $mform =& $this->_form;
        $mform->addElement('header', 'selectcourses', get_string('selectcourses_header', 'local_culrollover'));
        $mform->setExpanded('selectcourses', true);
        $courses = 1;

        if($CFG->src_filter_regex_term) {
            $srcfilter = $CFG->src_filter_regex_term;            
        } else {
            $srcfilter = $CFG->src_filter_term;            
        }        

        if($CFG->dest_filter_regex_term) {
            $destfilter = $CFG->dest_filter_regex_term;            
        } else {
            $destfilter = $CFG->dest_filter_term;            
        }
       
        $usercourses = get_user_capability_course('moodle/course:update', NULL, true, 'id, shortname, fullname');
        $srccourses = array();
        $destcourses = array();

        foreach($usercourses as $course) {
            if(preg_match($srcfilter, $course->shortname)) {
                $srccourses[] = $course;
            }

            if(preg_match($destfilter, $course->shortname)) {
                $destcourses[] = $course;
            }
        }

        $emptyoption = array('' => '');
        $src_options = array();
        $dst_options = array();

        if(!empty($srccourses) && !empty($destcourses)) {
            foreach($srccourses as $srccourse) {
                $src_options[$srccourse->id] = addslashes($srccourse->shortname);
            }

            $src_options = $emptyoption + $src_options;

            foreach($destcourses as $dstcourse) {
                $dst_options[$dstcourse->id]['text'] = addslashes($dstcourse->shortname);
                $dst_options[$dstcourse->id]['value'] = $dstcourse->id;

                // Check if pending already and disable if so.
                if ($this->check_rollover_pending($dstcourse->id)) {                    
                    $dst_options[$dstcourse->id]['attributes'] = 'disabled';
                } else {
                    $dst_options[$dstcourse->id]['attributes'] = '';
                }
            }
        } else {
            throw new moodle_exception('nocoursestomigrate', 'local_culrollover', '/local/culrollover');
        }

        $defaultroles = isset($this->_customdata['defaultroles'])? $this->_customdata['defaultroles'] : NULL;

        if(!empty($this->_customdata['csv'])) {
            $csvdata = unserialize(trim($this->_customdata['csv']));
            $rows = explode(PHP_EOL, $csvdata);
            $i = 0;

            foreach($rows as $row) {
                if(!empty($row)) {
                    $crs = str_getcsv($row);
                    $mform->addElement('hidden', "source[$i]", $this->get_course_id_from_shortname($crs[ARRAY_SOURCE_LOCATION], ARRAY_SOURCE_LOCATION));
                    $mform->setType("source[$i]", PARAM_INT);
                    $mform->addElement('static', "sourcename[$i]", '', $crs[ARRAY_SOURCE_LOCATION]);
                    $mform->addElement('hidden', "dest[$i]", $this->get_course_id_from_shortname($crs[ARRAY_DEST_LOCATION], ARRAY_DEST_LOCATION));
                    $mform->setType("dest[$i]", PARAM_INT);
                    $mform->addElement('static', "destname[$i]", '', $crs[ARRAY_DEST_LOCATION]);
                    $i++;
                }

                $mform->addElement('hidden', 'rolloverrepeats', $i);
                $mform->setType('rolloverrepeats', PARAM_INT);
            }

            $i--;
        } else {
            $repeatarray = array();

            $attributes = array(
                'placeholder' => get_string('source_courses','local_culrollover'),
                'class' => 'source_select'
                );

            $label = get_string('selectsourcecourses', 'local_culrollover');
            $repeatarray[] = $mform->createElement('select', 'source', $label, $src_options, $attributes);

            $attributes = array(
                'placeholder' => get_string('destination_courses','local_culrollover'),
                'class' => 'dest_select'
                );

            $label = get_string('selectdestinationcourses', 'local_culrollover');
            $dstselect = $mform->createElement('select', 'dest', $label, array(), $attributes);
            $dstselect->addOption('', '');

            foreach($dst_options as $option){
                // Need to use addOption to be able to include option attributes.
                $dstselect->addOption($option['text'], $option['value'], $option['attributes']);
            }

            $repeatarray[] = $dstselect;

            $element = $mform->createElement('hidden', 'when', $this->_customdata['defaultwhen']);
            $mform->setType('when', PARAM_INT);
            $repeatarray[] = $element;

            $element = $mform->createElement('hidden', 'migrateondate', $this->_customdata['defaultmigrateondate']);
            $mform->setType('migrateondate', PARAM_INT);
            $repeatarray[] = $element;            

            $element = $mform->createElement('hidden', 'what', $this->_customdata['defaultwhat']);
            $mform->setType('what', PARAM_INT);
            $repeatarray[] = $element;

            $element = $mform->createElement('hidden', 'merge', $this->_customdata['defaultmerge']);
            $mform->setType('merge', PARAM_INT);
            $repeatarray[] = $element;

            $element = $mform->createElement('hidden', 'groups', $this->_customdata['defaultgroups']);
            $mform->setType('groups', PARAM_INT);
            $repeatarray[] = $element;

            $element = $mform->createElement('hidden', 'roles', serialize($defaultroles));
            $mform->setType('roles', PARAM_TEXT);
            $repeatarray[] = $element;

            $element = $mform->createElement('hidden', 'visible', $this->_customdata['defaultvisible']);
            $mform->setType('visible', PARAM_INT);
            $repeatarray[] = $element;

            $element = $mform->createElement('hidden', 'visibleondate', $this->_customdata['defaultvisibleondate']);
            $mform->setType('visibleondate', PARAM_INT);
            $repeatarray[] = $element;

            $repeateloptions = array();

            $this->repeat_elements(
                $repeatarray, 
                1,
                $repeateloptions, 
                'rolloverrepeats', 
                'rolloveraddfields', 
                1, 
                get_string('addmore', 'local_culrollover'), 
                true
                );

            // Make the first rollover required.
            if ($mform->elementExists('rollover[0]')) {
                $mform->addRule('rollover[0]', get_string('atleastonerollover', 'local_culrollover'), 'required', null, 'client');
            }
        }

        // Add the JS to make the select inputs dynamic.
        $i = 0;

        if(isset($this->_customdata['rolloverrepeats'])) {
            $i = $this->_customdata['rolloverrepeats'];
        }

        while($i >= 0) {
            $PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', $params = array("#id_source_$i", false, false,
            'Select Modules'));
            $PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', $params = array("#id_dest_$i", false, false,
            'Select Modules'));
            $i--;
        }
        
        // Include all the default values in case the form is being submitted to add more courses.
        $mform->addElement('hidden', 'defaultwhen', $this->_customdata['defaultwhen']);
        $mform->setType('defaultwhen', PARAM_INT);
        $mform->addElement('hidden', 'defaultmigrateondate', $this->_customdata['defaultmigrateondate']);
        $mform->setType('defaultmigrateondate', PARAM_INT);
        $mform->addElement('hidden', 'defaultwhat', $this->_customdata['defaultwhat']);
        $mform->setType('defaultwhat', PARAM_INT);
        $mform->addElement('hidden', 'defaultmerge', $this->_customdata['defaultmerge']);
        $mform->setType('defaultmerge', PARAM_INT);
        $mform->addElement('hidden', 'defaultgroups', $this->_customdata['defaultgroups']);
        $mform->setType('defaultgroups', PARAM_INT);
        $mform->addElement('hidden', 'defaultroles', serialize($defaultroles));
        $mform->setType('defaultroles', PARAM_TEXT);
        $mform->addElement('hidden', 'defaultvisible', $this->_customdata['defaultvisible']);
        $mform->setType('defaultvisible', PARAM_INT);
        $mform->addElement('hidden', 'defaultvisibleondate', $this->_customdata['defaultvisibleondate']);
        $mform->setType('defaultvisibleondate', PARAM_INT);

        $mform->addElement('hidden','courses', $courses);
        $mform->setType('courses', PARAM_INT);

        // Add Step check.
        $mform->addElement('hidden','step', MIGRATION_COURSE_CHOICES);
        $mform->setType('step', PARAM_INT);

        // Buttons for activity.
        $this->add_action_buttons(true, get_string('review','local_culrollover'));




    }

    /**
     * An approximate helper - get the course id from the course short name (course short names might not be unique)
     * @param $shortname
     * @param $location
     * @return mixed
     * @throws moodle_exception
     */
    public function get_course_id_from_shortname($shortname, $location) {
        global $DB, $CFG, $ROLLOVERID;

        $db_check = $DB->record_exists('course', array('shortname' => trim($shortname)));

        if($db_check) {
            $data =  $DB->get_record('course', array('shortname' => trim($shortname)), 'id', MUST_EXIST);
            return $data->id;
        } else {
            throw new moodle_exception('invalidcourseshortname', 'local_culrollover', '/local/culrollover', '', $shortname);
        }
    }

    public function check_rollover_pending($destid) {
        global $DB;

        $db_check = $DB->record_exists('cul_rollover', array('status' => 'Pending', 'destid' => $destid));
        return $db_check; 
    }
}
