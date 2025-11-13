<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin-user',
    description: 'Create an admin user for the application',
)]
class CreateAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, 'Username for the admin user')
            ->addArgument('email', InputArgument::OPTIONAL, 'Email for the admin user')
            ->addArgument('password', InputArgument::OPTIONAL, 'Password for the admin user')
            ->addOption('first-name', null, InputOption::VALUE_OPTIONAL, 'First name of the admin user')
            ->addOption('last-name', null, InputOption::VALUE_OPTIONAL, 'Last name of the admin user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Create Admin User');

        // Mode interactif si les arguments ne sont pas fournis
        $username = $input->getArgument('username');
        if (!$username) {
            $username = $io->ask('Username', null, function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Username cannot be empty');
                }
                $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $answer]);
                if ($existingUser) {
                    throw new \RuntimeException('A user with this username already exists!');
                }
                return $answer;
            });
        }

        $email = $input->getArgument('email');
        if (!$email) {
            $email = $io->ask('Email', null, function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Email cannot be empty');
                }
                if (!filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Invalid email format');
                }
                $existingEmail = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $answer]);
                if ($existingEmail) {
                    throw new \RuntimeException('A user with this email already exists!');
                }
                return $answer;
            });
        }

        $password = $input->getArgument('password');
        if (!$password) {
            $password = $io->askHidden('Password (min 8 characters)', function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Password cannot be empty');
                }
                if (strlen($answer) < 8) {
                    throw new \RuntimeException('Password must be at least 8 characters long');
                }
                return $answer;
            });

            $passwordConfirm = $io->askHidden('Confirm password', function ($answer) use ($password) {
                if ($answer !== $password) {
                    throw new \RuntimeException('Passwords do not match');
                }
                return $answer;
            });
        }

        $firstName = $input->getOption('first-name');
        if (!$firstName) {
            $firstName = $io->ask('First name (optional)', '');
        }

        $lastName = $input->getOption('last-name');
        if (!$lastName) {
            $lastName = $io->ask('Last name (optional)', '');
        }

        // Validation finale pour le mode non-interactif
        if ($input->getArgument('username')) {
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
            if ($existingUser) {
                $io->error('A user with this username already exists!');
                return Command::FAILURE;
            }

            $existingEmail = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingEmail) {
                $io->error('A user with this email already exists!');
                return Command::FAILURE;
            }

            if (strlen($password) < 8) {
                $io->error('Password must be at least 8 characters long');
                return Command::FAILURE;
            }
        }

        // Create new user
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsActive(true);
        $user->setCreatedAt(new \DateTimeImmutable());

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Save user
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Admin user created successfully!');
        $io->table(['Field', 'Value'], [
            ['Username', $username],
            ['Email', $email],
            ['First Name', $firstName ?: 'N/A'],
            ['Last Name', $lastName ?: 'N/A'],
            ['Roles', 'ROLE_ADMIN'],
            ['Active', 'Yes'],
        ]);

        return Command::SUCCESS;
    }
}