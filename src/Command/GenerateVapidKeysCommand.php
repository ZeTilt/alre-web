<?php

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-vapid-keys',
    description: 'Generate VAPID keys for Web Push notifications',
)]
class GenerateVapidKeysCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Generate VAPID Keys for Web Push');

        $keys = VAPID::createVapidKeys();

        $io->success('VAPID keys generated successfully!');

        $io->section('Add these lines to your .env.local file:');
        $io->writeln('');
        $io->writeln('<info>###> web-push ###</info>');
        $io->writeln(sprintf('<comment>VAPID_SUBJECT=mailto:contact@alre-web.bzh</comment>'));
        $io->writeln(sprintf('<comment>VAPID_PUBLIC_KEY=%s</comment>', $keys['publicKey']));
        $io->writeln(sprintf('<comment>VAPID_PRIVATE_KEY=%s</comment>', $keys['privateKey']));
        $io->writeln('<info>###< web-push ###</info>');
        $io->writeln('');

        $io->warning('Keep your private key secret! Never commit it to version control.');

        return Command::SUCCESS;
    }
}
