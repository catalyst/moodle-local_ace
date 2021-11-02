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

declare(strict_types=1);

namespace local_ace\local\entities;

use context_system;
use html_writer;
use lang_string;
use moodle_url;
use stdClass;
use core_user\fields;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\user_profile_fields;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\base as base_report;

/**
 * User entity class implementation.
 *
 * This entity defines all the user columns and filters to be used in any report.
 *
 * @package    local_ace
 * @copyright  2021 University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment extends user {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
                'user' => 'u',
                'enrol' => 'e',
                'user_enrolments' => 'ue',
                'role' => 'r',
                'user_lastaccess' => 'ul',
                'course' => 'c',
               ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityenrolment', 'core_reportbuilder');
    }

    /**
     * Initialise the entity, add all user fields and all 'visible' user profile fields
     *
     * @return base
     */
    public function initialise(): base {

        $columns = $this->get_all_columns();

        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = $this->get_all_filters();

        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }

        // TODO: differentiate between filters and conditions (specifically the 'date' type: MDL-72662).
        $conditions = $this->get_all_filters();
        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     * 
     * Time enrolment started (user_enrolments.timestart)
     * Time enrolment ended (user_enrolments.timeend)
     * Time created (user_enrolments.timeend),
     * Enrol plugin used (mdl_enrol.enrol)
     * Role given to user (mdl_enrol.roleid - allowing for role shortname.
     * User last access (join with mdl_user_lastaccess table)
     *
     * These are all the columns available to use in any report that uses this entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {

        $usertablealias = $this->get_table_alias('user');
        $userenrolmentsalias = $this->get_table_alias('user_enrolments');
        $enrolalias = $this->get_table_alias('enrol');
        $rolealias = $this->get_table_alias('role');
        $userlastaccessalias = $this->get_table_alias('user_lastaccess');
        $coursealias = $this->get_table_alias('course');

        $join = "
                INNER JOIN {user_enrolments} {$userenrolmentsalias}
                ON {$userenrolmentsalias}.userid = {$usertablealias}.id
                INNER JOIN {enrol} {$enrolalias}
                ON {$enrolalias}.id = {$userenrolmentsalias}.enrolid
                JOIN {role} {$rolealias}
                ON {$rolealias}.id = {$enrolalias}.roleid
                JOIN {user_lastaccess} {$userlastaccessalias}
                ON {$userlastaccessalias}.userid = {$usertablealias}.id
                INNER JOIN {course} {$coursealias}
                ON {$enrolalias}.courseid = {$coursealias}.id
        ";

        // Time enrolment started (user_enrolments.timestart)
        $columns[] = (new column(
            'timestart',
            new lang_string('timestarted', 'local_ace'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_fields("$userenrolmentsalias.timestart")
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static function(?string $value): string {
                if ($value === null) {
                    return '';
                }

                return userdate($value);
            });

        // Time enrolment ended (user_enrolments.timeend)
        $columns[] = (new column(
            'timeend',
            new lang_string('timeend', 'local_ace'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_fields("$userenrolmentsalias.timeend")
            ->add_callback(static function ($value): string {
                if ($value === null) {
                    return '';
                }
                return userdate($value);
            });

        // Time created (user_enrolments.timecreated),
        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'local_ace'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_fields("$userenrolmentsalias.timecreated")
            ->add_callback(static function ($value): string {
                if ($value === null) {
                    return '';
                }
                return userdate($value);
            });

        // Enrol plugin used (mdl_enrol.enrol)
        $columns[] = (new column(
            'enrol',
            new lang_string('enrol', 'local_ace'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_fields("$enrolalias.enrol");


        // Role given to user (mdl_enrol.roleid - allowing for role shortname.
        $columns[] = (new column(
            'role',
            new lang_string('role', 'local_ace'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_fields("$rolealias.shortname");

        $columns[] = (new column(
            'lastaccessed',
            new lang_string('lastaccessed', 'local_ace'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_fields("$userlastaccessalias.timeaccess")
            ->add_callback(static function ($value): string {
                if ($value === null) {
                    return '';
                }
                return userdate($value);
            });        
    
        return $columns;
    }

    /**
     * Return list of all available filters
     * 
     * Time enrolment started (user_enrolments.timestart)
     * Time enrolment ended (user_enrolments.timeend)
     * Time created (user_enrolments.timecreated),
     * Enrol plugin used (mdl_enrol.enrol)
     * Role given to user (mdl_enrol.roleid - allowing for role shortname.
     * User last access (join with mdl_user_lastaccess table)
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $filters = [];

        $usertablealias = $this->get_table_alias('user');
        $userenrolmentsalias = $this->get_table_alias('user_enrolments');
        $enrolalias = $this->get_table_alias('enrol');
        $rolealias = $this->get_table_alias('role');
        $userlastaccessalias = $this->get_table_alias('user_lastaccess');

        $join = "
                INNER JOIN {user_enrolments} {$userenrolmentsalias}
                ON {$userenrolmentsalias}.userid = {$usertablealias}.id
                INNER JOIN {enrol} {$enrolalias}
                ON {$enrolalias}.id = {$userenrolmentsalias}.enrolid
                JOIN {role} {$rolealias}
                ON {$rolealias}.id = {$enrolalias}.roleid
                JOIN {user_lastaccess} {$userlastaccessalias}
                ON {$userlastaccessalias}.userid = {$usertablealias}.id
        ";

        // Time enrolment started (user_enrolments.timestart)
        $filters[] = (new filter(
            text::class,
            'timestart',
            new lang_string('timestarted', 'local_ace'),
            $this->get_entity_name(),
            "{$userenrolmentsalias}.timestart"
        ))
            ->add_join($join);
        }

        // Time enrolment ended (user_enrolments.timeend)
        $filters[] = (new filter(
            text::class,
            'timeend',
            new lang_string('timeended', 'local_ace'),
            $this->get_entity_name(),
            "{$userenrolmentsalias}.timeend"
        ))
            ->add_join($join);
        }

        // Time created (user_enrolments.timecreated)
        $filters[] = (new filter(
            text::class,
            'timecreated',
            new lang_string('timecreated', 'local_ace'),
            $this->get_entity_name(),
            "{$userenrolmentsalias}.timecreated"
        ))
            ->add_join($join);
        }

        // Enrol plugin used (mdl_enrol.enrol)
        $filters[] = (new filter(
            text::class,
            'enrol',
            new lang_string('enrol', 'local_ace'),
            $this->get_entity_name(),
            "{$enrolalias}.enrol"
        ))
            ->add_join($join);
        }
        // Role given to user (mdl_enrol.roleid - allowing for role shortname.
        $filters[] = (new filter(
            text::class,
            'role',
            new lang_string('role', 'local_ace'),
            $this->get_entity_name(),
            "{$rolalias}.shortname"
        ))
            ->add_join($join);
        }

        // User last access (join with mdl_user_lastaccess table)
        $filters[] = (new filter(
            text::class,
            'lastaccess',
            new lang_string('lastaccess', 'local_ace'),
            $this->get_entity_name(),
            "{$userlastaccessalias}.timeaccess"
        ))
            ->add_join($join);
        }

        return $filters;
    }
}
