<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkExportInvoicesRequest;
use App\Models\Booking;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Generates PDF and HTML invoices for bookings.
 * GET /api/v1/bookings/{id}/invoice — HTML (default) or PDF (?format=pdf)
 * GET /api/v1/bookings/{id}/invoice.pdf — always PDF
 */
class BookingInvoiceController extends Controller
{
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $booking = Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data = $this->buildInvoiceData($booking, $user);

        // If ?format=pdf or Accept header requests PDF
        if ($request->input('format') === 'pdf' || $request->wantsJson() === false && str_contains($request->header('Accept', ''), 'application/pdf')) {
            return $this->renderPdf($data);
        }

        return $this->renderHtmlResponse($data);
    }

    /**
     * Explicit PDF endpoint: GET /api/v1/bookings/{id}/invoice.pdf
     */
    public function pdf(Request $request, string $id)
    {
        $user = $request->user();
        $booking = Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return $this->renderPdf($this->buildInvoiceData($booking, $user));
    }

    /**
     * Admin bulk export: POST /api/v1/admin/invoices/bulk
     * Accepts array of booking IDs, returns a zip or combined PDF.
     */
    public function bulkExport(BulkExportInvoicesRequest $request)
    {
        $bookings = Booking::whereIn('id', $request->booking_ids)->get();

        if ($bookings->isEmpty()) {
            return response()->json(['error' => 'NO_BOOKINGS_FOUND', 'message' => 'No bookings found for given IDs'], 404);
        }

        // Generate combined PDF with all invoices
        $htmlPages = [];
        foreach ($bookings as $booking) {
            $user = $booking->user;
            if (! $user) {
                continue;
            }
            $data = $this->buildInvoiceData($booking, $user);
            $htmlPages[] = $this->renderHtml($data);
        }

        $combinedHtml = implode('<div style="page-break-after: always;"></div>', $htmlPages);
        $pdf = Pdf::loadHTML($combinedHtml)->setPaper('a4');

        return $pdf->download('invoices-bulk-'.date('Y-m-d').'.pdf');
    }

    private function buildInvoiceData(Booking $booking, $user): array
    {
        $company = Setting::get('company_name', 'ParkHub');
        $vatId = Setting::get('impressum_vat_id', '');
        $street = Setting::get('impressum_street', '');
        $zipCity = Setting::get('impressum_zip_city', '');
        $email = Setting::get('impressum_email', '');

        // Carbon casts on the Booking model expose start_time / end_time as
        // Carbon instances; use their API directly instead of stringifying
        // through strtotime() so strict_types doesn't reject the coercion.
        $start = $booking->start_time?->timestamp ?? 0;
        $end = $booking->end_time?->timestamp ?? ($start + 3600);
        $hours = max(1, round(($end - $start) / 3600, 2));

        $startFmt = $booking->start_time?->format('d.m.Y H:i') ?? '-';
        $endFmt = $booking->end_time?->format('d.m.Y H:i') ?? '-';
        $dateNow = date('d.m.Y');

        $shortId = strtoupper(substr(str_replace('-', '', $booking->id), 0, 8));
        $year = $booking->created_at ? $booking->created_at->format('Y') : date('Y');
        $invoiceNo = 'INV-'.$year.'-'.$shortId;

        return compact(
            'company', 'vatId', 'street', 'zipCity', 'email',
            'booking', 'user', 'hours', 'startFmt', 'endFmt',
            'dateNow', 'invoiceNo', 'shortId'
        );
    }

    private function renderPdf(array $d)
    {
        $html = $this->renderHtml($d);
        $pdf = Pdf::loadHTML($html)->setPaper('a4');
        $shortId = $d['shortId'];

        return $pdf->download("rechnung-{$shortId}.pdf");
    }

    private function renderHtmlResponse(array $d)
    {
        $html = $this->renderHtml($d);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'inline; filename="rechnung-'.$d['shortId'].'.html"',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    private function renderHtml(array $d): string
    {
        $e = fn (string $s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $vatRow = $d['vatId']
            ? '<tr><td>Umsatzsteuer-ID</td><td>'.$e($d['vatId']).'</td></tr>'
            : '';

        $address = array_filter([$d['street'], $d['zipCity']]);
        $addressHtml = implode('<br>', array_map($e, $address));

        // Pricing breakdown
        $pricingHtml = '';
        if ($d['booking']->total_price) {
            $curr = $d['booking']->currency ?? 'EUR';
            $base = number_format((float) $d['booking']->base_price, 2, ',', '.');
            $tax = number_format((float) $d['booking']->tax_amount, 2, ',', '.');
            $total = number_format((float) $d['booking']->total_price, 2, ',', '.');
            $pricingHtml = <<<PRICE
  <div class="totals">
    <table>
      <tr><td>Nettobetrag</td><td style="text-align:right;">{$base} {$e($curr)}</td></tr>
      <tr><td>MwSt. (19%)</td><td style="text-align:right;">{$tax} {$e($curr)}</td></tr>
      <tr class="total"><td>Gesamtbetrag</td><td style="text-align:right;">{$total} {$e($curr)}</td></tr>
    </table>
  </div>
PRICE;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rechnung {$e($d['invoiceNo'])}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 14px; color: #1a1a1a; background: #f5f5f5; }
  .page { max-width: 800px; margin: 32px auto; background: white; padding: 48px; box-shadow: 0 2px 16px rgba(0,0,0,.08); }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; padding-bottom: 24px; border-bottom: 2px solid #e5e7eb; }
  .logo { font-size: 24px; font-weight: 700; color: #d97706; }
  .logo span { color: #374151; }
  .meta { text-align: right; color: #6b7280; font-size: 13px; line-height: 1.6; }
  .meta strong { color: #1a1a1a; font-size: 16px; }
  .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 32px; }
  .party h4 { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #9ca3af; margin-bottom: 8px; }
  .party p { line-height: 1.6; color: #374151; }
  .party strong { color: #1a1a1a; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  th { text-align: left; padding: 12px 16px; background: #f9fafb; font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
  td { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
  tr:last-child td { border-bottom: none; }
  .totals { margin-top: 16px; margin-left: auto; width: 300px; }
  .totals table { font-size: 14px; }
  .totals td { padding: 8px 16px; }
  .totals .total td { font-weight: 700; font-size: 16px; border-top: 2px solid #e5e7eb; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
  .badge-confirmed { background: #d1fae5; color: #065f46; }
  .badge-cancelled { background: #fee2e2; color: #991b1b; }
  .badge-default { background: #f3f4f6; color: #374151; }
  .footer { margin-top: 48px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; text-align: center; line-height: 1.8; }
  .notice { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #92400e; margin-top: 24px; }
  @media print {
    body { background: white; }
    .page { box-shadow: none; margin: 0; padding: 24px; }
    .print-btn { display: none; }
  }
</style>
</head>
<body>
<div class="page">

  <div style="text-align:right; margin-bottom: 12px;" class="print-btn">
    <button onclick="window.print()" style="background:#d97706; color:white; border:none; padding:8px 20px; border-radius:6px; cursor:pointer; font-size:13px;">Als PDF speichern</button>
  </div>

  <div class="header">
    <div>
      <div class="logo">Park<span>Hub</span></div>
      <div style="margin-top:6px; color:#6b7280; font-size:13px; line-height:1.6;">
        {$addressHtml}
        {$e($d['email'])}
      </div>
    </div>
    <div class="meta">
      <strong>Rechnung</strong><br>
      Nr.: {$e($d['invoiceNo'])}<br>
      Datum: {$e($d['dateNow'])}<br>
      <span style="margin-top:4px; display:inline-block; background:#d97706; color:white; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600;">BEZAHLT</span>
    </div>
  </div>

  <div class="parties">
    <div class="party">
      <h4>Rechnungssteller</h4>
      <p><strong>{$e($d['company'])}</strong><br>{$addressHtml}<br>{$e($d['email'])}</p>
    </div>
    <div class="party">
      <h4>Rechnungsempfaenger</h4>
      <p>
        <strong>{$e($d['user']->name)}</strong><br>
        {$e($d['user']->email)}<br>
        {$e($d['user']->username)}
      </p>
    </div>
  </div>

  <h3 style="margin-bottom:16px; font-size:16px; color:#374151;">Leistungsuebersicht</h3>
  <table>
    <thead>
      <tr>
        <th>Beschreibung</th>
        <th>Zeitraum</th>
        <th>Dauer</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <strong>Parkplatz-Buchung</strong><br>
          <span style="color:#6b7280; font-size:13px;">
            {$e($d['booking']->lot_name ?? '-')} - Stellplatz {$e($d['booking']->slot_number ?? '-')}
          </span><br>
          <span style="color:#9ca3af; font-size:12px; font-family: monospace;">#{$e($d['booking']->id)}</span>
        </td>
        <td style="white-space:nowrap;">
          {$e($d['startFmt'])}<br>
          <span style="color:#6b7280;">bis {$e($d['endFmt'])}</span>
        </td>
        <td style="white-space:nowrap;">{$e(number_format($d['hours'], 1))} Std.</td>
        <td>
          <span class="badge badge-confirmed">{$e(ucfirst($d['booking']->status ?? 'confirmed'))}</span>
        </td>
      </tr>
    </tbody>
  </table>

  {$pricingHtml}
  {$vatRow}

  <div class="notice">
    <strong>Hinweis:</strong> Diese Buchungsbestaetigung dient als Beleg. Gemaess Paragraph 14 UStG wird keine gesonderte Steuer ausgewiesen (Kleinunternehmerregelung oder Betreiber-konfiguriert). Bitte wenden Sie sich bei Fragen an {$e($d['email'])}.
  </div>

  <div class="footer">
    <strong>{$e($d['company'])}</strong> - ParkHub Open Source Parking Platform<br>
    Erstellt am {$e($d['dateNow'])} - Buchungs-ID: {$e($d['booking']->id)}<br>
    <a href="https://github.com/nash87/parkhub-php" style="color:#9ca3af;">github.com/nash87/parkhub-php</a>
  </div>

</div>
</body>
</html>
HTML;
    }
}
