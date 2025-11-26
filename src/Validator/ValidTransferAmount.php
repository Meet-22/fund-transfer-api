<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ValidTransferAmount extends Constraint
{
    public string $message = 'Invalid transfer amount. Amount must be a positive decimal value.';
    public string $tooSmallMessage = 'Transfer amount is too small. Minimum amount is {{ minimum }}.';
    public string $tooLargeMessage = 'Transfer amount is too large. Maximum amount is {{ maximum }}.';
}

class ValidTransferAmountValidator extends \Symfony\Component\Validator\ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        // Convert to string for decimal operations
        $amount = (string) $value;

        // Check if it's a valid decimal number
        if (!is_numeric($amount)) {
            $this->context->buildViolation($constraint->message)->addViolation();
            return;
        }

        // Check if positive
        if (bccomp($amount, '0', 2) <= 0) {
            $this->context->buildViolation($constraint->message)->addViolation();
            return;
        }

        // Check minimum amount
        $minAmount = $_ENV['MINIMUM_TRANSFER_AMOUNT'] ?? '0.01';
        if (bccomp($amount, $minAmount, 2) < 0) {
            $this->context->buildViolation($constraint->tooSmallMessage)
                ->setParameter('{{ minimum }}', $minAmount)
                ->addViolation();
            return;
        }

        // Check maximum amount
        $maxAmount = $_ENV['SINGLE_TRANSFER_LIMIT'] ?? '50000.00';
        if (bccomp($amount, $maxAmount, 2) > 0) {
            $this->context->buildViolation($constraint->tooLargeMessage)
                ->setParameter('{{ maximum }}', $maxAmount)
                ->addViolation();
            return;
        }
    }
}
