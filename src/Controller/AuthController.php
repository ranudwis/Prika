<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use App\Repository\UserRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AuthController extends AbstractController
{
    private $repository;
    private $entityManager;

    public function __construct(UserRepository $repository, EntityManagerInterface $entityManager)
    {
        $this->repository = $repository;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/auth", methods={"GET"})
     */
    public function connectAzure(ClientRegistry $clientRegistry)
    {
        return $clientRegistry
            ->getClient('azure')
            ->redirect([
                'openid', 'profile', 'email', 'User.Read'
            ]);
    }

    /**
     * @Route("/auth/check", methods={"GET"}, name="connect_azure_check")
     */
    public function checkAzure(Request $request, ClientRegistry $clientRegistry, JWTTokenManagerInterface $JWTManager)
    {
        $client = $clientRegistry->getClient('azure');

        try {
            $accessToken = $client->getAccessToken();
            $graph = new Graph();
            $graph->setAccessToken($accessToken);

            $microsoftUser = $graph->createRequest('GET', '/me')
                ->setReturnType(Model\User::class)
                ->execute();

            $microsoftId = $microsoftUser->getId();
            $email = $microsoftUser->getMail();
            $studentId = $microsoftUser->getSurname();

            $user = $this->repository->findWithMicrosoftIdEmailOrStudentId($microsoftId, $email, $studentId);

            if (! $user) {
                $user = new User();
                $this->entityManager->persist($user);
            }

            $user->setName($microsoftUser->getDisplayName());
            $user->setStudentId($studentId);
            $user->setEmail($email);
            $user->setMicrosoftId($microsoftId);
            $user->addRole(User::ROLE_STUDENT);

            $this->entityManager->flush();

            var_dump($JWTManager->create($user)); die;
        } catch (IdentityProviderException $e) {
            var_dump($e->getMessage()); die;
        }
    }
}
