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
 * Renderer
 *
 *
 * @package    local_culrollover
 * @copyright  2016 Amanda Doughty
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/culrollover/classes/forms/default_options_form.php');
require_once($CFG->dirroot . '/local/culrollover/classes/forms/select_courses_form.php');
require_once($CFG->dirroot . '/local/culrollover/classes/forms/review_options_form.php');

class local_culrollover_renderer extends plugin_renderer_base {

    public function get_rollover_summaries() {
        global $DB;

        $table = '';

        // Get record set.
        if(has_capability('moodle/site:config', context_system::instance())) {
            $requests = $DB->get_records('cul_rollover', null, '');
        } else {
            $requests = $DB->get_records('cul_rollover', array('userid' => $userid), '');
        }

        if (!empty($requests)) {
            $table .= html_writer::start_tag('table', array('id' => 'previous'));
            $table .= html_writer::start_tag('thead');

            $headers = array(
                    't',
                    get_string('date', 'local_culrollover'),
                    get_string('source_header', 'local_culrollover'),
                    get_string('dest_header', 'local_culrollover'),
                    get_string('type_header', 'local_culrollover'),
                    get_string('status', 'local_culrollover'),
                    get_string('user','local_culrollover'),
                    ''
                );

            foreach ($headers as $header) {
                $table .= html_writer::tag('th', $header);
            }

            $table .= html_writer::end_tag('thead');
            $table .= html_writer::start_tag('tbody');
            $rows = array();

            foreach($requests as $request) {
                // Construct tooltip text.
                switch ($request->merge) {
                    case 0:
                        $merge = 'merge | ';
                        break;
                    case 1:
                        $merge = 'delete first | ';
                        break;
                }

                switch ($request->groups) {
                    case 0:
                        $groups = 'no groups | ';
                        break;
                    case 1:
                        $groups = 'groups | ';
                        break;
                }

                $allroles = $DB->get_records('role');
                $rolestocopy = 'roles to copy: ';
                $roles = array();            
                $enrolments = $request->enrolments;

                if(!$enrolments) {
                    $rolestocopy .= 'none | ';
                } else {
                    // loop through and get roles.
                    $enrolments = explode(',', $enrolments);

                    foreach ($enrolments as $role) {
                        $roles[] = $allroles[$role]->shortname;
                    }

                    $rolestocopy .= implode(', ', $roles) . ' | ';
                }

                switch ($request->visible) {
                    case 0:
                        $visible = 'hide | ';
                        break;
                    case 1:
                        $visible = 'make visible | ';
                        break;
                    case 2:
                        $visible = 'visible on ' . date('d/M/Y', $request->visibledate) . ' | ';
                        break;
                }

                $rolestonotify = 'roles to notify: ';
                $roles = array();
                $notifies = $request->notify;

                if (!$notifies) {
                    $rolestonotify .= 'none';
                } else {
                    // Loop through and get roles.
                    $notifies  = explode (',', $notifies);

                    foreach ($notifies as $role) {
                        $roles[] = $allroles[$role]->shortname;
                    }

                    $rolestonotify .= implode(', ', $roles);
                }

                $tooltip = 'options {' . $merge . $groups . $rolestocopy . $visible . $rolestonotify . '}';
                // end tooltip

                $cells = array();
                $cells[] = $request->schedule;
                $cells[] = date('d/M/Y', $request->schedule);

                $url = new moodle_url('/course/view.php', array('id' => $request->sourceid));
                $sourcecourse = $DB->get_record('course', array('id' => $request->sourceid));
                $link = html_writer::link($url, $sourcecourse->shortname);
                $cells[] = $link;

                $url = new moodle_url('/course/view.php', array('id' => $request->destid));
                $destcourse = $DB->get_record('course', array('id' => $request->destid));
                $link = html_writer::link($url, $destcourse->shortname);
                $cells[] = $link;

                switch ($request->type) {
                    case 0:
                        $type = get_string('copy_everything', 'local_culrollover');
                        break;
                    case 1:
                        $type = get_string('copy_content', 'local_culrollover');
                        break;
                    default:
                        $type = get_string('copy_unknown', 'local_culrollover');
                }

                $cells['type'] = $type;

                if($request->status == 'Complete') {
                    $compDate = date('d/M/Y H:i', $request->completiondate);
                } else {
                    $compDate = date('d/M/Y', $request->schedule);
                }

                $cells[] = "{$request->status} ($compDate)";

                $url = new moodle_url('/user/profile.php', array('id' => $request->userid));
                $user = $DB->get_record('user', array('id' => $request->userid));
                $cells[] = html_writer::link($url, fullname($user));

                if( has_capability('moodle/site:config', context_system::instance()) || ($request->status == 'Pending') ) {
                    $url = new moodle_url('', array('delentry' => $request->id));
                    $delete = html_writer::link(
                        $url,
                        '<i class="icon-large icon-remove"></i>',
                        array(
                            'class' => 'DeleteRow',
                            'title' => 'Remove from Queue',
                            'onclick' => "return confirm('Are you sure you want to delete this entry?')"
                            )
                        );
                } else {
                    $delete = "&nbsp;";
                }

                $cells[] = $delete;
                $rows[] = $cells;
            }        

            foreach ($rows as $cells) {
                $table .= html_writer::start_tag('tr');

                foreach ($cells as $header => $cell) {
                    if($header == 'type') {
                        $table .= html_writer::tag('td', $cell, array('title' => $tooltip));
                    } else {
                        $table .= html_writer::tag('td', $cell);
                    }
                }

                $table .= html_writer::end_tag('tr');
            }

            $table .= html_writer::end_tag('tbody');
            $table .= html_writer::end_tag('table');
        } else {
            $table .= get_string('norequests','local_culrollover');
        }

        // Add the JS to make the table sortable.
        $this->page->requires->js_call_amd('local_culrollover/rollovertable', 'initialise');
        return $table;
    }

