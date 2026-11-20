<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Web service functions used by the Ask Tut service.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Ask Tut web service: the "Ask Tut" external service holds exactly the functions Tut may call.
// Tut calls them with the signed-in user's own token, so Moodle checks that user's roles and capabilities on every call.
defined('MOODLE_INTERNAL') || die();
$ownfunctions = [
    'local_asktut_me' => ['description' => 'Who the Tut user is (roles, Tut access)', 'type' => 'read'],
    'local_asktut_policy' => ['description' => 'The Tut admin rules (Tut Admin)', 'type' => 'read'],
    'local_asktut_spend' => ['description' => 'This month\'s Tut spend (user and site)', 'type' => 'read'],
    'local_asktut_log_usage' => ['description' => 'Record one Tut request (usage and cost)', 'type' => 'write'],
    'local_asktut_log_change' => ['description' => 'Record one change Tut made after approval', 'type' => 'write'],
    'local_asktut_claim' => ['description' => 'Claim a single-use approval card', 'type' => 'write'],
    'local_asktut_feedback' => ['description' => 'Thumbs up/down on a Tut answer', 'type' => 'write'],
    'local_asktut_budget_check' => ['description' => 'Budget alerts in the Moodle bell (the plugin decides; once per level per month)', 'type' => 'write'],
];
$functions = [];
foreach ($ownfunctions as $name => $f) {
    $functions[$name] = [
        'classname' => '\\local_asktut\\external\\' . substr($name, strlen('local_asktut_')),
        'description' => $f['description'],
        'type' => $f['type'],
        'ajax' => false,
        'loginrequired' => true,
    ];
}
$services = [
    'Ask Tut' => [
        'functions' => array_merge(array_keys($ownfunctions), [
            // read
            'core_webservice_get_site_info',
            'core_enrol_get_users_courses',
            'core_course_get_enrolled_courses_by_timeline_classification',
            'core_course_get_recent_courses',
            'core_course_search_courses',
            'core_course_get_courses_by_field',
            'core_course_get_contents',
            'core_course_get_categories',
            'core_enrol_get_enrolled_users',
            'core_user_get_users_by_field',
            'mod_assign_get_assignments',
            'mod_assign_list_participants',
            'mod_assign_get_submission_status',
            'gradereport_user_get_grade_items',
            'gradereport_overview_get_course_grades',
            'mod_forum_get_forums_by_courses',
            'mod_forum_get_forum_discussions',
            'mod_forum_get_discussion_posts',
            'core_calendar_get_action_events_by_timesort',
            'core_calendar_get_calendar_events',
            'core_completion_get_activities_completion_status',
            // change (only after the user approves; only where Tut Admin allows)
            'core_calendar_create_calendar_events',
            'mod_assign_save_grade',
            'mod_forum_add_discussion',
            'mod_forum_add_discussion_post',
        ]),
        'shortname' => 'local_asktut',
        'restrictedusers' => 0,
        'enabled' => 1,
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
