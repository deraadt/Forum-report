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
 * Course completion progress report
 *
 * @package    report
 * @subpackage forum
 * @copyright  2012 Michael de Raadt <michaeld@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Required files
require('../../config.php');
require_once($CFG->libdir.'/tablelib.php');

// Get passed parameters
$courseid = required_param('course', PARAM_INT);
$forumselected = optional_param('forum', null, PARAM_INT);

// Get course and context
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

// Set up page
$urlparams = array('course'=>$course->id);
if ($forumselected) {
    $urlparams['forum'] = $forumselected;
}
$url = new moodle_url('/report/forum/index.php', $urlparams);
$title = get_string('title', 'report_forum');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_pagelayout('report');
$PAGE->set_heading($course->fullname);

// Check permissions
require_login($course);
require_capability('report/forum:view', $context);

// Start the page
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

// Output the list of forums
$query = "SELECT id, name
            FROM {forum}
           WHERE course = :courseid";
$params = array('courseid' => $courseid);
$forums = $DB->get_records_sql($query, $params);
$selectoptions = array(0 => get_string('allforums', 'report_forum'));
foreach ($forums as $forum) {
    $selectoptions[$forum->id] = $forum->name;
}
echo HTML_WRITER::start_tag('div', array('class' => 'report_forum_selector'));
echo get_string('selectforum', 'report_forum').': ';
echo $OUTPUT->single_select($url, 'forum', $selectoptions, $forumselected, null, 'forumform');
echo $OUTPUT->help_icon('selectingaforum', 'report_forum');
echo HTML_WRITER::end_tag('div');

// Create the table to be shown
require_once($CFG->libdir.'/tablelib.php');
$table = new flexible_table('report-forum-display');
$table->define_columns(array('picture', 'fullname', 'posts', 'discussions', 'readcount'));
$tableheaders = array(
    '',
    get_string('fullname'),
    get_string('posts', 'report_forum'),
    get_string('discussions', 'report_forum'),
    get_string('readcount', 'report_forum')
);
$table->define_headers($tableheaders);
$table->define_baseurl($url);
$table->sortable(true);
$table->collapsible(false);
$table->initialbars(false);
$table->column_suppress('picture');
$table->column_suppress('fullname');
$table->set_attribute('cellspacing', '0');
$table->set_attribute('align', 'center');
$table->column_style_all('text-align', 'center');
$table->column_style('fullname', 'text-align', 'left');
$table->column_style_all('vertical-align', 'middle');
$table->no_sorting('picture');
$table->setup();

// Apply a forum restriction if supplied
$tablewhere = '';
$cmidwhere  = '';
if ($forumselected > 0) {
    $tablewhere = 'AND d.forum = '.$forumselected;
    $coursemodule = get_coursemodule_from_instance('forum', $forumselected, $courseid);
    $cmidwhere  = 'AND l.cmid = '.$coursemodule->id;
    $url->param('forum', $forumselected);
}

// Get user information and forum involvment
// TO-DO test if this scales, otherwise split into separate queries and merge in PHP
if (!$sort = $table->get_sql_sort()) {
     $sort = 'posts ASC, lastname DESC';
}
$query = "SELECT u.id, firstname, lastname, lastaccess, picture, imagealt, email,
                 (
                     SELECT COUNT(*)
                       FROM {forum_discussions} d, {forum_posts} p
                      WHERE d.course = :courseid1
                        $tablewhere
                        AND d.id = p.discussion
                        AND p.userid = u.id
                 ) AS posts,
                 (
                     SELECT COUNT(*)
                       FROM {forum_discussions} d
                      WHERE d.course = :courseid2
                        $tablewhere
                        AND d.userid = u.id
                 ) AS discussions,
                 (
                     SELECT COUNT(*)
                       FROM {log} l
                      WHERE l.module = 'forum'
                        $cmidwhere
                        AND l.course = :courseid3
                        AND l.userid = u.id
                 ) AS readcount
            FROM {role_assignments} r, {user} u
           WHERE r.contextid = :contextid
             AND r.userid = u.id
        ORDER BY $sort";
$params = array('courseid1' => $courseid, 'courseid2' => $courseid, 'courseid3' => $courseid, 'contextid' => $context->id);
$users = $DB->get_records_sql($query, $params);

// Mash the user, discussion and post data into one array
if (!empty($users)) {
    foreach ($users as $user) {
        $user->fullname    = fullname($user);
        $user->picture     = $OUTPUT->user_picture($user, array('course'=>$courseid));
    }
    $users = array_values($users);
}

// Build the table content and output
foreach ($users as $user) {
    $table->add_data(array($user->picture, $user->fullname, $user->posts, $user->discussions, $user->readcount));
}
$table->print_html();

// End the output
echo $OUTPUT->footer($course);
