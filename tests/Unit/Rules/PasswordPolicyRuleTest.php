<?php

namespace Tests\Unit\Rules;

use App\Rules\PasswordPolicyRule;
use Tests\TestCase;

class PasswordPolicyRuleTest extends TestCase
{
    private function validate(string $password, array $config = []): ?string
    {
        config([
            'parkhub.password_min_length' => $config['min_length'] ?? 8,
            'parkhub.password_require_uppercase' => $config['require_uppercase'] ?? true,
            'parkhub.password_require_number' => $config['require_number'] ?? true,
            'parkhub.password_require_special' => $config['require_special'] ?? false,
        ]);

        $rule = new PasswordPolicyRule;
        $failMessage = null;

        $rule->validate('password', $password, function (string $message) use (&$failMessage) {
            $failMessage = $message;
        });

        return $failMessage;
    }

    public function test_valid_password_passes(): void
    {
        $this->assertNull($this->validate('SecurePass1'));
    }

    public function test_too_short_password_fails(): void
    {
        $message = $this->validate('Ab1');
        $this->assertNotNull($message);
        $this->assertStringContainsString('8', $message);
    }

    public function test_too_long_password_fails(): void
    {
        $message = $this->validate(str_repeat('Aa1', 50));
        $this->assertNotNull($message);
        $this->assertStringContainsString('128', $message);
    }

    public function test_missing_uppercase_fails(): void
    {
        $message = $this->validate('lowercase1');
        $this->assertNotNull($message);
        $this->assertStringContainsString('uppercase', $message);
    }

    public function test_missing_lowercase_fails(): void
    {
        $message = $this->validate('UPPERCASE1');
        $this->assertNotNull($message);
        $this->assertStringContainsString('lowercase', $message);
    }

    public function test_missing_number_fails(): void
    {
        $message = $this->validate('SecurePass');
        $this->assertNotNull($message);
        $this->assertStringContainsString('number', $message);
    }

    public function test_special_character_required_when_configured(): void
    {
        $message = $this->validate('SecurePass1', ['require_special' => true]);
        $this->assertNotNull($message);
        $this->assertStringContainsString('special', $message);
    }

    public function test_special_character_passes_when_present(): void
    {
        $this->assertNull($this->validate('SecurePass1!', ['require_special' => true]));
    }

    public function test_uppercase_not_required_when_disabled(): void
    {
        $this->assertNull($this->validate('lowercase1', ['require_uppercase' => false]));
    }

    public function test_number_not_required_when_disabled(): void
    {
        $this->assertNull($this->validate('SecurePass', ['require_number' => false]));
    }

    public function test_custom_min_length(): void
    {
        $message = $this->validate('Ab1cde', ['min_length' => 10]);
        $this->assertNotNull($message);
        $this->assertStringContainsString('10', $message);
    }
}
