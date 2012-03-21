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
 * Version details.
 *
 * @package    report
 * @subpackage forum
 * @copyright  2012 Michael de Raadt <michaeld@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_forum_extend_navigation_course($navigation, $course, $context) {
    global $CFG;

    if (has_capability('report/forumreport:view', $context)) {
        $url = new moodle_url('/report/forum/index.php', array('course'=>$course->id));
        $navigation->add(
            get_string('pluginname', 'report_forum'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Compares two users for ordering
 *
 * @param  mixed $a element containing name, post count and discussion count
 * @param  mixed $b element containing name, post count and discussion count
 * @return order of pair expressed as -1, 0, or 1
 */
function compare_users ($a, $b) {
    global $sort;

    // Process each of the one or two orders
    $orders = explode(',', $sort);
    foreach ($orders as $order) {

        // Extract the order information
        $orderelements = explode(' ', trim($order));
        $aspect = $orderelements[0];
        $ascdesc = $orderelements[1];

        // Check if order can be established
        if ($a->$aspect < $b->$aspect) {
            return $ascdesc=='ASC'?-1:1;
        }
        if ($a->$aspect > $b->$aspect) {
            return $ascdesc=='ASC'?1:-1;
        }
    }

    // If previous ordering fails, consider values equal
    return 0;
}