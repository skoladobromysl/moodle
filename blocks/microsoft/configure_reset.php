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
 * This page allow authorised users to configure course reset actions on connected Team/group.
 *
 * @package block_microsoft
 * @author Lai Wei <lai.wei@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2021 onwards Microsoft, Inc. (http://microsoft.com/)
 */

use local_o365\feature\usergroups\utils;

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot . '/local/o365/lib.php');
require_once($CFG->dirroot . '/blocks/microsoft/lib.php');
require_once($CFG->dirroot . '/blocks/microsoft/forms.php');

$courseid = required_param('course', PARAM_INT);
$coursecontext = context_course::instance($courseid);

require_login($courseid);

require_capability('moodle/course:reset', $coursecontext);

$PAGE->set_context($coursecontext);

$redirecturl = new moodle_url('/course/view.php', ['id' => $courseid]);

// Validations.
// Part 1, site course sync settings.
if (!utils::is_enabled()) {
    print_error('error_site_course_sync_disabled', 'block_microsoft', $redirecturl);
}

// Part 2, course sync enabled.
$connectedtoteam = false;
if (utils::course_is_group_enabled($courseid)) {
    // Check if course customisation is allowed.
    $siteresetsetting = get_config('local_o365', 'course_reset_teams');
    if ($siteresetsetting != TEAMS_GROUP_COURSE_RESET_SITE_SETTING_PER_COURSE) {
        print_error('error_reset_setting_not_managed_per_course', 'block_microsoft', $redirecturl);
    }
    if (utils::course_is_group_feature_enabled($courseid, 'team')) {
        // The course is configured to be synced to Team.
        if (!$o365object = $DB->get_record('local_o365_objects',
            ['type' => 'group', 'subtype' => 'courseteam', 'moodleid' => $courseid])) {
            print_error('error_connected_team_missing', 'block_microsoft', $redirecturl);
        }
        $connectedtoteam = true;
    } else {
        // The course is configured to be synced to group.
        if (!$o365object = $DB->get_record('local_o365_objects',
            ['type' => 'group', 'subtype' => 'course', 'moodleid' => $courseid])) {
            print_error('error_connected_group_missing', 'block_microsoft', $redirecturl);
        }
    }
} else {
    // Sync is disabled for the course.
    print_error('error_course_sync_disabled', 'block_microsoft', $redirecturl);
}

$formdata = ['course' => $courseid];
$existingcourseresetsetting = block_microsoft_get_course_reset_setting($courseid);
if ($existingcourseresetsetting) {
    $formdata['reset_setting'] = $existingcourseresetsetting;
}
if ($connectedtoteam) {
    $mform = new block_microsoft_course_configure_team_form();
    $mform->set_data($formdata);
} else {
    $mform = new block_microsoft_course_configure_group_form();
    $mform->set_data($formdata);
}

if ($mform->is_cancelled()) {
    redirect($redirecturl);
} else if ($fromform = $mform->get_data()) {
    block_microsoft_set_course_reset_setting($fromform->course, $fromform->reset_setting);

    redirect($redirecturl, get_string('reset_setting_saved', 'block_microsoft'));
}

$pagetitle = get_string('configure_course_reset', 'block_microsoft');
$PAGE->set_url('/blocks/microsoft/configure_reset.php', ['course' => $courseid]);
$PAGE->navbar->add($pagetitle);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

echo $OUTPUT->header();

if ($connectedtoteam) {
    echo $OUTPUT->heading(get_string('reset_page_heading_team', 'block_microsoft', $COURSE->fullname));
} else {
    echo $OUTPUT->heading(get_string('reset_page_heading_group', 'block_microsoft', $COURSE->fullname));
}

$mform->display();

echo $OUTPUT->footer();
