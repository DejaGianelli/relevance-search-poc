<?php

namespace App\Entity;

use App\Doctrine\UuidType;
use App\Repository\ProductRatingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A 1 to 5 stars rating given to a product by a visitor (identified by their session).
 */
#[ORM\Entity(repositoryClass: ProductRatingRepository::class)]
#[ORM\Table(name: 'product_ratings')]
#[ORM\UniqueConstraint(name: 'product_ratings_product_rater_key', columns: ['product_id', 'rater'])]
class ProductRating
{
    public const MIN_STARS = 1;
    public const MAX_STARS = 5;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(length: 128)]
    private string $rater;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $stars;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Product $product, string $rater, int $stars)
    {
        $this->id = Uuid::v7();
        $this->product = $product;
        $this->rater = $rater;
        $this->createdAt = new \DateTimeImmutable();
        $this->rate($stars);
    }

    public function rate(int $stars): void
    {
        if ($stars < self::MIN_STARS || $stars > self::MAX_STARS) {
            throw new \InvalidArgumentException(sprintf('Stars must be between %d and %d.', self::MIN_STARS, self::MAX_STARS));
        }
        $this->stars = $stars;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getRater(): string
    {
        return $this->rater;
    }

    public function getStars(): int
    {
        return $this->stars;
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
