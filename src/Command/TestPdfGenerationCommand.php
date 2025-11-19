<?php

namespace App\Command;

use App\Entity\Devis;
use App\Entity\Facture;
use App\Service\PdfGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-pdf',
    description: 'Test PDF generation for devis and factures',
)]
class TestPdfGenerationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PdfGeneratorService $pdfGenerator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing PDF Generation');

        // Find the first devis
        $devisRepo = $this->entityManager->getRepository(Devis::class);
        $devis = $devisRepo->findOneBy([], ['id' => 'ASC']);

        if (!$devis) {
            $io->error('No devis found in database');
            return Command::FAILURE;
        }

        $io->section('Devis Information');
        $io->table(
            ['Property', 'Value'],
            [
                ['Number', $devis->getNumber()],
                ['Client', $devis->getClient()->getName()],
                ['Total HT', number_format($devis->getTotalHt(), 2) . ' €'],
                ['Items', count($devis->getItems())],
                ['Status', $devis->getStatus()],
            ]
        );

        try {
            $io->section('Generating PDF');
            $io->text('Processing with new professional design...');

            $filepath = $this->pdfGenerator->generateDevisPdf($devis);

            $io->success('PDF generated successfully!');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Path', $filepath],
                    ['Size', $this->formatBytes(filesize($filepath))],
                    ['Exists', file_exists($filepath) ? '✅ Yes' : '❌ No'],
                ]
            );

            if (!file_exists($filepath) || filesize($filepath) == 0) {
                $io->error('Devis PDF file is empty or not created');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error('Error generating devis PDF: ' . $e->getMessage());
            $io->text('Stack trace:');
            $io->text($e->getTraceAsString());
            return Command::FAILURE;
        }

        // Test facture generation
        $factureRepo = $this->entityManager->getRepository(Facture::class);
        $facture = $factureRepo->findOneBy([], ['id' => 'ASC']);

        if ($facture) {
            $io->section('Facture Information');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Number', $facture->getNumber()],
                    ['Client', $facture->getClient()->getName()],
                    ['Total HT', number_format($facture->getTotalHt(), 2) . ' €'],
                    ['Items', count($facture->getItems())],
                    ['Status', $facture->getStatus()],
                ]
            );

            try {
                $io->section('Generating Facture PDF');
                $io->text('Processing with new professional design...');

                $filepath = $this->pdfGenerator->generateFacturePdf($facture);

                $io->success('Facture PDF generated successfully!');
                $io->table(
                    ['Property', 'Value'],
                    [
                        ['Path', $filepath],
                        ['Size', $this->formatBytes(filesize($filepath))],
                        ['Exists', file_exists($filepath) ? '✅ Yes' : '❌ No'],
                    ]
                );

                if (!file_exists($filepath) || filesize($filepath) == 0) {
                    $io->error('Facture PDF file is empty or not created');
                    return Command::FAILURE;
                }

            } catch (\Exception $e) {
                $io->error('Error generating facture PDF: ' . $e->getMessage());
                $io->text('Stack trace:');
                $io->text($e->getTraceAsString());
                return Command::FAILURE;
            }
        }

        $io->success('All PDF generation tests PASSED!');
        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
