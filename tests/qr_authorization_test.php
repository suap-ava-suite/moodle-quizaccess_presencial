<?php
// This file is part of Moodle - http://moodle.org/

namespace quizaccess_presencial;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

/** @covers \quizaccess_presencial\local\qr_authorization */
final class qr_authorization_test extends \advanced_testcase {
    public function test_qr_is_single_use_and_authorizes_the_request(): void {
        [$request, $student, $operator, $requests] = $this->request_fixture();
        $this->setUser($student);
        $token = \quizaccess_presencial\local\qr_authorization::issue($request->id);
        $this->setUser($operator);
        \quizaccess_presencial\local\qr_authorization::authorize($request->id, $token);
        $this->assertSame('authorized', $requests->get($request->id)->state);
        $this->expectException(\moodle_exception::class);
        \quizaccess_presencial\local\qr_authorization::authorize($request->id, $token);
    }

    public function test_replaced_and_expired_codes_cannot_authorize(): void {
        [$request, $student, $operator, $requests] = $this->request_fixture();
        $this->setUser($student);
        $old = \quizaccess_presencial\local\qr_authorization::issue($request->id);
        $current = \quizaccess_presencial\local\qr_authorization::issue($request->id);
        $this->setUser($operator);
        try {
            \quizaccess_presencial\local\qr_authorization::authorize($request->id, $old);
            $this->fail('A replaced QR code must not authorize.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('qrinvalid', $exception->errorcode);
        }
        $this->assertSame('pending', $requests->get($request->id)->state);
        \core\di::get(\core\clock::class)->bump(60);
        try {
            \quizaccess_presencial\local\qr_authorization::authorize($request->id, $current);
            $this->fail('A QR code must expire after 60 seconds.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('qrinvalid', $exception->errorcode);
        }
        $this->assertSame('pending', $requests->get($request->id)->state);
    }

    public function test_an_unprivileged_user_cannot_consume_the_code(): void {
        [$request, $student, $operator, $requests] = $this->request_fixture();
        $this->setUser($student);
        $token = \quizaccess_presencial\local\qr_authorization::issue($request->id);
        $this->setUser(self::getDataGenerator()->create_user());
        try {
            \quizaccess_presencial\local\qr_authorization::authorize($request->id, $token);
            $this->fail('An unprivileged user must not authorize.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('pending', $requests->get($request->id)->state);
        }
        $this->setUser($operator);
        \quizaccess_presencial\local\qr_authorization::authorize($request->id, $token);
        $this->assertSame('authorized', $requests->get($request->id)->state);
    }

    public function test_code_is_valid_at_59_seconds_and_cannot_be_used_for_another_request(): void {
        [$request, $student, $operator, $requests] = $this->request_fixture();
        $other = self::getDataGenerator()->create_user();
        $otherrequest = $requests->ensure_pending($request->quizid, $other->id, 1);
        $this->setUser($student);
        $token = \quizaccess_presencial\local\qr_authorization::issue($request->id);
        \core\di::get(\core\clock::class)->bump(59);
        $this->setUser($operator);
        try {
            \quizaccess_presencial\local\qr_authorization::authorize($otherrequest->id, $token);
            $this->fail('The QR must not authorize a different request.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('qrinvalid', $exception->errorcode);
        }
        \quizaccess_presencial\local\qr_authorization::authorize($request->id, $token);
        $this->assertSame('authorized', $requests->get($request->id)->state);
        $this->assertSame('pending', $requests->get($otherrequest->id)->state);
    }

    private function request_fixture(): array {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = 1_799_999_000;
        $quiz->presencial_timeclose = 1_800_010_000;
        \quizaccess_presencial::save_settings($quiz);
        $student = $generator->create_user();
        $operator = $generator->create_user();
        \quizaccess_presencial\local\delegation_manager::include_users(
            $quiz->id, [$operator->id], get_admin()->id, 'direct', 1_800_000_000
        );
        $requests = new \quizaccess_presencial\local\release_request_service();
        $request = $requests->ensure_pending($quiz->id, $student->id, 1);
        return [$request, $student, $operator, $requests];
    }
}
