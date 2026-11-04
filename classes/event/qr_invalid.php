<?php
// This file is part of Moodle - http://moodle.org/

namespace quizaccess_presencial\event;

/**
 * An invalid QR authorization was presented.
 *
 * @package quizaccess_presencial
 * @copyright 2026 SUAP AVA Suite
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class qr_invalid extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }
    public static function get_name(): string {
        return get_string('eventqrinvalid', 'quizaccess_presencial');
    }
    public function get_description(): string {
        return 'An invalid QR authorization was presented.';
    }
}
