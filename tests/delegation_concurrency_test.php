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

namespace quizaccess_presencial;

use quizaccess_presencial\local\delegation_manager;
use quizaccess_presencial\local\invitation;

/**
 * Concurrent invitation acceptance and direct inclusion through independent connections.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\local\delegation_manager
 * @covers     \quizaccess_presencial\local\invitation
 */
final class delegation_concurrency_test extends \advanced_testcase {
    /**
     * Both grant paths must wait for the outer commit and preserve one delegation's lifecycle.
     *
     * @dataProvider outer_operation_provider
     * @param string $outeroperation Operation performed inside the parent's outer transaction.
     */
    public function test_concurrent_grants_preserve_one_delegation(string $outeroperation): void {
        global $CFG, $DB, $USER;

        $this->resetAfterTest();
        // Independent connections need committed fixtures and cannot participate in the reset transaction.
        $this->preventResetByRollback();
        $this->setAdminUser();
        $teacherid = (int) $USER->id;
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timeopen' => time() - HOURSECS,
            'timeclose' => time() + (2 * HOURSECS),
        ]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = time() - HOURSECS;
        $quiz->presencial_timeclose = time() + HOURSECS;
        \quizaccess_presencial::save_settings($quiz);
        $applicator = self::getDataGenerator()->create_user();
        $issued = invitation::generate($quiz->cmid, 0);

        [$process, $pipes] = $this->start_worker('delegation_worker.php');
        $transaction = null;
        try {
            stream_set_timeout($pipes[1], 30);
            stream_set_timeout($pipes[2], 30);
            $this->assertSame("READY\n", fgets($pipes[1]));
            $transaction = $DB->start_delegated_transaction();
            if ($outeroperation === 'accept') {
                $this->setUser($applicator);
                $accepted = invitation::accept($quiz->cmid, $issued['token']);
                $first = $accepted['delegation'];
                $this->assertTrue($accepted['created']);
            } else {
                $included = delegation_manager::include_users($quiz->id, [$applicator->id], $teacherid);
                $first = $included[$applicator->id];
            }
            $childoperation = $outeroperation === 'accept' ? 'include' : 'accept';
            fwrite($pipes[0], json_encode([
                'operation' => $childoperation,
                'quizid' => (int) $quiz->id,
                'cmid' => (int) $quiz->cmid,
                'userid' => (int) $applicator->id,
                'actorid' => $childoperation === 'accept' ? (int) $applicator->id : $teacherid,
                'token' => $issued['token'],
            ], JSON_THROW_ON_ERROR) . "\n");
            fflush($pipes[0]);
            $this->assertSame("ATTEMPTING\n", fgets($pipes[1]));
            $read = [$pipes[1]];
            $write = $except = [];
            $completedearly = stream_select($read, $write, $except, 1);

            // Re-enter both public grant paths on the owning connection while the other connection waits.
            $this->setAdminUser();
            $nested = delegation_manager::include_users($quiz->id, [$applicator->id], $teacherid);
            $this->assertEquals($first, $nested[$applicator->id]);
            $this->setUser($applicator);
            $again = invitation::accept($quiz->cmid, $issued['token']);
            $this->assertFalse($again['created']);
            $this->assertEquals($first, $again['delegation']);
            $transaction->allow_commit();
            $transaction = null;

            $line = fgets($pipes[1]);
            $this->assertNotFalse($line, 'The concurrent grant did not finish within 30 seconds.');
            $second = json_decode($line, false, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('', stream_get_contents($pipes[2]));
            $this->assertEquals($first, $second, 'Both grant paths must return the same delegation.');
            $this->assertSame(0, $completedearly, 'The concurrent grant must wait for the outer commit.');
            $this->setAdminUser();
            $current = delegation_manager::list_current($quiz->id);
            $this->assertCount(1, $current);
            $this->assertEquals($first, reset($current));

            $this->setUser($applicator);
            $again = invitation::accept($quiz->cmid, $issued['token']);
            $this->assertFalse($again['created']);
            $this->assertEquals($first, $again['delegation']);
            $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $applicator->id));
            $this->setAdminUser();
            $this->assertTrue(delegation_manager::revoke($quiz->id, $first->id, $teacherid));
            $this->assertFalse(delegation_manager::is_active_applicator($quiz->id, $applicator->id));
            $this->assertEmpty(delegation_manager::list_current($quiz->id));

