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
 * Invitation metadata without Moodle database state.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\privacy\provider
 */
final class metadata_test extends \basic_testcase {
    /**
     * The registry describes the creator and invitation lifecycle data.
     */
    public function test_metadata_describes_invitation_personal_data(): void {
        $collection = provider::get_metadata(new collection('quizaccess_presencial'));
        $items = $collection->get_collection();

        $this->assertCount(1, $items);
        $item = reset($items);
        $this->assertSame('quizaccess_presencial_invite', $item->get_name());
        $this->assertEqualsCanonicalizing([
            'createdby', 'generation', 'state', 'timecreated', 'timemodified', 'timeexpires',
        ], array_keys($item->get_privacy_fields()));
    }
}
