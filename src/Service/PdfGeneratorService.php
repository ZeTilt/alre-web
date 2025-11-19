<?php

namespace App\Service;

use App\Entity\Devis;
use App\Entity\Facture;
use TCPDF;
use Spatie\Browsershot\Browsershot;
use Twig\Environment;

class PdfGeneratorService
{
    private string $projectDir;
    private CompanyService $companyService;
    private Environment $twig;

    // Couleurs de la charte graphique
    private const COLOR_PRIMARY = '#3A4556'; // Anthracite
    private const COLOR_ACCENT = '#27A3B4';  // Turquoise
    private const COLOR_TEXT = '#333333';
    private const COLOR_LIGHT_BG = '#F8F9FA';
    private const COLOR_BORDER = '#E0E0E0';

    public function __construct(string $projectDir, CompanyService $companyService, Environment $twig)
    {
        $this->projectDir = $projectDir;
        $this->companyService = $companyService;
        $this->twig = $twig;
    }

    public function generateDevisPdf(Devis $devis): string
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Alré Web');
        $pdf->SetAuthor('Alré Web - Fabrice DHUICQUE');
        $pdf->SetTitle('Devis ' . $devis->getNumber());
        $pdf->SetSubject('Devis');

        // Configuration
        $pdf->SetDefaultMonospacedFont('courier');
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Supprimer header/footer par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $html = $this->generateDevisHtml($devis);
        $pdf->writeHTML($html, true, false, true, false, '');

        // Génération du nom de fichier
        $filename = sprintf(
            '%s-Devis-%s-%s.pdf',
            $devis->getDateCreation()->format('Y-m-d'),
            $devis->getNumber(),
            $this->sanitizeFilename($devis->getClient()->getName())
        );

        $outputDir = $this->projectDir . '/var/pdf/devis/' . $devis->getDateCreation()->format('Y');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filepath = $outputDir . '/' . $filename;
        $pdf->Output($filepath, 'F');

