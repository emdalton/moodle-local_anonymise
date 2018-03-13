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
 * Pseudonymise personal identifiers
 *
 * @package    local_pseudonymise
 * @copyright  2016 Gavin Henrick
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/pseudonymise/locallib.php');

$pseudonymise = optional_param('action',  false,  PARAM_BOOL);

// Allow more time for long query runs.
set_time_limit(0);

// Start page output.
admin_externalpage_setup('local_pseudonymise');
$PAGE->set_url($CFG->wwwroot . '/local/pseudonymise/index.php');
$title = get_string('pluginname', 'local_pseudonymise');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

if (!debugging() || empty($CFG->maintenance_enabled)) {
    $debugging = new moodle_url('/admin/settings.php', array('section' => 'debugging'));
    $maintenance = new moodle_url('/admin/settings.php', array('section' => 'maintenancemode'));
    $langparams = (object)array('debugging' => $debugging->out(false), 'maintenance' => $maintenance->out(false));
    echo $OUTPUT->notification(get_string('nodebuggingmaintenancemode', 'local_pseudonymise', $langparams));
    echo $OUTPUT->footer();
    die();
}

if ($pseudonymise) {

    require_sesskey();

    // Exectute pseudonymisation based on form selections.
    $activities = optional_param('activities',  false,  PARAM_BOOL);
    $categories = optional_param('categories',  false,  PARAM_BOOL);
    $courses = optional_param('courses',  false,  PARAM_BOOL);
    $files = optional_param('files', false, PARAM_BOOL);
    $users = optional_param('users',  false,  PARAM_BOOL);
    $password = optional_param('password',  false,  PARAM_BOOL);
    $admin = optional_param('admin',  false,  PARAM_BOOL);
    $site = optional_param('site',  false,  PARAM_BOOL);
    $others = optional_param('others', false, PARAM_BOOL);

    if ($activities) {
        echo $OUTPUT->heading(get_string('activities', 'local_pseudonymise'), 3);
        pseudonymise_activities();
    }

    if ($categories) {
        echo $OUTPUT->heading(get_string('categories', 'local_pseudonymise'), 3);
        pseudonymise_categories();
    }

    if ($courses) {
        echo $OUTPUT->heading(get_string('courses', 'local_pseudonymise'), 3);
        pseudonymise_courses($site);
    }

    if ($files) {
        echo $OUTPUT->heading(get_string('files', 'local_pseudonymise'), 3);
        pseudonymise_files();
    }

    if ($users) {
        echo $OUTPUT->heading(get_string('users', 'local_pseudonymise'), 3);
        pseudonymise_users($password, $admin);
    }

    if ($others) {
        echo $OUTPUT->heading(get_string('others', 'local_pseudonymise'), 3);
        pseudonymise_others($activities, $password);
    }

    echo html_writer::tag('p', get_string('done', 'local_pseudonymise'), array('style' => 'margin-top: 20px;'));
    $purgeprompt = get_string('purgeprompt', 'local_pseudonymise');
    $purgeprompt .= ' ';
    $params = array('sesskey' => sesskey(), 'confirm' => '1', 'returnurl' => '/');
    $url = new moodle_url('/admin/purgecaches.php', $params);
    $purgeprompt .= html_writer::link($url, get_string('purgelink', 'local_pseudonymise'));
    $purgeprompt .= '.';
    echo html_writer::tag('p', $purgeprompt, array('style' => 'margin-top: 20px;'));

} else {

    // Display the form.
    echo $OUTPUT->notification(get_string('warning', 'local_pseudonymise'));
    $mform = new local_pseudonymise_form(new moodle_url('/local/pseudonymise/'));
    $mform->display();

}

echo $OUTPUT->footer();
