<?php

namespace App\Service;

use App\Entity\Devis;
use App\Entity\Facture;
use TCPDF;

class PdfGeneratorService
{
    private string $projectDir;
    private CompanyService $companyService;

    public function __construct(string $projectDir, CompanyService $companyService)
    {
        $this->projectDir = $projectDir;
        $this->companyService = $companyService;
    }

    public function generateDevisPdf(Devis $devis): string
    {
        $pdf = new TCPDF();
        $pdf->SetCreator('ZETiLT');
        $pdf->SetAuthor('ZETiLT - Fabrice DHUICQUE');
        $pdf->SetTitle('Devis ' . $devis->getNumber());
        $pdf->SetSubject('Devis');

        // Set default font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Add page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        // Generate content
        $html = $this->generateDevisHtml($devis);
        $pdf->writeHTML($html, true, false, true, false, '');

        // Generate filename
        $filename = sprintf(
            '%s-%s-%s.pdf',
            $devis->getDateCreation()->format('Y-m-d'),
            $devis->getNumber(),
            $this->sanitizeFilename($devis->getClient()->getName())
        );

        // Create output directory if it doesn't exist
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
        $pdf = new TCPDF();
        $pdf->SetCreator('ZETiLT');
        $pdf->SetAuthor('ZETiLT - Fabrice DHUICQUE');
        $pdf->SetTitle('Facture ' . $facture->getNumber());
        $pdf->SetSubject('Facture');

        // Set default font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Add page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        // Generate content
        $html = $this->generateFactureHtml($facture);
        $pdf->writeHTML($html, true, false, true, false, '');

        // Generate filename
        $filename = sprintf(
            '%s-%s-%s.pdf',
            $facture->getDateFacture()->format('Y-m-d'),
            $facture->getNumber(),
            $this->sanitizeFilename($facture->getClient()->getName())
        );

        // Create output directory if it doesn't exist
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

        $html = '
        <style>
            body { font-family: Arial, sans-serif; font-size: 11px; line-height: 1.2; margin: 0; padding: 20px; }
            .header-table { width: 100%; margin-bottom: 40px; }
            .logo-cell { width: 15%; vertical-align: top; }
            .logo { width: 120px; height: auto; }
            .company-info { width: 35%; font-size: 11px; line-height: 1.3; vertical-align: top; padding-left: 10px; }
            .devis-section { width: 50%; vertical-align: top; text-align: right; }
            .devis-header { background-color: #FF6B35; color: white; padding: 12px; text-align: center; font-size: 18px; font-weight: bold; letter-spacing: 2px; margin-bottom: 15px; }
            .devis-details { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 30px; }
            .devis-details td { border: 1px solid #ddd; padding: 8px; }
            .devis-label { background-color: #FF6B35; color: white; font-weight: bold; width: 30%; }
            .client-section { margin-bottom: 40px; font-size: 11px; line-height: 1.3; }
            .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 11px; }
            .items-table th { background-color: #FF6B35; color: white; padding: 10px 8px; text-align: center; font-weight: bold; border: 1px solid #FF6B35; font-size: 10px; }
            .items-table td { padding: 12px 8px; border: 1px solid #ccc; text-align: center; vertical-align: middle; }
            .items-table td:first-child { text-align: left; }
            .totals-section { margin-top: 30px; text-align: right; margin-bottom: 20px; }
            .conditions { margin-top: 30px; font-size: 11px; margin-bottom: 30px; }
            .total-ht { font-size: 14px; font-weight: bold; margin-bottom: 15px; }
            .tva-notice { font-size: 11px; margin-bottom: 30px; text-align: center; }
            .acompte-section { margin-bottom: 20px; text-align: center; font-size: 12px; }
            .total-final { background-color: #FF6B35; color: white; padding: 12px; font-weight: bold; font-size: 14px; text-align: center; margin-top: 20px; letter-spacing: 1px; }
        </style>

        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <img src="' . $this->projectDir . '/public/images/logo.jpg" class="logo" alt="ZETiLT Logo">
                </td>
                <td class="company-info">
                    <strong style="font-size: 13px;">' . htmlspecialchars($company->getName()) . '</strong><br>
                    ' . htmlspecialchars($company->getOwnerName()) . '<br>
                    ' . htmlspecialchars($company->getAddress()) . '<br>
                    ' . htmlspecialchars($company->getPostalCode()) . ' ' . htmlspecialchars($company->getCity()) . '<br>
                    ' . htmlspecialchars($company->getPhone()) . '<br>
                    ' . htmlspecialchars($company->getEmail()) . '<br>
                    SIRET : ' . htmlspecialchars($company->getSiret()) . '
                </td>
                <td class="devis-section">
                    <div class="devis-header">D E V I S</div>
                    <table class="devis-details">
                        <tr>
                            <td class="devis-label">N°</td>
                            <td>' . htmlspecialchars($devis->getNumber()) . '</td>
                        </tr>
                        <tr>
                            <td class="devis-label">Date</td>
                            <td>' . $devis->getDateCreation()->format('d/m/Y') . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="client-section">';

        if ($client->getCompanyName()) {
            $html .= '<strong>' . htmlspecialchars($client->getCompanyName()) . '</strong><br>';
        }
        
        $html .= '<strong>' . htmlspecialchars($client->getName()) . '</strong><br>
            ' . htmlspecialchars($client->getAddress()) . '<br>
            ' . htmlspecialchars($client->getPostalCode()) . ' ' . htmlspecialchars($client->getCity()) . '<br>';

        if ($client->getEmail()) {
            $html .= htmlspecialchars($client->getEmail()) . '<br>';
        }

        $html .= '</div>';

        $html .= '<table class="items-table">
            <thead>
                <tr>
                    <th width="40%">Désignation du bien ou du service</th>
                    <th width="20%">Prix Unitaire Hors Taxes (Heure)</th>
                    <th width="20%">Quantité (Heures)</th>
                    <th width="20%">Montant Net Hors Taxes</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($items as $item) {
            $html .= '<tr>
                <td>' . nl2br(htmlspecialchars($item->getDescription())) . '</td>
                <td>' . number_format((float)$item->getUnitPrice(), 2, ',', ' ') . ' €</td>
                <td>' . number_format((float)$item->getQuantity(), 1, ',', ' ') . '</td>
                <td>' . number_format($item->getTotalAfterDiscount(), 2, ',', ' ') . ' €</td>
            </tr>';
        }

        $html .= '</tbody>
        </table>';

        if ($devis->getConditions()) {
            $html .= '<div class="conditions">
                <strong>Conditions de règlement :</strong><br>
                ' . nl2br(htmlspecialchars($devis->getConditions())) . '
            </div>';
        }

        $vatRate = (float)$devis->getVatRate();
        $totalHt = (float)$devis->getTotalHt();
        $vatAmount = $totalHt * ($vatRate / 100);
        $totalTtc = $totalHt + $vatAmount;

        $html .= '<div class="totals-section">
            <div class="total-ht">Total H.T&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . number_format($totalHt, 2, ',', ' ') . ' €</div>
        </div>';

        if ($vatRate > 0) {
            $html .= '<div class="totals-section" style="margin-top: 10px;">
                <div style="font-size: 12px; margin-bottom: 10px;">TVA (' . number_format($vatRate, 2, ',', ' ') . '%)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . number_format($vatAmount, 2, ',', ' ') . ' €</div>
            </div>';
        } else {
            $html .= '<div class="tva-notice">
                <strong>TVA non applicable, art. 293 B du CGI</strong>
            </div>';
        }

        $finalTotal = ($vatRate > 0) ? $totalTtc : $totalHt;

        if ($devis->getAcompte()) {
            $acompteAmount = (float)$devis->getAcompte();
            $restant = $finalTotal - $acompteAmount;

            $html .= '<div class="acompte-section">
                -&nbsp;&nbsp;&nbsp;&nbsp;<strong>Acompte</strong>&nbsp;&nbsp;&nbsp;&nbsp;-&nbsp;&nbsp;&nbsp;&nbsp;' . number_format($acompteAmount, 2, ',', ' ') . ' €
            </div>

            <div class="total-final">
                <strong>TOTAL RESTANT' . ($vatRate > 0 ? ' TTC' : '') . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . number_format($restant, 2, ',', ' ') . ' €</strong>
            </div>';
        } else {
            $html .= '<div class="total-final">
                <strong>TOTAL' . ($vatRate > 0 ? ' TTC' : '') . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . number_format($finalTotal, 2, ',', ' ') . ' €</strong>
            </div>';
        }

        return $html;
    }

    private function generateFactureHtml(Facture $facture): string
    {
        $client = $facture->getClient();
        $items = $facture->getItems();
        $company = $this->companyService->getCompanyOrDefault();

        $html = '
        <style>
            body { font-family: Arial, sans-serif; font-size: 11px; line-height: 1.4; }
            .header-table { width: 100%; margin-bottom: 20px; }
            .logo { height: 60px; }
            .company-header { text-align: left; vertical-align: top; }
            .company-name { font-size: 18px; font-weight: bold; color: #2B5F75; margin-bottom: 2px; }
            .company-tagline { font-size: 12px; color: #FF6B35; font-weight: bold; margin-bottom: 10px; }
            .company-details { font-size: 10px; line-height: 1.3; }
            .document-title { text-align: center; font-size: 20px; font-weight: bold; color: #2B5F75; margin: 20px 0; }
            .client-info { background-color: #f8f9fa; padding: 12px; border-left: 4px solid #2B5F75; margin-bottom: 15px; }
            .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10px; }
            .items-table th { background-color: #2B5F75; color: white; padding: 8px; text-align: left; font-weight: bold; }
            .items-table td { padding: 6px 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
            .items-table tr:nth-child(even) { background-color: #f8f9fa; }
            .totals-section { margin-top: 20px; }
            .totals-table { width: 50%; margin-left: auto; border-collapse: collapse; }
            .totals-table td { padding: 4px 8px; text-align: right; }
            .total-line { border-bottom: 1px solid #ddd; }
            .total-final { font-weight: bold; font-size: 12px; background-color: #2B5F75; color: white; }
            .conditions { margin-top: 30px; font-size: 9px; background-color: #f8f9fa; padding: 10px; }
            .payment-info { margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #FF6B35; }
        </style>

        <table class="header-table">
            <tr>
                <td width="15%">
                    <img src="' . $this->projectDir . '/public/images/logo.jpg" class="logo" alt="ZETiLT Logo">
                </td>
                <td width="35%" class="company-header">
                    <div class="company-name">' . htmlspecialchars($company->getName()) . '</div>
                    <div class="company-tagline">WEB & APPS</div>
                    <div class="company-details">
                        <strong>' . htmlspecialchars($company->getOwnerName()) . '</strong><br>
                        ' . htmlspecialchars($company->getTitle()) . '<br>
                        ' . htmlspecialchars($company->getLegalStatus() ?: 'Auto-entrepreneur') . '<br>
                        <br>
                        <strong>Email:</strong> ' . htmlspecialchars($company->getEmail()) . '<br>
                        <strong>Tél:</strong> ' . htmlspecialchars($company->getPhone()) . '<br>
                        <strong>SIRET:</strong> ' . htmlspecialchars($company->getSiret()) . '
                    </div>
                </td>
                <td width="50%" style="text-align: right; vertical-align: top;">
                    <div class="client-info">
                        <strong style="color: #2B5F75;">FACTURÉ À:</strong><br>
                        <strong>' . htmlspecialchars($client->getName()) . '</strong><br>';

        if ($client->getCompanyName()) {
            $html .= htmlspecialchars($client->getCompanyName()) . '<br>';
        }

        $html .= htmlspecialchars($client->getAddress()) . '<br>
                        ' . htmlspecialchars($client->getPostalCode()) . ' ' . htmlspecialchars($client->getCity()) . '<br>';

        if ($client->getEmail()) {
            $html .= '<strong>Email:</strong> ' . htmlspecialchars($client->getEmail()) . '<br>';
        }

        $html .= '</div>
                </td>
            </tr>
        </table>

        <div class="document-title">FACTURE N° ' . htmlspecialchars($facture->getNumber()) . '</div>

        <table width="100%" style="margin-bottom: 20px;">
            <tr>
                <td width="50%">
                    <strong>Date de facturation:</strong> ' . $facture->getDateFacture()->format('d/m/Y') . '<br>
                    <strong>Date d\'échéance:</strong> ' . $facture->getDateEcheance()->format('d/m/Y') . '
                </td>
                <td width="50%" style="text-align: right;">
                    <strong>Objet:</strong> ' . htmlspecialchars($facture->getTitle()) . '<br>';

        if ($facture->getDevis()) {
            $html .= '<strong>Devis de référence:</strong> ' . htmlspecialchars($facture->getDevis()->getNumber());
        }

        $html .= '</td>
            </tr>
        </table>';

        if ($facture->getDescription()) {
            $html .= '<div style="margin-bottom: 20px; background-color: #f8f9fa; padding: 10px; border-left: 4px solid #FF6B35;">
                <strong style="color: #2B5F75;">Description:</strong><br>
                ' . nl2br(htmlspecialchars($facture->getDescription())) . '
            </div>';
        }

        $html .= '<table class="items-table">
            <thead>
                <tr>
                    <th width="5%">N°</th>
                    <th width="45%">Description</th>
                    <th width="8%">Qté</th>
                    <th width="8%">Unité</th>
                    <th width="12%">Prix unitaire HT</th>
                    <th width="8%">Remise</th>
                    <th width="14%">Total HT</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($items as $item) {
            $discountText = ($item->getDiscount() && $item->getDiscount() > 0) ? number_format((float)$item->getDiscount(), 1) . '%' : '-';
            $html .= '<tr>
                <td style="text-align: center;">' . $item->getPosition() . '</td>
                <td>' . nl2br(htmlspecialchars($item->getDescription())) . '</td>
                <td style="text-align: center;">' . number_format((float)$item->getQuantity(), 2, ',', ' ') . '</td>
                <td style="text-align: center;">' . ($item->getUnit() ?: '-') . '</td>
                <td style="text-align: right;">' . number_format((float)$item->getUnitPrice(), 2, ',', ' ') . ' €</td>
                <td style="text-align: center;">' . $discountText . '</td>
                <td style="text-align: right;"><strong>' . number_format($item->getTotalAfterDiscount(), 2, ',', ' ') . ' €</strong></td>
            </tr>';
        }

        $html .= '</tbody>
        </table>

        <div class="totals-section">
            <table class="totals-table">
                <tr class="total-line">
                    <td><strong>Total HT:</strong></td>
                    <td><strong>' . number_format((float)$facture->getTotalHt(), 2, ',', ' ') . ' €</strong></td>
                </tr>
                <tr class="total-line">
                    <td>TVA (20%):</td>
                    <td>' . number_format((float)$facture->getVatAmount(), 2, ',', ' ') . ' €</td>
                </tr>
                <tr class="total-final">
                    <td><strong>TOTAL TTC:</strong></td>
                    <td><strong>' . number_format((float)$facture->getTotalTtc(), 2, ',', ' ') . ' €</strong></td>
                </tr>
            </table>
        </div>';

        if ($facture->getConditions()) {
            $html .= '<div class="conditions">
                <strong style="color: #2B5F75;">Conditions de paiement:</strong><br>
                ' . nl2br(htmlspecialchars($facture->getConditions())) . '
            </div>';
        }

        $html .= '<div class="payment-info">
            <strong style="color: #2B5F75;">Informations de paiement:</strong><br>
            <strong>Mode de règlement:</strong> ' . ($facture->getModePaiement() ?: 'Virement bancaire') . '<br>
            <strong>Date d\'échéance:</strong> ' . $facture->getDateEcheance()->format('d/m/Y') . '<br>
            <strong>Pénalités de retard:</strong> 3 fois le taux d\'intérêt légal<br>
            <strong>Indemnité forfaitaire:</strong> 40 € (art. L441-6 du Code de commerce)
        </div>

        <div style="margin-top: 20px; text-align: center; font-size: 8px; color: #666;">
            Alré Web - Fabrice DHUICQUE - Auto-entrepreneur - SIRET: 90308676700022<br>
            Email: contact@alre-web.bzh - Dispensé d\'immatriculation au registre du commerce et des sociétés (RCS) et au répertoire des métiers (RM)<br>
            TVA non applicable, art. 293 B du CGI
        </div>';

        return $html;
    }

    private function sanitizeFilename(string $filename): string
    {
        // Remove special characters and spaces
        $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $filename);
        $filename = preg_replace('/-+/', '-', $filename);
        return trim($filename, '-');
    }
}