            $this->setUser($applicator);
            $replacement = invitation::accept($quiz->cmid, $issued['token']);
            $this->assertTrue($replacement['created']);
            $this->assertNotEquals($first->id, $replacement['delegation']->id);
            $this->setAdminUser();
            // The old delegation still exists and stays revoked after the new grant.
            $this->assertFalse(delegation_manager::revoke($quiz->id, $first->id, $teacherid));
            $current = delegation_manager::list_current($quiz->id);
            $this->assertCount(1, $current);
            $this->assertEquals($replacement['delegation'], reset($current));
            $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $applicator->id));
        } catch (\Throwable $exception) {
            if ($transaction !== null) {
                $transaction->rollback($exception);
            }
            throw $exception;
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }

    /**
     * A direct inclusion must use the actor's authority after waiting for the quiz lock.
     *
     * @dataProvider delayed_inclusion_provider
     * @param bool $revoke Whether to revoke the teacher's role during the wait.
     * @param bool $othersession Whether the actor differs from the session user.
     * @param bool $switched Whether an administrator has switched to the temporary role.
     * @param bool $switchteacher Whether a non-administrator uses the switched role.
     */
    public function test_inclusion_rechecks_authority_after_lock_wait(
        bool $revoke,
        bool $othersession,
        bool $switched = false,
        bool $switchteacher = false,
    ): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        if (!function_exists('proc_open') || $DB->get_dbfamily() !== 'postgres') {
            $this->markTestSkipped('This test monitors PostgreSQL locks across independent connections.');
        }
        $this->preventResetByRollback();
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = time() - 100;
        $quiz->presencial_timeclose = time() + 3600;
        \quizaccess_presencial::save_settings($quiz);
        $context = \context_module::instance($quiz->cmid);
        $teacher = self::getDataGenerator()->create_user();
        $applicator = self::getDataGenerator()->create_user();
        $role = create_role('Temporary manager', 'temporarymanager', '');
        assign_capability('mod/quiz:manage', CAP_ALLOW, $role, $context->id);
        role_assign($role, $teacher->id, $context->id);
        $this->assertTrue(has_capability('mod/quiz:manage', $context, $teacher->id));
        if ($switchteacher) {
            role_unassign($role, $teacher->id, $context->id);
            $switcher = create_role('Role switcher', 'roleswitcher', '');
            assign_capability('moodle/role:switchroles', CAP_ALLOW, $switcher, $context->id);
            role_assign($switcher, $teacher->id, $context->id);
        }
        $actorid = $switched && !$switchteacher ? (int) get_admin()->id : (int) $teacher->id;
        $result = $this->run_delayed_inclusion($quiz, [
            'userid' => (int) $applicator->id,
            'actorid' => $actorid,
            'sessionid' => $othersession ? (int) get_admin()->id : $actorid,
            'switchrole' => $switched ? $role : 0,
        ], static function () use ($revoke, $switched, $role, $teacher, $context): void {
            if ($revoke) {
                if ($switched) {
                    assign_capability('mod/quiz:manage', CAP_PREVENT, $role, $context->id, true);
                } else {
                    role_unassign($role, $teacher->id, $context->id);
                }
            }
        });
        $this->setAdminUser();
        $current = delegation_manager::list_current($quiz->id);
        if ($revoke) {
            $this->assertSame(['permissiondenied' => true], $result);
            $this->assertEmpty($current);
        } else {
            $this->assertCount(1, $current);
            $this->assertSame((int) reset($current)->id, $result['delegationid']);
            if ($switchteacher) {
                $this->setUser($teacher);
                role_switch($role, $context);
            }
            $again = delegation_manager::include_users($quiz->id, [$applicator->id], $actorid);
            $this->assertSame($result['delegationid'], (int) $again[$applicator->id]->id);
        }
    }

    /**
     * Invitation management rechecks authority after waiting for the quiz lock.
     *
     * @dataProvider delayed_invitation_authority_provider
     * @param bool $revoke Whether the manager's role is revoked while waiting.
     */
    public function test_invitation_details_recheck_authority_after_lock_wait(bool $revoke): void {
        global $DB;

        $this->resetAfterTest();
        if (!function_exists('proc_open') || $DB->get_dbfamily() !== 'postgres') {
            $this->markTestSkipped('This test monitors PostgreSQL locks across independent connections.');
        }
        $this->preventResetByRollback();
        $this->setAdminUser();
        $quiz = $this->create_authority_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $context = \context_module::instance($quiz->cmid);
        $teacher = self::getDataGenerator()->create_user();
        $role = create_role('Invitation manager', 'invitationmanager', '');
        assign_capability('mod/quiz:manage', CAP_ALLOW, $role, $context->id);
        role_assign($role, $teacher->id, $context->id);
        $this->assertTrue(has_capability('mod/quiz:manage', $context, $teacher->id));

        $result = $this->run_delayed_inclusion($quiz, [
            'operation' => 'invitation_details',
            'actorid' => (int) $teacher->id,
            'sessionid' => (int) $teacher->id,
        ], static function () use ($revoke, $role, $teacher, $context): void {
            if ($revoke) {
                role_unassign($role, $teacher->id, $context->id);
            }
        });
        if ($revoke) {
            $this->assertSame(['permissiondenied' => true], $result);
        } else {
            $this->assertSame(['token' => $issued['token'], 'generation' => 1], $result);
        }
        $this->setAdminUser();
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
    }

    /**
     * Invitation changes recheck management authority after waiting for the lock.
     *
     * @dataProvider invitation_mutation_authority_provider
     * @param string $operation Requested operation.
     * @param bool $revoke Whether authority is revoked during the wait.
     */
    public function test_invitation_mutation_rechecks_authority(string $operation, bool $revoke): void {
        global $DB;

        $this->resetAfterTest();
        if (!function_exists('proc_open') || $DB->get_dbfamily() !== 'postgres') {
            $this->markTestSkipped('This test monitors PostgreSQL locks across independent connections.');
        }
        $this->preventResetByRollback();
        $this->setAdminUser();
        $quiz = $this->create_authority_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $context = \context_module::instance($quiz->cmid);
        $teacher = self::getDataGenerator()->create_user();
        $role = create_role('Invitation manager', 'invitationmanager', '');
        assign_capability('mod/quiz:manage', CAP_ALLOW, $role, $context->id);
        role_assign($role, $teacher->id, $context->id);
        $result = $this->run_delayed_inclusion($quiz, [
            'operation' => $operation,
            'actorid' => (int) $teacher->id,
            'sessionid' => (int) $teacher->id,
        ], static function () use ($revoke, $role, $teacher, $context): void {
            if ($revoke) {
                role_unassign($role, $teacher->id, $context->id);
            }
        });
        if ($revoke) {
            $this->assertSame(['permissiondenied' => true], $result);
            $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
        } else if ($operation === 'invitation_generate') {
            $this->assertSame(2, $result['generation']);
            $this->assertTrue(invitation::validate($quiz->cmid, $result['token']));
            $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        } else {
            $this->assertSame(['disabled' => true], $result);
            $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        }
    }

    /**
     * Provide authority outcomes for invitation changes.
     *
     * @return array Named mutation authority scenarios.
     */
    public static function invitation_mutation_authority_provider(): array {
        return [
            'generate revoked' => ['invitation_generate', true],
            'generate allowed' => ['invitation_generate', false],
            'disable revoked' => ['invitation_disable', true],
            'disable allowed' => ['invitation_disable', false],
        ];
    }

    /**
     * Provide both authority outcomes after a lock wait.
     *
     * @return array Named authority scenarios.
     */
    public static function delayed_invitation_authority_provider(): array {
        return [
            'manager loses authority' => [true],
            'manager retains authority' => [false],
        ];
    }

    /**
     * Provide authority and session scenarios for delayed direct inclusion.
     *
     * @return array Named authority and session scenarios.
     */
    public static function delayed_inclusion_provider(): array {
        return [
            'actor loses authority' => [true, false],
            'other actor loses authority' => [true, true],
            'actor retains authority' => [false, false],
            'other actor retains authority' => [false, true],
            'switched administrator loses authority' => [true, false, true],
            'switched administrator retains authority' => [false, false, true],
            'switched teacher retains authority' => [false, false, true, true],
        ];
    }

    /**
     * Exercise either grant path as the owner of the outer transaction.
     *
     * @return array Named operation scenarios.
     */
    public static function outer_operation_provider(): array {
        return [
            'acceptance holds the outer transaction' => ['accept'],
            'direct inclusion holds the outer transaction' => ['include'],
        ];
    }
    /**
     * A removed administrator is evaluated by current contextual roles or forced configuration.
     *
     * @dataProvider administrator_provider
     * @param bool $retainsrole Whether a course role continues to grant management authority.
     * @param bool $forced Whether config.php forces the administrator list.
     * @param bool $padded Whether config.php includes whitespace between administrator ids.
     */
    public function test_administrator_list_is_rechecked_after_wait(bool $retainsrole, bool $forced, bool $padded = false): void {
        global $CFG;

        $this->prepare_authority_test();
        $quiz = $this->create_authority_quiz();
        $teacher = self::getDataGenerator()->create_user();
        $applicator = self::getDataGenerator()->create_user();
        if ($retainsrole) {
            self::getDataGenerator()->enrol_user($teacher->id, $quiz->course, 'editingteacher');
        }
        $admins = $CFG->siteadmins;
        set_config('siteadmins', $admins . ',' . $teacher->id);
        $request = [
            'actorid' => (int) $teacher->id,
            'sessionid' => (int) $teacher->id,
            'userid' => (int) $applicator->id,
        ];
        if ($forced) {
            $request['forcedadmins'] = $padded ? str_replace(',', ', ', $CFG->siteadmins) : $CFG->siteadmins;
        }
        $result = $this->run_delayed_inclusion($quiz, $request, static function () use ($admins): void {
            set_config('siteadmins', $admins);
        });
        if ($retainsrole || $forced) {
            $this->assertArrayHasKey('delegationid', $result);
            $this->assertCount(1, delegation_manager::list_current($quiz->id));
        } else {
            $this->assertSame(['permissiondenied' => true], $result);
            $this->assertEmpty(delegation_manager::list_current($quiz->id));
        }
    }

    /**
     * Supply the administrator authority sources to retain after removal from the database list.
     *
     * @return array Administrator scenarios.
     */
    public static function administrator_provider(): array {
        return [
            'no other authority' => [false, false],
            'course role remains valid' => [true, false],
            'config.php list takes precedence' => [false, true],
            'config.php list accepts whitespace' => [false, true, true],
        ];
    }

    /**
     * A deleted account must not retain authority through a default role cached in its session.
     */
    public function test_deleted_actor_cannot_use_default_role_authority_after_wait(): void {
        global $CFG;

        $this->prepare_authority_test();
        $quiz = $this->create_authority_quiz();
        $teacher = self::getDataGenerator()->create_user();
        $applicator = self::getDataGenerator()->create_user();
        $context = \context_module::instance($quiz->cmid);
        assign_capability('mod/quiz:manage', CAP_ALLOW, $CFG->defaultuserroleid, $context->id, true);
        $result = $this->run_delayed_inclusion($quiz, [
            'actorid' => (int) $teacher->id,
            'sessionid' => (int) $teacher->id,
            'userid' => (int) $applicator->id,
        ], static function () use ($teacher): void {
            delete_user($teacher);
        });
        $this->assertSame(['permissiondenied' => true], $result);
        $current = delegation_manager::list_current($quiz->id);
        $this->assertEmpty($current, 'Unexpected delegation after deleting the actor: ' . json_encode($current));
    }

    /**
     * Enrolment plugins must renew temporary authority after the wait, respecting prohibits.
     *
     * @dataProvider temporary_authority_provider
     * @param string $change Authority change while the worker waits.
     */
    public function test_temporary_authority_is_revalidated_after_wait(string $change): void {
        global $DB;

        $this->prepare_authority_test();
        $quiz = $this->create_authority_quiz();
        $teacher = self::getDataGenerator()->create_user();
        $applicator = self::getDataGenerator()->create_user();
        $context = \context_module::instance($quiz->cmid);
        $role = create_role('Temporary manager', 'temporarymanager', '');
        assign_capability('mod/quiz:manage', CAP_ALLOW, $role, \context_course::instance($quiz->course)->id);
        $restriction = create_role('Management restriction', 'managementrestriction', '');
        role_assign($restriction, $teacher->id, $context->id);
        set_config('guestroleid', $role);
        set_config('enrol_plugins_enabled', 'manual,guest');
        $guest = enrol_get_plugin('guest');
        $instanceid = $guest->add_instance(get_course($quiz->course), [
            'status' => ENROL_INSTANCE_ENABLED,
            'password' => '',
        ]);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $result = $this->run_delayed_inclusion($quiz, [
            'actorid' => (int) $teacher->id,
            'sessionid' => (int) $teacher->id,
            'userid' => (int) $applicator->id,
            'temporaryinstance' => $instanceid,
        ], static function () use ($change, $guest, $instance, $role, $restriction, $context): void {
            if ($change === 'disable') {
                $guest->update_status($instance, ENROL_INSTANCE_DISABLED);
            } else if ($change === 'prevent') {
                assign_capability('mod/quiz:manage', CAP_PREVENT, $role, $context->id);
            } else if ($change === 'prohibit') {
                assign_capability('mod/quiz:manage', CAP_PROHIBIT, $restriction, $context->id);
            }
        });
        if ($change === 'none') {
            $this->assertArrayHasKey('delegationid', $result);
            $this->assertCount(1, delegation_manager::list_current($quiz->id));
        } else {
            $this->assertSame(['permissiondenied' => true], $result);
            $this->assertEmpty(delegation_manager::list_current($quiz->id));
        }
    }

    /**
     * Supply valid and invalidated temporary authority scenarios.
     *
     * @return array Temporary authority scenarios.
     */
    public static function temporary_authority_provider(): array {
        return [
            'grant remains valid' => ['none'],
            'enrolment instance disabled' => ['disable'],
            'role permission removed' => ['prevent'],
            'persisted prohibit wins' => ['prohibit'],
        ];
    }

    /**
     * A stale persisted role must not be mistaken for a temporary enrolment role.
     *
     * @dataProvider session_actor_provider
     * @param bool $othersession Whether the actor differs from the current session user.
     */
    public function test_role_revoked_before_inclusion_is_not_temporary_authority(bool $othersession): void {
        $this->prepare_authority_test();
        $quiz = $this->create_authority_quiz();
        $teacher = self::getDataGenerator()->create_user();
        $applicator = self::getDataGenerator()->create_user();
        $context = \context_module::instance($quiz->cmid);
        $role = create_role('Revoked manager', 'revokedmanager', '');
        assign_capability('mod/quiz:manage', CAP_ALLOW, $role, $context->id);
        role_assign($role, $teacher->id, $context->id);
        $result = $this->run_delayed_inclusion($quiz, [
            'actorid' => (int) $teacher->id,
            'sessionid' => $othersession ? (int) get_admin()->id : (int) $teacher->id,
            'userid' => (int) $applicator->id,
            'waitforstart' => true,
        ], static function () use ($role, $teacher, $context): void {
            role_unassign($role, $teacher->id, $context->id);
        });
        $this->assertSame(['permissiondenied' => true], $result);
        $this->assertEmpty(delegation_manager::list_current($quiz->id));
    }

    /**
     * Supply current and explicit actor scenarios.
     *
     * @return array Session scenarios.
     */
    public static function session_actor_provider(): array {
        return ['session actor' => [false], 'explicit actor' => [true]];
    }

    /**
     * Switched and default roles use Moodle's current contextual precedence after a wait.
     *
     * @dataProvider switched_permission_provider
     * @param int $coursepermission Course permission committed while waiting.
     * @param int $modulepermission Quiz override committed while waiting.
     * @param int|null $defaultpermission Optional permission for the default user role.
     * @param bool $expected Whether management authority remains valid.
     */
    public function test_switched_roles_use_current_precedence(
        int $coursepermission,
        int $modulepermission,
        ?int $defaultpermission,
        bool $expected,
    ): void {
        global $CFG;

        $this->prepare_authority_test();
        $quiz = $this->create_authority_quiz();
        $applicator = self::getDataGenerator()->create_user();
        $context = \context_module::instance($quiz->cmid);
        $coursecontext = \context_course::instance($quiz->course);
        $role = create_role('Switched manager', 'switchedmanager', '');
        assign_capability('mod/quiz:manage', CAP_ALLOW, $role, $coursecontext->id);
        $defaultrole = $CFG->defaultuserroleid;
        $result = $this->run_delayed_inclusion($quiz, [
            'actorid' => (int) get_admin()->id,
            'sessionid' => (int) get_admin()->id,
            'userid' => (int) $applicator->id,
            'switchrole' => $role,
        ], static function () use (
            $coursepermission,
            $modulepermission,
            $defaultpermission,
            $role,
            $defaultrole,
            $context,
            $coursecontext,
        ): void {
            assign_capability('mod/quiz:manage', $coursepermission, $role, $coursecontext->id, true);
            assign_capability('mod/quiz:manage', $modulepermission, $role, $context->id, true);
            if ($defaultpermission !== null) {
                assign_capability('mod/quiz:manage', $defaultpermission, $defaultrole, $context->id, true);
            }
        });
        if ($expected) {
            $this->assertArrayHasKey('delegationid', $result);
            $this->assertCount(1, delegation_manager::list_current($quiz->id));
        } else {
            $this->assertSame(['permissiondenied' => true], $result);
            $this->assertEmpty(delegation_manager::list_current($quiz->id));
        }
    }

    /**
     * Supply contextual overrides and default-role combinations.
     *
     * @return array Permission precedence scenarios.
     */
    public static function switched_permission_provider(): array {
        return [
            'quiz allows' => [CAP_ALLOW, CAP_ALLOW, null, true],
            'quiz prevents' => [CAP_ALLOW, CAP_PREVENT, null, false],
            'quiz overrides course prevent' => [CAP_PREVENT, CAP_ALLOW, null, true],
            'course prohibit wins' => [CAP_PROHIBIT, CAP_ALLOW, null, false],
            'default role supplies allowance' => [CAP_ALLOW, CAP_PREVENT, CAP_ALLOW, true],
            'default role prohibit wins' => [CAP_ALLOW, CAP_ALLOW, CAP_PROHIBIT, false],
        ];
    }

    /**
     * Initialize a test requiring committed fixtures and PostgreSQL lock monitoring.
     */
    private function prepare_authority_test(): void {
        global $DB;

        $this->resetAfterTest();
        if (!function_exists('proc_open') || $DB->get_dbfamily() !== 'postgres') {
            $this->markTestSkipped('This test monitors PostgreSQL locks across independent connections.');
        }
        $this->preventResetByRollback();
        $this->setAdminUser();
    }

    /**
     * Create a quiz with an active authorization period for the authority scenarios.
     *
     * @return \stdClass Configured quiz.
     */
    private function create_authority_quiz(): \stdClass {
        $quiz = self::getDataGenerator()->create_module('quiz', [
            'course' => self::getDataGenerator()->create_course()->id,
        ]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = time() - 100;
        $quiz->presencial_timeclose = time() + 3600;
        \quizaccess_presencial::save_settings($quiz);
        return $quiz;
    }

    /**
     * Start one independent Moodle connection using the shared PHPUnit bootstrap.
     *
     * @param string $fixture Fixture file name under tests/fixtures.
     * @return array Process resource and its input, output and error pipes.
     */
    private function start_worker(string $fixture): array {
        global $CFG;

        $bootstrap = <<<'PHP'
        $autoload = $argv[1] . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            $autoload = dirname($argv[1]) . '/vendor/autoload.php';
        }
        require($autoload);
        define('PHPUNIT_UTIL', true);
        require($argv[1] . '/lib/phpunit/bootstrap.php');
        require($argv[2]);
        PHP;
        $process = proc_open(
            [PHP_BINARY, '-d', 'max_input_vars=5000', '-r', $bootstrap, $CFG->dirroot,
                $CFG->dirroot . '/mod/quiz/accessrule/presencial/tests/fixtures/' . $fixture],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertIsResource($process);
        return [$process, $pipes];
    }

    /**
     * Read a worker response with stage-specific diagnostics for EOF and timeouts.
     *
     * @param array $pipes Worker pipes.
     * @param string $stage Expected response description.
     * @return array Decoded response.
     */
    private function read_worker_response(array $pipes, string $stage): array {
        $line = fgets($pipes[1]);
        if ($line === false) {
            stream_set_blocking($pipes[2], false);
            $this->fail('Missing worker response: ' . $stage . '. STDERR: ' . stream_get_contents($pipes[2]));
        }
        try {
            $result = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->fail('Invalid worker response: ' . $stage . '. ' . $exception->getMessage() . ': ' . $line);
        }
        $this->assertIsArray($result, 'Expected an object for worker response: ' . $stage);
        return $result;
    }

    /**
     * Change authority after the worker has loaded its access data.
     *
     * @param \stdClass $quiz Quiz configuration.
     * @param array $request Worker parameters.
     * @param callable $change Change committed before the inclusion continues.
     * @return array Public inclusion result reported by the worker.
     */
    private function run_delayed_inclusion(\stdClass $quiz, array $request, callable $change): array {
        global $DB;

        [$process, $pipes] = $this->start_worker('delegation_authority_worker.php');
        $transaction = null;
        $trackingcomplete = false;
        try {
            stream_set_timeout($pipes[1], 30);
            stream_set_timeout($pipes[2], 30);
            $transaction = $DB->start_delegated_transaction();
            \quizaccess_presencial\local\quiz_lock::execute($quiz->id, static function (): void {
            });
            $request += ['quizid' => (int) $quiz->id, 'cmid' => (int) $quiz->cmid];
            fwrite($pipes[0], json_encode($request, JSON_THROW_ON_ERROR) . "\n");
            fflush($pipes[0]);
            $connection = $this->read_worker_response($pipes, 'database connection');
            if (empty($request['waitforstart'])) {
                $deadline = microtime(true) + 10;
                do {
                    $waiting = $DB->get_field_sql(
                        'SELECT 1 WHERE pg_backend_pid() = ANY(pg_blocking_pids(:connectionid))',
                        ['connectionid' => $connection['connectionid']],
                    );
                    if ($waiting) {
                        break;
                    }
                    usleep(10000);
                } while (microtime(true) < $deadline);
                $this->assertNotFalse($waiting, 'The operation must actually wait for the quiz lock.');
            }
            $change();
            $transaction->allow_commit();
            $transaction = null;
            if (!empty($request['waitforstart'])) {
                fwrite($pipes[0], "START\n");
                fflush($pipes[0]);
            }
            $response = $this->read_worker_response($pipes, 'inclusion result');
            // PHPUnit tracks writes per process; include the worker's writes in the parent's reset.
            foreach ($response['modifiedtables'] as $table) {
                \phpunit_util::$tableupdated[$table] = true;
            }
            $trackingcomplete = true;
            $this->assertSame('', stream_get_contents($pipes[2]));
            return $response['result'];
        } finally {
            if ($transaction) {
                try {
                    $transaction->rollback(new \Exception('Concurrent inclusion test cleanup.'));
                } catch (\Exception $ignored) {
                    $transaction = null;
                }
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            proc_close($process);
            if (!$trackingcomplete) {
                // A crashed worker cannot report its writes, so reset every table conservatively.
                foreach ($DB->get_tables() as $table) {
                    \phpunit_util::$tableupdated[$table] = true;
                }
            }
        }
    }
}
