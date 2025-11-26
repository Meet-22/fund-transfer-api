<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ValidAccountNumber extends Constraint
{
    public string $message = 'Invalid account number format. Account number must be alphanumeric and between 10-50 characters.';
}

class ValidAccountNumberValidator extends \Symfony\Component\Validator\ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
            return;
        }

        // Check length
        if (strlen($value) < 10 || strlen($value) > 50) {
            $this->context->buildViolation($constraint->message)->addViolation();
            return;
        }

        // Check format (alphanumeric with optional hyphens)
        if (!preg_match('/^[A-Za-z0-9\-]+$/', $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
            return;
        }
    }
}
