<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\RecordingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Un enregistrement audio reçu du device Skald.
 *
 * L'upload se fait via POST multipart géré par RecordingUploadController
 * (API Platform n'expose ici que la lecture). La persistance des métadonnées
 * sert de socle au pipeline de traitement (phases ultérieures).
 */
#[ORM\Entity(repositoryClass: RecordingRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
)]
class Recording
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $originalFilename;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $storagePath;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $mimeType;

    #[ORM\Column(type: Types::BIGINT)]
    private int $sizeBytes;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $deviceId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $recordedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $uploadedAt;

    /**
     * Statut de traitement. v0.1 : toujours "received".
     * Valeurs futures : "processing", "done".
     */
    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = 'received';

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): self
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): self
    {
        $this->sizeBytes = $sizeBytes;

        return $this;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(?string $deviceId): self
    {
        $this->deviceId = $deviceId;

        return $this;
    }

    public function getRecordedAt(): ?\DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(?\DateTimeImmutable $recordedAt): self
    {
        $this->recordedAt = $recordedAt;

        return $this;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
