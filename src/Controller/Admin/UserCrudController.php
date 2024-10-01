<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use PHPUnit\TextUI\XmlConfiguration\CodeCoverage\Report\Text;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            TextField::new('lastName'),
            TextField::new('email'),
            TextField::new('plainPassword')->setRequired(true)->onlyOnForms()->setLabel('Password'),
            ArrayField::new('roles')->setLabel('Roles'),
            BooleanField::new('isVerified'),
        ];
    }
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'hashPassword',
            BeforeEntityUpdatedEvent::class => 'hashPassword',
        ];
    }

    public function hashPassword($event)
    {
        $entity = $event->getEntityInstance();

        if (!($entity instanceof User)) {
            return;
        }

        if ($entity->getPlainPassword()) {
            $entity->setPassword(
                $this->passwordHasher->hashPassword(
                    $entity,
                    $entity->getPlainPassword()
                )
            );

            // On supprime le plainPassword après le hachage
            $entity->setPlainPassword(null);
        }
    }
}
