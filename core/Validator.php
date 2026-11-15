<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Form Validator
 */

class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public static function make(array $data, array $rules): self {
        $validator = new self($data);
        $validator->validate($rules);
        return $validator;
    }

    public function validate(array $rules): void {
        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $this->applyRule($field, $value, $rule, $params);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule, array $params): void {
        $label = ucwords(str_replace(['_', '-'], ' ', $field));

        switch ($rule) {
            case 'required':
                if ($value === null || trim((string)$value) === '') {
                    $this->addError($field, "Kolom {$label} wajib diisi.");
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, "Kolom {$label} harus berupa angka.");
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "Format {$label} tidak valid.");
                }
                break;

            case 'min':
                $min = (int)($params[0] ?? 0);
                if (!empty($value) && strlen((string)$value) < $min) {
                    $this->addError($field, "Kolom {$label} minimal berjumlah {$min} karakter.");
                }
                break;

            case 'max':
                $max = (int)($params[0] ?? 255);
                if (!empty($value) && strlen((string)$value) > $max) {
                    $this->addError($field, "Kolom {$label} maksimal berjumlah {$max} karakter.");
                }
                break;
        }
    }

    public function addError(string $field, string $message): void {
        $this->errors[$field][] = $message;
    }

    public function fails(): bool {
        return !empty($this->errors);
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }

    public function firstError(): ?string {
        if (empty($this->errors)) {
            return null;
        }
        $first = reset($this->errors);
        return $first[0] ?? null;
    }
}
