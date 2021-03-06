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
 * This script displays a particular page of a quiz attempt that is in progress.
 *
 * @package   mod_extendedquiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/extendedquiz/locallib.php');

// Look for old-style URLs, such as may be in the logs, and redirect them to startattemtp.php.
if ($id = optional_param('id', 0, PARAM_INT)) {
    redirect($CFG->wwwroot . '/mod/extendedquiz/startattempt.php?cmid=' . $id . '&sesskey=' . sesskey());
} else if ($qid = optional_param('q', 0, PARAM_INT)) {
    if (!$cm = get_coursemodule_from_instance('extendedquiz', $qid)) {
        print_error('invalidquizid', 'extendedquiz');
    }
    redirect(new moodle_url('/mod/extendedquiz/startattempt.php',
            array('cmid' => $cm->id, 'sesskey' => sesskey())));
}

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$attemptobj = extendedquiz_attempt::create($attemptid);
$page = $attemptobj->force_page_number_into_range($page);
$PAGE->set_url($attemptobj->attempt_url(null, $page));

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    if ($attemptobj->has_capability('mod/extendedquiz:viewreports')) {
        redirect($attemptobj->review_url(null, $page));
    } else {
        throw new moodle_extendedquiz_exception($attemptobj->get_quizobj(), 'notyourattempt');
    }
}

// Check capabilities and block settings.
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/extendedquiz:attempt');
    if (empty($attemptobj->get_quiz()->showblocks)) {
        $PAGE->blocks->show_only_fake_blocks();
    }

} else {
    navigation_node::override_active_url($attemptobj->start_attempt_url());
}

// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
    redirect($attemptobj->review_url(null, $page));
} else if ($attemptobj->get_state() == extendedquiz_attempt::OVERDUE) {
    redirect($attemptobj->summary_url());
}

// Check the access rules.
$accessmanager = $attemptobj->get_access_manager(time());
$accessmanager->setup_attempt_page($PAGE);
$output = $PAGE->get_renderer('mod_extendedquiz');
$messages = $accessmanager->prevent_access();
if (!$attemptobj->is_preview_user() && $messages) {
    print_error('attempterror', 'extendedquiz', $attemptobj->view_url(),
            $output->access_messages($messages));
}
if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
    redirect($attemptobj->start_attempt_url(null, $page));
}

add_to_log($attemptobj->get_courseid(), 'extendedquiz', 'continue attempt',
        'review.php?attempt=' . $attemptobj->get_attemptid(),
        $attemptobj->get_quizid(), $attemptobj->get_cmid());

// Get the list of questions (id's) needed by this page.
$slots = $attemptobj->get_slots();
//debugging("slot of this page: ".print_r($slots));
//debugging("all slots: ".print_r($attemptobj->get_slots()));

// Check.
if (empty($slots)) {
    throw new moodle_extendedquiz_exception($attemptobj->get_quizobj(), 'noquestionsfound');
}

// Update attempt page.
if ($attemptobj->get_currentpage() != $page) {
    if ($attemptobj->get_navigation_method() == EXTENDEDQUIZ_NAVMETHOD_SEQ && $attemptobj->get_currentpage() > $page) {
        // Prevent out of sequence access.
        redirect($attemptobj->start_attempt_url(null, $attemptobj->get_currentpage()));
    }
    $DB->set_field('extendedquiz_attempts', 'currentpage', $page, array('id' => $attemptid));
}

// Initialise the JavaScript.
$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->requires->js_init_call('M.mod_extendedquiz.init_attempt_form', null, false, extendedquiz_get_js_module());

// Arrange for the navigation to be displayed in the first region on the page.
$navbc = $attemptobj->get_navigation_panel($output, 'extendedquiz_attempt_nav_panel', $page, true);
$regions = $PAGE->blocks->get_regions();
$PAGE->blocks->add_fake_block($navbc, reset($regions));

$title = get_string('attempt', 'quiz', $attemptobj->get_attempt_number());
$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->set_title(format_string($attemptobj->get_quiz_name()));
$PAGE->set_heading($attemptobj->get_course()->fullname);

if ($attemptobj->is_last_page($page)) {
    $nextpage = -1;
} else {
    $nextpage = $page + 1;
}
//Extendedquiz mod 
$attemptobj->assign_random_values();
//Extendedquiz mod end
//aixo ho haure de fer dins d attempt_page del render

//debugging('Attempt_page output method : page='.$page.'slots'.print_r($slots).' id='.$id.' nextpage = '.$nextpage);
//echo $output->review_page($attemptobj, $slots, $page, $showall, $lastpage, $options, $summarydata, $attemptobj->get_questiontext());

echo $output->attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id, $nextpage, $attemptobj->get_questiontext());
