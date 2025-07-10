<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    /**
     * @Route("/test-route", name="test.route", methods={"GET"})
     */
    public function testRoute(): JsonResponse
    {
        return new JsonResponse(['message' => 'Test route works!']);
    }
}
