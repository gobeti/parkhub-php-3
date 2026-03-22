import { Printer } from '@phosphor-icons/react';
import type { Booking } from '../api/client';
import { useTranslation } from 'react-i18next';

interface Props {
  booking: Booking;
  qrSvg?: string;
}

function escapeHtml(str: string): string {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

export function PrintBookingButton({ booking, qrSvg }: Props) {
  const { t } = useTranslation();

  function handlePrint() {
    const printWindow = window.open('', '_blank', 'width=600,height=800');
    if (!printWindow) return;

    const statusColor: Record<string, string> = {
      confirmed: '#16a34a',
      active: '#2563eb',
      completed: '#6b7280',
      cancelled: '#dc2626',
    };

    const color = statusColor[booking.status] || '#6b7280';
    const start = new Date(booking.start_time).toLocaleString();
    const end = new Date(booking.end_time).toLocaleString();

    const doc = printWindow.document;
    doc.open();

    const style = doc.createElement('style');
    style.textContent = `
      body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 2rem; color: #111; max-width: 500px; margin: 0 auto; }
      .header { text-align: center; margin-bottom: 1.5rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 1rem; }
      .header h1 { font-size: 1.5rem; margin: 0 0 0.25rem; }
      .header .id { color: #6b7280; font-size: 0.75rem; font-family: monospace; }
      .status { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; color: white; text-transform: uppercase; }
      .details { margin: 1.5rem 0; }
      .row { display: flex; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6; }
      .label { width: 120px; color: #6b7280; font-size: 0.875rem; }
      .value { flex: 1; font-size: 0.875rem; font-weight: 500; }
      .qr { text-align: center; margin: 1.5rem 0; }
      .qr img { max-width: 150px; }
      .footer { text-align: center; color: #9ca3af; font-size: 0.75rem; margin-top: 2rem; border-top: 1px solid #e5e7eb; padding-top: 1rem; }
      @media print { body { padding: 1rem; } }
    `;
    doc.head.appendChild(style);

    const title = doc.createElement('title');
    title.textContent = `Booking ${booking.id.slice(0, 8)}`;
    doc.head.appendChild(title);

    // Header
    const header = doc.createElement('div');
    header.className = 'header';
    const h1 = doc.createElement('h1');
    h1.textContent = 'ParkHub Booking';
    header.appendChild(h1);
    const idDiv = doc.createElement('div');
    idDiv.className = 'id';
    idDiv.textContent = booking.id;
    header.appendChild(idDiv);
    const statusSpan = doc.createElement('span');
    statusSpan.className = 'status';
    statusSpan.style.background = color;
    statusSpan.textContent = booking.status;
    const statusWrap = doc.createElement('div');
    statusWrap.style.marginTop = '0.5rem';
    statusWrap.appendChild(statusSpan);
    header.appendChild(statusWrap);
    doc.body.appendChild(header);

    // Details
    const details = doc.createElement('div');
    details.className = 'details';
    const rows = [
      ['Lot', escapeHtml(booking.lot_name)],
      ['Slot', escapeHtml(booking.slot_number)],
      ['Start', start],
      ['End', end],
    ];
    if (booking.vehicle_plate) rows.push(['Vehicle', escapeHtml(booking.vehicle_plate)]);
    if (booking.total_price != null) rows.push(['Price', `${booking.total_price} ${booking.currency || 'EUR'}`]);

    for (const [label, value] of rows) {
      const row = doc.createElement('div');
      row.className = 'row';
      const labelEl = doc.createElement('div');
      labelEl.className = 'label';
      labelEl.textContent = label;
      const valueEl = doc.createElement('div');
      valueEl.className = 'value';
      valueEl.textContent = value;
      row.appendChild(labelEl);
      row.appendChild(valueEl);
      details.appendChild(row);
    }
    doc.body.appendChild(details);

    // QR Code
    if (qrSvg) {
      const qrDiv = doc.createElement('div');
      qrDiv.className = 'qr';
      const img = doc.createElement('img');
      img.src = qrSvg;
      img.alt = 'QR Code';
      qrDiv.appendChild(img);
      doc.body.appendChild(qrDiv);
    }

    // Footer
    const footer = doc.createElement('div');
    footer.className = 'footer';
    footer.textContent = `Printed ${new Date().toLocaleString()}`;
    doc.body.appendChild(footer);

    doc.close();
    printWindow.onload = () => printWindow.print();
  }

  return (
    <button
      onClick={handlePrint}
      className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white border border-gray-200 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:hover:bg-surface-700 transition print:hidden"
      title={t('booking.print', 'Print booking')}
    >
      <Printer size={16} />
      {t('common.print', 'Print')}
    </button>
  );
}
