<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Declarative server-side validation.
 *
 *   $data = Validator::make($request->post, [
 *       'email' => 'required|email|max:191',
 *       'qty'   => 'required|int|min:1|max:99',
 *   ])->validated();   // throws ValidationException
 *
 * Rules: required, nullable, string, email, int, money, bool, min:n, max:n
 * (length for strings, value for numbers), in:a,b, regex:/x/, phone, url,
 * slug, confirmed, date, same:field, hex
 */
final class Validator
{
    private array $errors = [];
    private array $clean = [];

    private const LABELS = [
        'password_confirmation' => 'password confirmation',
        // Address fields (US store): the column is still named province/postal_code.
        'province' => 'state',
        'postal_code' => 'ZIP code',
        'line1' => 'street address',
        'phone' => 'phone number',
    ];

    private function __construct(private readonly array $input, private readonly array $rules)
    {
        $this->run();
    }

    public static function make(array $input, array $rules): self
    {
        return new self($input, $rules);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        if ($this->errors) {
            throw new ValidationException($this->errors);
        }
        return $this->clean;
    }

    private function label(string $field): string
    {
        return self::LABELS[$field] ?? str_replace('_', ' ', $field);
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $spec) {
            $rules = is_array($spec) ? $spec : explode('|', $spec);
            $raw = $this->input[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;
            $label = ucfirst($this->label($field));
            $isInt = in_array('int', $rules, true);
            $isMoney = in_array('money', $rules, true);
            $isBool = in_array('bool', $rules, true);

            if ($isBool) {
                $this->clean[$field] = in_array($value, ['1', 'on', 'true', 'yes', 1, true], true);
                continue;
            }

            $empty = $value === null || $value === '' || $value === [];
            if ($empty) {
                if (in_array('required', $rules, true)) {
                    $this->errors[$field] = "{$label} is required.";
                } else {
                    $this->clean[$field] = null;
                }
                continue;
            }
            if (is_array($value)) {
                $this->errors[$field] = "{$label} is invalid.";
                continue;
            }
            $value = (string) $value;
            if (!mb_check_encoding($value, 'UTF-8')) {
                $this->errors[$field] = "{$label} contains invalid characters.";
                continue;
            }
            // Strip control characters except newline/tab.
            $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

            $typed = $value;
            if ($isInt) {
                if (!preg_match('/^-?\d{1,12}$/', $value)) {
                    $this->errors[$field] = "{$label} must be a whole number.";
                    continue;
                }
                $typed = (int) $value;
            }
            if ($isMoney) {
                $minor = Money::parse($value);
                if ($minor === null) {
                    $this->errors[$field] = "{$label} must be an amount like 2499 or 2499.50.";
                    continue;
                }
                $typed = $minor;
            }

            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = match ($name) {
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) && strlen($value) <= 191 ? null : "Enter a valid email address.",
                    'min' => ($isInt || $isMoney)
                        ? ($typed < (int) $arg ? "{$label} must be at least {$arg}." : null)
                        : (mb_strlen($value) < (int) $arg ? "{$label} must be at least {$arg} characters." : null),
                    'max' => ($isInt || $isMoney)
                        ? ($typed > (int) $arg ? "{$label} may not be greater than {$arg}." : null)
                        : (mb_strlen($value) > (int) $arg ? "{$label} may not be longer than {$arg} characters." : null),
                    'in' => in_array($value, explode(',', (string) $arg), true) ? null : "Select a valid {$this->label($field)}.",
                    'regex' => preg_match((string) $arg, $value) ? null : "{$label} format is invalid.",
                    'phone' => preg_match('/^\+?[0-9(][0-9\s\-().]{6,19}$/', $value) && preg_match_all('/\d/', $value) >= 7 ? null : 'Enter a valid phone number.',
                    'url' => (filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value)) ? null : "{$label} must be a valid http(s) URL.",
                    'link' => (preg_match('#^/[^/\\\\]#', $value) || $value === '/' || (filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value))) ? null : "{$label} must start with / or https://",
                    'slug' => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) ? null : "{$label} may contain lowercase letters, numbers and hyphens only.",
                    'hex' => preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? null : "{$label} must be a colour like #0B0B0B.",
                    'date' => \DateTime::createFromFormat('Y-m-d', $value) ? null : "{$label} must be a valid date.",
                    'datetime' => (\DateTime::createFromFormat('Y-m-d\TH:i', $value) || \DateTime::createFromFormat('Y-m-d H:i:s', $value)) ? null : "{$label} must be a valid date and time.",
                    'confirmed' => (($this->input[$field . '_confirmation'] ?? null) === ($this->input[$field] ?? null)) ? null : "{$label} confirmation does not match.",
                    'password' => (preg_match('/[A-Za-z]/', $value) && preg_match('/\d/', $value)) ? null : 'Password must include at least one letter and one number.',
                    default => null,
                };
                if ($error !== null) {
                    $this->errors[$field] = $error;
                    continue 2;
                }
            }
            $this->clean[$field] = $typed;
        }
    }
}
