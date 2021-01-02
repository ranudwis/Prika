<?php

namespace App\Service\Security;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\RefreshToken;
use App\Entity\User;
use DateTime;

class RefreshTokenCreator
{
    private $enittyManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Create refresh token and store it to database
     * @param  User         $user User to generate refresh token
     * @return RefreshToken       Refresh token entity object
     */
    public function create(User $user): RefreshToken
    {
        $refreshTokenString = $this->generateRefreshToken();
        $validUntil = $this->getTokenExpirationDate();

        $refreshToken = new RefreshToken();
        $refreshToken->setRefreshToken($refreshTokenString);
        $refreshToken->setUser($user);
        $refreshToken->setValidUntil($validUntil);

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return $refreshToken;
    }

    /**
     * Generate ramdon string to be used as refresh token
     * @return string Generated refresh token
     */
    private function generateRefreshToken(): string
    {
        return bin2hex(openssl_random_pseudo_bytes(64));
    }

    /**
     * Create expiration date which is one year from current time
     * @return DateTime Token expiration date
     */
    private function getTokenExpirationDate(): DateTime
    {
        return new DateTime('next year');
    }
}