        return $filepath;
    }

    public function generateFacturePdf(Facture $facture): string
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Alré Web');
        $pdf->SetAuthor('Alré Web - Fabrice DHUICQUE');
        $pdf->SetTitle('Facture ' . $facture->getNumber());
        $pdf->SetSubject('Facture');

        // Configuration
        $pdf->SetDefaultMonospacedFont('courier');
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Supprimer header/footer par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $html = $this->generateFactureHtml($facture);
        $pdf->writeHTML($html, true, false, true, false, '');

        // Génération du nom de fichier
        $filename = sprintf(
            '%s-Facture-%s-%s.pdf',
            $facture->getDateFacture()->format('Y-m-d'),
            $facture->getNumber(),
            $this->sanitizeFilename($facture->getClient()->getName())
        );

        $outputDir = $this->projectDir . '/var/pdf/factures/' . $facture->getDateFacture()->format('Y');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filepath = $outputDir . '/' . $filename;
        $pdf->Output($filepath, 'F');

        return $filepath;
    }

    private function generateDevisHtml(Devis $devis): string
    {
        $client = $devis->getClient();
        $items = $devis->getItems();
        $company = $this->companyService->getCompanyOrDefault();
        $logoPath = $this->projectDir . '/public/images/logo.png';

        $html = '
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: "Helvetica", Arial, sans-serif; font-size: 10px; color: ' . self::COLOR_TEXT . '; line-height: 1.5; }

            /* En-tête */
            .header-wrapper { margin-bottom: 30px; border-bottom: 2px solid ' . self::COLOR_ACCENT . '; padding-bottom: 15px; }
            .header-table { width: 100%; }
            .header-flex { display: flex; justify-content: space-between; }
            .logo-cell { width: 30%; }
            .logo { max-width: 140px; height: auto; }
            .company-info { font-size: 9px; line-height: 1.6; color: #666; text-align: left; margin-top: 2rem; }

            /* Bloc document à droite */
            .doc-cell { width: 70%; text-align: right; }
            .doc-title { background: linear-gradient(135deg, ' . self::COLOR_PRIMARY . ' 0%, ' . self::COLOR_ACCENT . ' 100%); color: white; padding: 12px 20px; font-size: 20px; font-weight: bold; letter-spacing: 3px; border-radius: 4px; margin-bottom: 12px; }
            .doc-info-table { width: 100%; border-collapse: collapse; border: 1px solid ' . self::COLOR_BORDER . '; }
            .doc-info-table td { padding: 8px 10px; font-size: 9px; text-align: left; }
            .doc-label { background-color: ' . self::COLOR_LIGHT_BG . '; font-weight: bold; color: ' . self::COLOR_PRIMARY . '; width: 40%; border-right: 1px solid ' . self::COLOR_BORDER . '; }
            .doc-value { background-color: white; }

            /* Client */
            .client-wrapper { margin: 25px 0; padding: 15px; background-color: ' . self::COLOR_LIGHT_BG . '; border: 1px solid ' . self::COLOR_BORDER . '; border-radius: 4px; }
            .client-label { font-size: 9px; font-weight: bold; color: ' . self::COLOR_ACCENT . '; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 1px; }
            .client-info { font-size: 10px; line-height: 1.7; }
            .client-name { font-size: 12px; font-weight: bold; color: ' . self::COLOR_PRIMARY . '; margin-bottom: 4px; }

            /* Objet */
            .object-wrapper { margin: 20px 0; }
            .object-label { font-size: 9px; font-weight: bold; color: ' . self::COLOR_PRIMARY . '; text-transform: uppercase; margin-bottom: 6px; }
            .object-text { font-size: 11px; padding: 10px; background-color: white; border: 1px solid ' . self::COLOR_BORDER . '; border-radius: 4px; }

            /* Tableau des lignes */
            .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; border: 1px solid ' . self::COLOR_BORDER . '; }
            .items-table thead th { background: ' . self::COLOR_PRIMARY . '; color: white; padding: 12px 15px; text-align: center; font-size: 9px; font-weight: bold; border-right: 1px solid rgba(255,255,255,0.2); border-bottom: 2px solid ' . self::COLOR_ACCENT . '; }
            .items-table thead th:last-child { border-right: none; text-align: right; }
            .items-table thead th:first-child { text-align: left; }
            .items-table tbody td { padding: 12px 15px; border-bottom: 1px solid ' . self::COLOR_BORDER . '; border-right: 1px solid ' . self::COLOR_BORDER . '; font-size: 9px; vertical-align: top; }
            .items-table tbody td:last-child { border-right: none; }
            .items-table tbody tr:nth-child(even) { background-color: ' . self::COLOR_LIGHT_BG . '; }
            .items-table tbody td:first-child { text-align: left; font-size: 10px; }
            .items-table tbody td:not(:first-child) { text-align: center; }
            .items-table tbody td:last-child { text-align: right; font-weight: bold; }

            /* Totaux */
            .totals-wrapper { margin: 25px 0 10px auto; width: 50%; min-width: 280px; }
            .totals-table { width: 100%; border-collapse: collapse; }
            .totals-table td { padding: 8px 12px; font-size: 10px; }
            .totals-table .label { text-align: right; color: #666; }
            .totals-table .value { text-align: right; font-weight: bold; width: 35%; }
            .total-ht-row { border-top: 1px solid ' . self::COLOR_BORDER . '; }
            .total-ht-row td { padding-top: 12px; font-size: 11px; }
            .tva-row td { color: #666; font-size: 9px; padding: 6px 12px; }
            .tva-notice { text-align: center; padding: 10px; background-color: ' . self::COLOR_LIGHT_BG . '; border-radius: 4px; margin: 15px 0; font-size: 9px; font-style: italic; color: #666; }
            .total-final-row { background: linear-gradient(135deg, ' . self::COLOR_PRIMARY . ' 0%, ' . self::COLOR_ACCENT . ' 100%); color: white; }
            .total-final-row td { padding: 14px 12px; font-size: 13px; font-weight: bold; letter-spacing: 1px; }

            /* Acompte */
            .acompte-wrapper { margin: 15px 0; padding: 12px; background-color: #FFF3CD; border-left: 4px solid #FFC107; border-radius: 0 4px 4px 0; }
            .acompte-label { font-size: 9px; font-weight: bold; color: #856404; margin-bottom: 4px; }
            .acompte-value { font-size: 12px; font-weight: bold; color: #856404; }

            /* Conditions */
            .conditions-wrapper { margin: 30px 0; padding: 15px; background-color: ' . self::COLOR_LIGHT_BG . '; border-radius: 4px; border: 1px solid ' . self::COLOR_BORDER . '; }
            .conditions-title { font-size: 10px; font-weight: bold; color: ' . self::COLOR_PRIMARY . '; margin-bottom: 8px; text-transform: uppercase; }
            .conditions-text { font-size: 9px; line-height: 1.7; color: #555; }

            /* Footer */
            .footer { margin-top: 40px; padding-top: 15px; border-top: 1px solid ' . self::COLOR_BORDER . '; text-align: center; font-size: 8px; color: #999; line-height: 1.6; }
        </style>';

        // En-tête
        $html .= '
        <div class="header-wrapper header-flex">
                    <span class="logo-cell">
                        <img src="' . $logoPath . '" class="logo" alt="Alré Web">
                        <div class="company-info">
                            <strong>' . htmlspecialchars($company->getOwnerName()) . '</strong><br>
                            ' . htmlspecialchars($company->getAddress()) . '<br>
                            ' . htmlspecialchars($company->getPostalCode()) . ' ' . htmlspecialchars($company->getCity()) . '<br>
                            <strong>Email:</strong> ' . htmlspecialchars($company->getEmail()) . '<br>
                            <strong>Tél:</strong> ' . htmlspecialchars($company->getPhone()) . '<br>
                            <strong>SIRET:</strong> ' . htmlspecialchars($company->getSiret()) . '
                        </div>
                    </span>
                    <span class="doc-cell">
                        <div class="doc-title">DEVIS</div>
                        <table class="doc-info-table">
                            <tbody><tr>
                                <td class="doc-label">Numéro</td>
                                <td class="doc-value"><strong>' . htmlspecialchars($devis->getNumber()) . '</strong></td>
                            </tr>
                            <tr>
                                <td class="doc-label">Date</td>
                                <td class="doc-value">' . $devis->getDateCreation()->format('d/m/Y') . '</td>
                            </tr>
                            <tr>
                                <td class="doc-label">Validité</td>
                                <td class="doc-value">' . $devis->getDateValidite()->format('d/m/Y') . '</td>
                            </tr>
                            </tbody></table>

                    </span>
        </div>';

        // Client
        $html .= '
        <div class="client-wrapper">
            <div class="client-label">Client</div>
            <div class="client-info">';

        if ($client->getCompanyName()) {
            $html .= '<div class="client-name">' . htmlspecialchars($client->getCompanyName()) . '</div>';
        }

        $html .= '<div class="client-name">' . htmlspecialchars($client->getName()) . '</div>
                ' . htmlspecialchars($client->getAddress()) . '<br>
                ' . htmlspecialchars($client->getPostalCode()) . ' ' . htmlspecialchars($client->getCity());

        if ($client->getEmail()) {
            $html .= '<br><strong>Email:</strong> ' . htmlspecialchars($client->getEmail());
        }
        if ($client->getPhone()) {
            $html .= '<br><strong>Tél:</strong> ' . htmlspecialchars($client->getPhone());
        }

        $html .= '</div></div>';

        // Objet
        if ($devis->getTitle()) {
            $html .= '
            <div class="object-wrapper">
                <div class="object-label">Objet</div>
                <div class="object-text">' . htmlspecialchars($devis->getTitle()) . '</div>
            </div>';
        }

        // Vérifier s'il y a des remises
        $hasDiscount = false;
        foreach ($items as $item) {
            if ($item->getDiscount() && $item->getDiscount() > 0) {
                $hasDiscount = true;
                break;
            }
        }

        // Lignes
        $html .= '
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: ' . ($hasDiscount ? '48%' : '55%') . ';">Désignation</th>
                    <th style="width: ' . ($hasDiscount ? '13%' : '15%') . ';">Prix unit. HT</th>
                    <th style="width: ' . ($hasDiscount ? '13%' : '15%') . ';">Quantité</th>';

        if ($hasDiscount) {
            $html .= '<th style="width: 13%;">Remise</th>';
        }

        $html .= '<th style="width: ' . ($hasDiscount ? '13%' : '15%') . ';">Total HT</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($items as $item) {
            $html .= '<tr>
                <td>' . nl2br(htmlspecialchars($item->getDescription())) . '</td>
                <td>' . number_format((float)$item->getUnitPrice(), 2, ',', ' ') . ' €</td>
                <td>' . number_format((float)$item->getQuantity(), 2, ',', ' ') . '</td>';

            if ($hasDiscount) {
                $discountText = ($item->getDiscount() && $item->getDiscount() > 0)
                    ? number_format((float)$item->getDiscount(), 0) . '%'
                    : '-';
                $html .= '<td>' . $discountText . '</td>';
            }

            $html .= '<td>' . number_format($item->getTotalAfterDiscount(), 2, ',', ' ') . ' €</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        // Totaux
        $vatRate = (float)$devis->getVatRate();
        $totalHt = (float)$devis->getTotalHt();
        $vatAmount = $totalHt * ($vatRate / 100);
        $totalTtc = $totalHt + $vatAmount;

        $html .= '<div class="totals-wrapper"><table class="totals-table">';
        $html .= '<tr class="total-ht-row">
            <td class="label">Total HT</td>
            <td class="value">' . number_format($totalHt, 2, ',', ' ') . ' €</td>
        </tr>';

        if ($vatRate > 0) {
            $html .= '<tr class="tva-row">
                <td class="label">TVA (' . number_format($vatRate, 0) . '%)</td>
                <td class="value">' . number_format($vatAmount, 2, ',', ' ') . ' €</td>
            </tr>';
            $html .= '<tr class="total-ht-row">
                <td class="label">Total TTC</td>
                <td class="value">' . number_format($totalTtc, 2, ',', ' ') . ' €</td>
            </tr>';
        }

        // Acompte
        if ($devis->getAcompte() && $devis->getAcompte() > 0) {
            $acompte = (float)$devis->getAcompte();
            $finalTotal = ($vatRate > 0 ? $totalTtc : $totalHt);
            $restant = $finalTotal - $acompte;

            $html .= '<tr class="tva-row">
                <td class="label">Acompte</td>
                <td class="value">- ' . number_format($acompte, 2, ',', ' ') . ' €</td>
            </tr>';
            $html .= '<tr class="total-final-row">
                <td class="label">RESTE À PAYER</td>
                <td class="value">' . number_format($restant, 2, ',', ' ') . ' €</td>
            </tr>';
        } else {
            $finalTotal = ($vatRate > 0 ? $totalTtc : $totalHt);
            $html .= '<tr class="total-final-row">
                <td class="label">TOTAL</td>
                <td class="value">' . number_format($finalTotal, 2, ',', ' ') . ' €</td>
            </tr>';
        }

        $html .= '</table></div>';

        // Conditions
        if ($devis->getConditions()) {
            $html .= '
            <div class="conditions-wrapper">
                <div class="conditions-title">Conditions</div>
                <div class="conditions-text">' . nl2br(htmlspecialchars($devis->getConditions())) . '</div>
            </div>';
        }

        // Footer
        $html .= '
        <div class="footer">
            Devis valable jusqu\'au ' . $devis->getDateValidite()->format('d/m/Y') . ' - Devis non contractuel<br>
            ' . htmlspecialchars($company->getName()) . ' - SIRET: ' . htmlspecialchars($company->getSiret()) . '<br>';

        if ($vatRate == 0) {
            $html .= 'TVA non applicable, art. 293 B du CGI - ';
        }

        $html .= 'Dispensé d\'immatriculation au registre du commerce et des sociétés (RCS) et au répertoire des métiers (RM)
        </div>';

        return $html;
    }

    private function generateFactureHtml(Facture $facture): string
    {
        $client = $facture->getClient();
        $items = $facture->getItems();
        $company = $this->companyService->getCompanyOrDefault();
        $logoPath = $this->projectDir . '/public/images/logo.png';

        $html = '
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: "Helvetica", Arial, sans-serif; font-size: 10px; color: ' . self::COLOR_TEXT . '; line-height: 1.5; }

            /* En-tête */
            .header-wrapper { margin-bottom: 30px; border-bottom: 2px solid ' . self::COLOR_ACCENT . '; padding-bottom: 15px; }
            .header-table { width: 100%; }
            .header-flex { display: flex; justify-content: space-between; }
            .logo-cell { width: 30%; }
            .logo { max-width: 140px; height: auto; }
            .company-info { font-size: 9px; line-height: 1.6; color: #666; text-align: left; margin-top: 2rem; }

            /* Bloc document à droite */
            .doc-cell { width: 70%; text-align: right; }
            .doc-title { background: linear-gradient(135deg, ' . self::COLOR_PRIMARY . ' 0%, ' . self::COLOR_ACCENT . ' 100%); color: white; padding: 12px 20px; font-size: 20px; font-weight: bold; letter-spacing: 3px; border-radius: 4px; margin-bottom: 12px; }
            .doc-info-table { width: 100%; border-collapse: collapse; border: 1px solid ' . self::COLOR_BORDER . '; }
            .doc-info-table td { padding: 8px 10px; font-size: 9px; text-align: left; }
            .doc-label { background-color: ' . self::COLOR_LIGHT_BG . '; font-weight: bold; color: ' . self::COLOR_PRIMARY . '; width: 40%; border-right: 1px solid ' . self::COLOR_BORDER . '; }
            .doc-value { background-color: white; }

            /* Client */
            .client-wrapper { margin: 25px 0; padding: 15px; background-color: ' . self::COLOR_LIGHT_BG . '; border: 1px solid ' . self::COLOR_BORDER . '; border-radius: 4px; }
            .client-label { font-size: 9px; font-weight: bold; color: ' . self::COLOR_ACCENT . '; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 1px; }
            .client-info { font-size: 10px; line-height: 1.7; }
            .client-name { font-size: 12px; font-weight: bold; color: ' . self::COLOR_PRIMARY . '; margin-bottom: 4px; }

            /* Objet */
            .object-wrapper { margin: 20px 0; }
            .object-label { font-size: 9px; font-weight: bold; color: ' . self::COLOR_PRIMARY . '; text-transform: uppercase; margin-bottom: 6px; }
            .object-text { font-size: 11px; padding: 10px; background-color: white; border: 1px solid ' . self::COLOR_BORDER . '; border-radius: 4px; }

            /* Tableau des lignes */
            .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; border: 1px solid ' . self::COLOR_BORDER . '; }
            .items-table thead th { background: ' . self::COLOR_PRIMARY . '; color: white; padding: 12px 15px; text-align: center; font-size: 9px; font-weight: bold; border-right: 1px solid rgba(255,255,255,0.2); border-bottom: 2px solid ' . self::COLOR_ACCENT . '; }
            .items-table thead th:last-child { border-right: none; text-align: right; }
            .items-table thead th:first-child { text-align: left; }
            .items-table tbody td { padding: 12px 15px; border-bottom: 1px solid ' . self::COLOR_BORDER . '; border-right: 1px solid ' . self::COLOR_BORDER . '; font-size: 9px; vertical-align: top; }
            .items-table tbody td:last-child { border-right: none; }
            .items-table tbody tr:nth-child(even) { background-color: ' . self::COLOR_LIGHT_BG . '; }
            .items-table tbody td:first-child { text-align: left; font-size: 10px; }
            .items-table tbody td:not(:first-child) { text-align: center; }
            .items-table tbody td:last-child { text-align: right; font-weight: bold; }

            /* Totaux */
            .totals-wrapper { margin: 25px 0 10px auto; width: 50%; min-width: 280px; }
            .totals-table { width: 100%; border-collapse: collapse; }
            .totals-table td { padding: 8px 12px; font-size: 10px; }
            .totals-table .label { text-align: right; color: #666; }
            .totals-table .value { text-align: right; font-weight: bold; width: 35%; }
            .total-ht-row { border-top: 1px solid ' . self::COLOR_BORDER . '; }
            .total-ht-row td { padding-top: 12px; font-size: 11px; }
            .tva-row td { color: #666; font-size: 9px; padding: 6px 12px; }
            .tva-notice { text-align: center; padding: 10px; background-color: ' . self::COLOR_LIGHT_BG . '; border-radius: 4px; margin: 15px 0; font-size: 9px; font-style: italic; color: #666; }
            .total-final-row { background: linear-gradient(135deg, ' . self::COLOR_PRIMARY . ' 0%, ' . self::COLOR_ACCENT . ' 100%); color: white; }
            .total-final-row td { padding: 14px 12px; font-size: 13px; font-weight: bold; letter-spacing: 1px; }

            /* Conditions paiement */
            .payment-wrapper { margin: 30px 0; padding: 15px; background-color: #FFF3CD; border-left: 4px solid #FFC107; border-radius: 0 4px 4px 0; }
            .payment-title { font-size: 10px; font-weight: bold; color: #856404; margin-bottom: 10px; text-transform: uppercase; }
            .payment-text { font-size: 9px; line-height: 1.8; color: #856404; }

            /* Footer */
            .footer { margin-top: 40px; padding-top: 15px; border-top: 1px solid ' . self::COLOR_BORDER . '; text-align: center; font-size: 8px; color: #999; line-height: 1.6; }
        </style>';

        // En-tête
        $html .= '
        <div class="header-wrapper">
            <table class="header-table">
                <tbody><div style="display:flex;justify-content:space-between">
                    <span class="logo-cell">
                        <img src="' . $logoPath . '" class="logo" alt="Alré Web">
                    <div class="company-info" style="margin-top:2rem;">
                            <strong>' . htmlspecialchars($company->getOwnerName()) . '</strong><br>
                            ' . htmlspecialchars($company->getAddress()) . '<br>
                            ' . htmlspecialchars($company->getPostalCode()) . ' ' . htmlspecialchars($company->getCity()) . '<br>
                            <strong>Email:</strong> ' . htmlspecialchars($company->getEmail()) . '<br>
                            <strong>Tél:</strong> ' . htmlspecialchars($company->getPhone()) . '<br>
                            <strong>SIRET:</strong> ' . htmlspecialchars($company->getSiret()) . '
                        </div></span>
                    <span class="doc-cell">
                        <div class="doc-title">FACTURE</div>
                        <table class="doc-info-table">
                            <tbody><tr>
                                <td class="doc-label">Numéro</td>
                                <td class="doc-value"><strong>' . htmlspecialchars($facture->getNumber()) . '</strong></td>
                            </tr>
                            <tr>
                                <td class="doc-label">Date</td>
                                <td class="doc-value">' . $facture->getDateFacture()->format('d/m/Y') . '</td>
                            </tr>
                            <tr>
                                <td class="doc-label">Échéance</td>
                                <td class="doc-value">' . $facture->getDateEcheance()->format('d/m/Y') . '</td>
                            </tr>';

        if ($facture->getDevis()) {
            $html .= '<tr>
                <td class="doc-label">Devis</td>
                <td class="doc-value">' . htmlspecialchars($facture->getDevis()->getNumber()) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>

                    </span>
                </div>
                </tbody></table>
        </div>';

        // Client
        $html .= '
        <div class="client-wrapper">
            <div class="client-label">Client</div>
            <div class="client-info">';

        if ($client->getCompanyName()) {
            $html .= '<div class="client-name">' . htmlspecialchars($client->getCompanyName()) . '</div>';
        }

        $html .= '<div class="client-name">' . htmlspecialchars($client->getName()) . '</div>
                ' . htmlspecialchars($client->getAddress()) . '<br>
                ' . htmlspecialchars($client->getPostalCode()) . ' ' . htmlspecialchars($client->getCity());

        if ($client->getEmail()) {
            $html .= '<br><strong>Email:</strong> ' . htmlspecialchars($client->getEmail());
        }
        if ($client->getPhone()) {
            $html .= '<br><strong>Tél:</strong> ' . htmlspecialchars($client->getPhone());
        }

        $html .= '</div></div>';

        // Objet
        if ($facture->getTitle()) {
            $html .= '
            <div class="object-wrapper">
                <div class="object-label">Objet</div>
                <div class="object-text">' . htmlspecialchars($facture->getTitle()) . '</div>
            </div>';
        }

        // Vérifier s'il y a des remises
        $hasDiscount = false;
        foreach ($items as $item) {
            if ($item->getDiscount() && $item->getDiscount() > 0) {
                $hasDiscount = true;
                break;
            }
        }

        // Lignes
        $html .= '
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">N°</th>
                    <th style="width: ' . ($hasDiscount ? '43%' : '47%') . ';">Désignation</th>
                    <th style="width: 10%;">Qté</th>
                    <th style="width: 10%;">Unité</th>
                    <th style="width: ' . ($hasDiscount ? '12%' : '14%') . ';">Prix unit. HT</th>';

        if ($hasDiscount) {
            $html .= '<th style="width: 8%;">Remise</th>';
        }

        $html .= '<th style="width: ' . ($hasDiscount ? '12%' : '14%') . ';">Total HT</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($items as $item) {
            $html .= '<tr>
                <td>' . $item->getPosition() . '</td>
                <td>' . nl2br(htmlspecialchars($item->getDescription())) . '</td>
                <td>' . number_format((float)$item->getQuantity(), 2, ',', ' ') . '</td>
                <td>' . htmlspecialchars($item->getUnit() ?: '-') . '</td>
                <td>' . number_format((float)$item->getUnitPrice(), 2, ',', ' ') . ' €</td>';

            if ($hasDiscount) {
                $discountText = ($item->getDiscount() && $item->getDiscount() > 0)
                    ? number_format((float)$item->getDiscount(), 0) . '%'
                    : '-';
                $html .= '<td>' . $discountText . '</td>';
            }

            $html .= '<td>' . number_format($item->getTotalAfterDiscount(), 2, ',', ' ') . ' €</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        // Totaux
        $vatRate = (float)$facture->getVatRate();
        $totalHt = (float)$facture->getTotalHt();
        $vatAmount = $totalHt * ($vatRate / 100);
        $totalTtc = $totalHt + $vatAmount;

        $html .= '<div class="totals-wrapper"><table class="totals-table">';
        $html .= '<tr class="total-ht-row">
            <td class="label">Total HT</td>
            <td class="value">' . number_format($totalHt, 2, ',', ' ') . ' €</td>
        </tr>';

        if ($vatRate > 0) {
            $html .= '<tr class="tva-row">
                <td class="label">TVA (' . number_format($vatRate, 0) . '%)</td>
                <td class="value">' . number_format($vatAmount, 2, ',', ' ') . ' €</td>
            </tr>';
            $html .= '<tr class="total-ht-row">
                <td class="label">Total TTC</td>
                <td class="value">' . number_format($totalTtc, 2, ',', ' ') . ' €</td>
            </tr>';
        }

        $html .= '<tr class="total-final-row">
            <td class="label">TOTAL</td>
            <td class="value">' . number_format(($vatRate > 0 ? $totalTtc : $totalHt), 2, ',', ' ') . ' €</td>
        </tr>';

        $html .= '</table></div>';

        // Conditions de paiement
        $modePaiement = $facture->getModePaiement() ?: 'Virement bancaire';
        $html .= '
        <div class="payment-wrapper">
            <div class="payment-title">Conditions de règlement</div>
            <div class="payment-text">
                <strong>Mode de paiement:</strong> ' . htmlspecialchars($modePaiement) . '<br>
                <strong>Date d\'échéance:</strong> ' . $facture->getDateEcheance()->format('d/m/Y') . '<br>
                <strong>Pénalités de retard:</strong> 3 fois le taux d\'intérêt légal<br>
                <strong>Indemnité forfaitaire pour frais de recouvrement:</strong> 40 € (art. L441-10 du Code de commerce)';

        if ($facture->getConditions()) {
            $html .= '<br><br>' . nl2br(htmlspecialchars($facture->getConditions()));
        }

        $html .= '</div></div>';

        // Footer
        $html .= '
        <div class="footer">
            ' . htmlspecialchars($company->getName()) . ' - ' . htmlspecialchars($company->getOwnerName()) . '<br>
            SIRET: ' . htmlspecialchars($company->getSiret()) . ' - ' . htmlspecialchars($company->getLegalStatus() ?: 'Auto-entrepreneur') . '<br>
            ' . htmlspecialchars($company->getEmail()) . ' - ' . htmlspecialchars($company->getPhone()) . '<br>';

        if ($vatRate == 0) {
            $html .= 'TVA non applicable, art. 293 B du CGI - ';
        }

        $html .= 'Dispensé d\'immatriculation au registre du commerce et des sociétés (RCS) et au répertoire des métiers (RM)
        </div>';

        return $html;
    }

    private function sanitizeFilename(string $filename): string
    {
        // Convertir les caractères accentués en leur équivalent sans accent
        $transliterations = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ç' => 'C', 'ç' => 'c',
            'Ñ' => 'N', 'ñ' => 'n',
            'Ý' => 'Y', 'ý' => 'y', 'ÿ' => 'y',
            'Æ' => 'AE', 'æ' => 'ae',
            'Œ' => 'OE', 'œ' => 'oe'
        ];

        $filename = strtr($filename, $transliterations);
        $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $filename);
        $filename = preg_replace('/-+/', '-', $filename);
        return trim($filename, '-');
    }

    /**
     * Generate PDF from HTML template using Browsershot (Headless Chrome)
     */
    public function generateDevisPdfFromHtml(Devis $devis): string
    {
        $company = $this->companyService->getCompanyOrDefault();

        // Render HTML from Twig template
        $html = $this->twig->render('pdf/devis.html.twig', [
            'devis' => $devis,
            'company' => $company,
        ]);

        // Create output directory structure
        $year = $devis->getDateCreation()->format('Y');
        $outputDir = $this->projectDir . '/var/pdf/devis/' . $year;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Generate filename
        $clientName = $devis->getClient()->getCompanyName() ?: $devis->getClient()->getName();
        $date = $devis->getDateCreation()->format('Y-m-d');
        $filename = sprintf(
            '%s-Devis-%s-%s.pdf',
            $date,
            $devis->getNumber(),
            $this->sanitizeFilename($clientName)
        );
        $filepath = $outputDir . '/' . $filename;

        // Generate PDF using Browsershot
        Browsershot::html($html)
            ->noSandbox()
            ->format('A4')
            ->margins(15, 15, 15, 15)
            ->showBackground()
            ->savePdf($filepath);

        return $filepath;
    }

    /**
     * Generate Facture PDF from HTML template using Browsershot (Headless Chrome)
     */
    public function generateFacturePdfFromHtml(Facture $facture): string
    {
        $company = $this->companyService->getCompanyOrDefault();

        // Render HTML from Twig template
        $html = $this->twig->render('pdf/facture.html.twig', [
            'facture' => $facture,
            'company' => $company,
        ]);

        // Create output directory structure
        $year = $facture->getDateFacture()->format('Y');
        $outputDir = $this->projectDir . '/var/pdf/factures/' . $year;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Generate filename
        $clientName = $facture->getClient()->getCompanyName() ?: $facture->getClient()->getName();
        $date = $facture->getDateFacture()->format('Y-m-d');
        $filename = sprintf(
            '%s-Facture-%s-%s.pdf',
            $date,
            $facture->getNumber(),
            $this->sanitizeFilename($clientName)
        );
        $filepath = $outputDir . '/' . $filename;

        // Generate PDF using Browsershot
        Browsershot::html($html)
            ->noSandbox()
            ->format('A4')
            ->margins(15, 15, 15, 15)
            ->showBackground()
            ->savePdf($filepath);

        return $filepath;
    }
}
