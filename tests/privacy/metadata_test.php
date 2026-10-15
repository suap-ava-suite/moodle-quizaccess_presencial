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

namespace quizaccess_presencial\privacy;

use core_privacy\local\metadata\collection;

#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
/**
 * Delegation and invitation metadata without Moodle database state.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\privacy\provider
 */
final class metadata_test extends \basic_testcase {
    /**
     * The registry describes the delegation data and the invitation creator and lifecycle data.
     */
    public function test_metadata_describes_delegation_and_invitation_personal_data(): void {
        $collection = provider::get_metadata(new collection('quizaccess_presencial'));
        $items = [];
        foreach ($collection->get_collection() as $item) {
            $items[$item->get_name()] = $item;
        }

        $this->assertEqualsCanonicalizing(
            ['quizaccess_presencial_delegation', 'quizaccess_presencial_invite', 'quizaccess_presencial_req'],
            array_keys($items),
        );
        $this->assertEqualsCanonicalizing([
            'quizid', 'userid', 'timeopen', 'timeclose', 'origin', 'timecreated', 'timemodified', 'timerevoked', 'revokedby',
        ], array_keys($items['quizaccess_presencial_delegation']->get_privacy_fields()));
        $this->assertEqualsCanonicalizing([
            'createdby', 'generation', 'state', 'timecreated', 'timemodified', 'timeexpires',
        ], array_keys($items['quizaccess_presencial_invite']->get_privacy_fields()));
        $this->assertEqualsCanonicalizing([
            'quizid', 'userid', 'attemptnumber', 'state', 'timecreated', 'expiresat', 'timemodified',
        ], array_keys($items['quizaccess_presencial_req']->get_privacy_fields()));
    }
}
