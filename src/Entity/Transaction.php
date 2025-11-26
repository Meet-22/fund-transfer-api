<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: 'App\Repository\TransactionRepository')]
#[ORM\Table(name: 'transactions')]
#[ORM\Index(name: 'idx_transaction_id', columns: ['transaction_id'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_from_account', columns: ['from_account_id'])]
#[ORM\Index(name: 'idx_to_account', columns: ['to_account_id'])]
#[ORM\HasLifecycleCallbacks]
class Transaction
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_WITHDRAWAL = 'withdrawal';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['transaction:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Groups(['transaction:read', 'transaction:create'])]
    private ?string $transactionId = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['transfer', 'deposit', 'withdrawal'])]
    #[Groups(['transaction:read', 'transaction:create'])]
    private ?string $type = null;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'outgoingTransactions')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['transaction:read'])]
    private ?Account $fromAccount = null;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'incomingTransactions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['transaction:read'])]
    private ?Account $toAccount = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['transaction:read', 'transaction:create'])]
    private ?string $amount = null;

    #[ORM\Column(type: 'string', length: 3)]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    #[Groups(['transaction:read', 'transaction:create'])]
    private string $currency = 'USD';

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['pending', 'processing', 'completed', 'failed', 'cancelled'])]
    #[Groups(['transaction:read'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['transaction:read', 'transaction:create'])]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['transaction:read'])]
    private ?string $failureReason = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['transaction:read'])]
    private ?array $metadata = [];

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['transaction:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['transaction:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['transaction:read'])]
    private ?\DateTimeImmutable $processedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getFromAccount(): ?Account
    {
        return $this->fromAccount;
    }

    public function setFromAccount(?Account $fromAccount): self
    {
        $this->fromAccount = $fromAccount;
        return $this;
    }

    public function getToAccount(): ?Account
    {
        return $this->toAccount;
    }

    public function setToAccount(?Account $toAccount): self
    {
        $this->toAccount = $toAccount;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): self
    {
        $this->failureReason = $failureReason;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): self
    {
        $this->processedAt = $processedAt;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function markAsProcessing(): self
    {
        $this->status = self::STATUS_PROCESSING;
        return $this;
    }

    public function markAsCompleted(): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->processedAt = new \DateTimeImmutable();
        return $this;
    }

    public function markAsFailed(string $reason = null): self
    {
        $this->status = self::STATUS_FAILED;
        $this->failureReason = $reason;
        $this->processedAt = new \DateTimeImmutable();
        return $this;
    }

    public function generateTransactionId(): string
    {
        return 'TXN-' . strtoupper(uniqid()) . '-' . date('YmdHis');
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        if (!$this->transactionId) {
            $this->transactionId = $this->generateTransactionId();
        }
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
