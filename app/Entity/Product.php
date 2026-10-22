<?php

namespace App\Entity;

use App\Doctrine\UuidType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\Index(name: 'products_created_at_id_idx', columns: ['created_at', 'id'])]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 64, unique: true)]
    private string $sku;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price;

    #[ORM\Column]
    private int $stock;

    #[ORM\Column]
    private bool $active;

    // Denormalized from product_ratings (see ProductRatingRepository::summary) so listings don't aggregate ratings
    #[ORM\Column(name: 'rating_average', type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    private ?string $ratingAverage = null;

    #[ORM\Column(name: 'rating_count')]
    private int $ratingCount = 0;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $name,
        string $sku,
        string $price,
        int $stock,
        ?string $description = null,
        bool $active = true,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->update($name, $sku, $price, $stock, $description, $active);
    }

    public function update(
        string $name,
        string $sku,
        string $price,
        int $stock,
        ?string $description,
        bool $active,
    ): void {
        $this->name = $name;
        $this->sku = $sku;
        $this->price = $price;
        $this->stock = $stock;
        $this->description = $description;
        $this->active = $active;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateRatingSummary(?string $average, int $count): void
    {
        $this->ratingAverage = $average;
        $this->ratingCount = $count;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getRatingAverage(): ?string
    {
        return $this->ratingAverage;
    }

    public function getRatingCount(): int
    {
        return $this->ratingCount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
