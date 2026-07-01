<?php

namespace App\Modules\Settings;

/**
 * Settings Validator
 * 
 * Validates setting values based on type and business rules.
 */
class SettingsValidator
{
    private array $errors = [];

    /**
     * Validate a batch of settings.
     */
    public function validate(array $settings, array $definitions): bool
    {
        $this->errors = [];

        foreach ($settings as $key => $value) {
            $def = $this->findDefinition($key, $definitions);
            if (!$def) continue;

            $this->validateField($key, $value, $def);
        }

        return empty($this->errors);
    }

    /**
     * Validate admin creation data.
     */
    public function validateAdmin(array $data, bool $isUpdate = false): bool
    {
        $this->errors = [];

        if (!$isUpdate || isset($data['name'])) {
            if (empty($data['name'])) {
                $this->errors['name'] = 'Name is required';
            } elseif (strlen($data['name']) < 2 || strlen($data['name']) > 100) {
                $this->errors['name'] = 'Name must be between 2 and 100 characters';
            }
        }

        if (!$isUpdate || isset($data['email'])) {
            if (empty($data['email'])) {
                $this->errors['email'] = 'Email is required';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $this->errors['email'] = 'Invalid email address';
            }
        }

        if (!$isUpdate) {
            // Password required for new admins
            if (empty($data['password'])) {
                $this->errors['password'] = 'Password is required';
            } elseif (strlen($data['password']) < 8) {
                $this->errors['password'] = 'Password must be at least 8 characters';
            }
        } elseif (isset($data['password']) && !empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                $this->errors['password'] = 'Password must be at least 8 characters';
            }
        }

        return empty($this->errors);
    }

    /**
     * Validate a single field.
     */
    private function validateField(string $key, $value, array $def): void
    {
        $type = $def['setting_type'] ?? 'text';

        switch ($type) {
            case 'number':
                if ($value !== '' && !is_numeric($value)) {
                    $this->errors[$key] = 'Must be a number';
                } elseif (is_numeric($value) && (int) $value < 0) {
                    $this->errors[$key] = 'Must be a positive number';
                }
                break;

            case 'boolean':
                if (!in_array($value, ['0', '1', ''], true)) {
                    $this->errors[$key] = 'Must be true or false';
                }
                break;

            case 'select':
                // No general validation; specific selects validated elsewhere
                break;

            case 'text':
            default:
                if (strlen($value) > 1000) {
                    $this->errors[$key] = 'Value too long (max 1000 chars)';
                }
                break;
        }
    }

    /**
     * Find definition by key.
     */
    private function findDefinition(string $key, array $definitions): ?array
    {
        foreach ($definitions as $def) {
            if ($def['setting_key'] === $key) return $def;
        }
        return null;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        return empty($this->errors) ? null : reset($this->errors);
    }
}