    public function get_default_options_form() {
        $defaultoptionsform = new default_options_form();
        return $defaultoptionsform->render();
    }

    public function get_select_courses_form() {
        $defaultoptionsform = new default_options_form(null, $_POST, 'post', '', array('id' => 'default_options_form'));

        if($defaultoptionsform->is_submitted()) {
            $choices = $defaultoptionsform->get_data();
            // TODO if not valid
        } else {

        }

        if($filedata = $defaultoptionsform->get_file_content('csvfile')) {
            $choices->csv = serialize($filedata);
            $choices = (array)$choices;
            $message = '';
            $selectcoursesform = new select_courses_form(null, $choices, 'post', '', array('id' => 'select_courses_form'));
        } else {
            if(!isset($choices->roles)) {
                $choices->roles = '';
            }
            $choices = (array)$choices;
            $message = get_string('selectcoursesmessage', 'local_culrollover');
            $selectcoursesform = new select_courses_form(null, $choices, 'post', '', array('id' => 'select_courses_form'));
        }

        $form = $selectcoursesform->render();

        return array($message, $form);
    }

    public function add_courses() {
        $message = get_string('selectcoursesmessage', 'local_culrollover');        
        $selectcoursesform = new select_courses_form(null, $_POST, 'post', '', array('id' => 'select_courses_form'));
        $form = $selectcoursesform->render();

        return array($message, $form);
    }

    public function get_review_options_form() {
        $selectcoursesform = new select_courses_form(null, $_POST, 'post', '', array('id' => 'select_courses_form'));

        if($selectcoursesform->is_submitted()) {
            $choices = $selectcoursesform->get_data();
            $choices = (array)$choices;
        } else {
            // TODO
        }

        $message = get_string('reviewmessage', 'local_culrollover');       
        $reviewoptionsform = new review_options_form(null, $choices, 'post', '', array('id' => 'review_options_form'));
        $form = $reviewoptionsform->render();

        return array($message, $form);
    }

    public function process_review_options_form() {
        global $USER, $DB;

        $reviewoptionsform = new review_options_form(null, $_POST, 'post', '', array('id' => 'review_options_form'));

        if($reviewoptionsform->is_submitted()) {
            $choices = $reviewoptionsform->get_data();
        } else {
            // TODO
        }

        $i = $choices->rolloverrepeats - 1;

        while($i >= 0) {
            if(isset($choices->source[$i]) && !empty($choices->source[$i])) {
                $rollover = new stdClass();
                $rollover->sourceid = $choices->source[$i];
                $rollover->destid = str_replace('\\', '', $choices->dest[$i]);
                $rollover->userid = $USER->id;
                $rollover->datesubmitted = time();
                $rollover->status = 'Pending';
                $scheduledatelength = strlen((string)$choices->migrateondate[$i]);

                if($scheduledatelength > 10) {
                    $rollover->schedule = $choices->migrateondate[$i] / 1000;
                } else{
                    $rollover->schedule = $choices->migrateondate[$i];
                }

                $enrolments = unserialize($choices->roles[$i]); // TODO Error       

                if($enrolments) {
                    $enrolments = implode(',', $enrolments);
                } else {
                    $enrolments = '';
                }

                if(isset($choices->rolestoinform)) {
                    $notifies = implode(',', $choices->rolestoinform);
                } else {
                    $notifies = null;
                }

                $rollover->type = $choices->what[$i];
                $rollover->merge = $choices->merge[$i];
                $rollover->groups = $choices->groups[$i];
                $rollover->enrolments = $enrolments;
                $rollover->notify = $notifies;
                $rollover->visible = $choices->visible[$i];
                $visibleondatelength = strlen((string)$choices->visibleondate[$i]);

                if($visibleondatelength > 10){
                    $rollover->visibledate = $choices->visibleondate[$i] / 1000;
                } else {
                    $rollover->visibledate = $choices->visibleondate[$i];
                }

                $rollover->completiondate = '';
                $DB->insert_record('cul_rollover', $rollover);
            }

            $i--;
        }

        return true;
    }


}