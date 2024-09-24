<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        // permet la vérification de l'utilisateur avant l'authentification (si l'email est bien validée)
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Your email address has not been verified.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Ajout d'autres vérifications après l'authentification, si besoin.
    }
}
