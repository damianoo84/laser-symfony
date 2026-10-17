<?php

declare(strict_types=1);

namespace App\Command;

use App\Mail\InquiryNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:mail:test', description: 'Wysyła wiadomość testową na aktualnych odbiorców z panelu')]
final class MailTestCommand extends Command
{
    public function __construct(private InquiryNotifier $notifier)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configuration = $this->notifier->configuration();
        if (!$configuration['ready']) {
            $output->writeln('<error>' . $configuration['message'] . '</error>');
            return Command::FAILURE;
        }
        if (!$this->notifier->sendTest()) {
            $output->writeln('<error>Nie udało się wysłać testu. Sprawdź host, port, logowanie, TLS i dozwolony adres nadawcy.</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>Wiadomość testowa przekazana do SMTP. Sprawdź skrzynki odbiorców (również spam).</info>');
        return Command::SUCCESS;
    }
}
