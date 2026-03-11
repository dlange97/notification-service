<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationTemplateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationTemplateRepository::class)]
#[ORM\Table(name: 'notification_template')]
#[ORM\UniqueConstraint(name: 'uniq_notification_template_key', columns: ['template_key'])]
#[ORM\HasLifecycleCallbacks]
class NotificationTemplate
{
    public const REQUEST_ACCESS_KEY = 'request-access';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'template_key', type: 'string', length: 80)]
    private string $templateKey = self::REQUEST_ACCESS_KEY;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $inboxEnabled = true;

    #[ORM\Column(type: 'string', length: 255)]
    private string $inboxTitle = 'Nowy wniosek o dostęp do aplikacji';

    #[ORM\Column(type: 'text')]
    private string $inboxBody = "Użytkownik {{email}} poprosił o dostęp do aplikacji.\nImię i nazwisko: {{firstName}} {{lastName}}\nWiadomość: {{message}}";

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $emailEnabled = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $emailTitle = 'Request access to My Dashboard';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $emailBody = "User {{email}} requested access.\nName: {{firstName}} {{lastName}}\nMessage: {{message}}";

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $pushEnabled = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pushTitle = 'Nowy request access';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $pushBody = 'Użytkownik {{email}} poprosił o dostęp.';

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTemplateKey(): string
    {
        return $this->templateKey;
    }

    public function setTemplateKey(string $templateKey): static
    {
        $this->templateKey = $templateKey;
        return $this;
    }

    public function isInboxEnabled(): bool
    {
        return $this->inboxEnabled;
    }

    public function setInboxEnabled(bool $inboxEnabled): static
    {
        $this->inboxEnabled = $inboxEnabled;
        return $this;
    }

    public function getInboxTitle(): string
    {
        return $this->inboxTitle;
    }

    public function setInboxTitle(string $inboxTitle): static
    {
        $this->inboxTitle = $inboxTitle;
        return $this;
    }

    public function getInboxBody(): string
    {
        return $this->inboxBody;
    }

    public function setInboxBody(string $inboxBody): static
    {
        $this->inboxBody = $inboxBody;
        return $this;
    }

    public function isEmailEnabled(): bool
    {
        return $this->emailEnabled;
    }

    public function setEmailEnabled(bool $emailEnabled): static
    {
        $this->emailEnabled = $emailEnabled;
        return $this;
    }

    public function getEmailTitle(): ?string
    {
        return $this->emailTitle;
    }

    public function setEmailTitle(?string $emailTitle): static
    {
        $this->emailTitle = $emailTitle;
        return $this;
    }

    public function getEmailBody(): ?string
    {
        return $this->emailBody;
    }

    public function setEmailBody(?string $emailBody): static
    {
        $this->emailBody = $emailBody;
        return $this;
    }

    public function isPushEnabled(): bool
    {
        return $this->pushEnabled;
    }

    public function setPushEnabled(bool $pushEnabled): static
    {
        $this->pushEnabled = $pushEnabled;
        return $this;
    }

    public function getPushTitle(): ?string
    {
        return $this->pushTitle;
    }

    public function setPushTitle(?string $pushTitle): static
    {
        $this->pushTitle = $pushTitle;
        return $this;
    }

    public function getPushBody(): ?string
    {
        return $this->pushBody;
    }

    public function setPushBody(?string $pushBody): static
    {
        $this->pushBody = $pushBody;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
