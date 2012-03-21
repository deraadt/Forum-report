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

require('../../config.php');
require('lib.php');

// Get course and context
$courseid = required_param('course', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

// Set up page
$url = new moodle_url('/report/forum/index.php', array('course'=>$course->id));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('title', 'report_forum'));
$PAGE->set_heading($course->fullname);

// Check permissions
require_login($course);
require_capability('report/forumreport:view', $context);

// Start the page
echo $OUTPUT->header();

// Get count of user postings
$query = "SELECT p.userid, COUNT(p.id) as posts
            FROM {forum} f, {forum_discussions} d, {forum_posts} p
           WHERE f.course = :courseid
             AND f.id = d.forum
             AND p.discussion = d.id
        GROUP BY p.userid
        ORDER BY posts DESC;";
$params = array('courseid' => $courseid);
$postsummary = $DB->get_records_sql($query, $params);

// Get count of discusions created
$query = "SELECT d.userid, COUNT(d.id) as discussions
            FROM {forum} f, {forum_discussions} d
           WHERE f.course = :courseid
             AND f.id = d.forum
        GROUP BY d.userid
        ORDER BY discussions DESC;";
$discussionssummary = $DB->get_records_sql($query, $params);

// Get user information
$query = "SELECT u.id, firstname, lastname, lastaccess, picture, imagealt, email
            FROM {role_assignments} r, {user} u
           WHERE r.contextid = :contextid
             AND r.userid = u.id";
$params = array('contextid' => $context->id);
$users = $DB->get_records_sql($query, $params);

// Mash the user, discussion and post data into one array
if (!empty($users)) {
    foreach ($users as $user) {
        $user->fullname    = fullname($user);
        $user->posts       = array_key_exists($user->id, $postsummary)?$postsummary[$user->id]->posts:0;
        $user->discussions = array_key_exists($user->id, $discussionssummary)?$discussionssummary[$user->id]->discussions:0;
        $user->picture     = $OUTPUT->user_picture($user, array('course'=>$courseid));
    }
    $users = array_values($users);
}

// Create the table to be shown
require_once($CFG->libdir.'/tablelib.php');
$table = new flexible_table('report-forum-display');
$table->define_columns(array('picture', 'fullname', 'posts', 'discussions'));
$tableheaders = array(
    '',
    get_string('fullname'),
    get_string('posts', 'report_forum'),
    get_string('discussions', 'report_forum')
);
$table->define_headers($tableheaders);
$table->define_baseurl($PAGE->url);
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

// Sort the information based on the table's sort
if (!$sort = $table->get_sql_sort()) {
     $sort = 'posts DESC, lastname ASC';
     $table->set_sql_sort($sort);
}
if (!empty($users)) {
    usort($users, 'compare_users');
}

// Build the table content and output
foreach ($users as $user) {
    $table->add_data(array($user->picture, $user->fullname, $user->posts, $user->discussions));
}
$table->print_html();

// End the output
echo $OUTPUT->footer($course);
