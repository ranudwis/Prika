<?php

namespace App\Controller;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Security\RefreshTokenCreator;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
    public function checkAzure(
        Request $request,
        ClientRegistry $clientRegistry,
        JWTTokenManagerInterface $JWTManager,
        RefreshTokenCreator $refreshTokenCreator
    ) {
        $client = $clientRegistry->getClient('azure');

        try {
            $microsoftUser = $this->getMicrosoftUser($client);

            $user = $this->checkExistingOrCreateUser($microsoftUser);

            $accessToken = $JWTManager->create($user);
            $refreshToken = $refreshTokenCreator->create($user);

            $cookie = $this->createRefreshTokenCookie($refreshToken);

            $response = $this->render('auth/token.html.twig', compact('accessToken'));
            $response->headers->setCookie($cookie);

            return $response;
        } catch (IdentityProviderException $e) {
            var_dump($e->getMessage()); die;
        }
    }

    /**
     * Create httpOnly cookie to store generated refresh token
     * @param  RefreshToken $refreshToken Generated refresh token
     * @return Cookie                     Generated cookie
     */
    private function createRefreshTokenCookie(RefreshToken $refreshToken): Cookie
    {
        return Cookie::create('prikaRefreshToken')
            ->withValue($refreshToken->getRefreshToken())
            ->withExpires($refreshToken->getValidUntil())
            ->withDomain($this->getParameter('app.url'))
            ->withHttpOnly(true);
    }

    /**
     * Check wether user already exist and return it or create new user also save the user to database
     * @param  ModelUser $microsoftUser Fetched Microsoft user
     * @return User                     Existing or new created user
     */
    private function checkExistingOrCreateUser(Model\User $microsoftUser): User
    {
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

        return $user;
    }

    /**
     * Fetch user info from access token aqcuired from oauth client
     * @param  [type]     $client Azure oauth client
     * @return ModelUser         Microsoft graph user model
     */
    private function getMicrosoftUser($client): Model\User
    {
        $accessToken = $client->getAccessToken();
        $graph = new Graph();
        $graph->setAccessToken($accessToken);

        $microsoftUser = $graph->createRequest('GET', '/me')
            ->setReturnType(Model\User::class)
            ->execute();

        return $microsoftUser;
    }
}
