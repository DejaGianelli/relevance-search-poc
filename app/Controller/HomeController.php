<?php
namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductRating;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

class HomeController extends AbstractController {

    public function __construct(Environment $twig, private EntityManagerInterface $em) {
        parent::__construct($twig);
    }

    private const PER_PAGE = 12;

    public function showHome() {
        [$products, $pagination] = $this->paginate(
            $this->em->getRepository(Product::class), ['active' => true], ['createdAt' => 'DESC', 'id' => 'DESC'], self::PER_PAGE,
        );

        return $this->render('index', [
            'products' => $products,
            'my_ratings' => $this->em->getRepository(ProductRating::class)->starsByRater(session_id(), $products),
            'pagination' => $pagination,
        ]);
    }
}
