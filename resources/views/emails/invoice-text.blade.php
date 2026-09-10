Your Invoice Is Ready

Hi {{ $customer?->name ?? 'there' }}, your invoice {{ $invoice->invoice_number }} is attached.
@if ($invoice->payment_link)

Pay this invoice online

You can pay this invoice securely using the link below:
{!! str_replace(["\r", "\n"], '', (string) $invoice->payment_link) !!}
@endif

Amount due: £{{ number_format($invoice->total, 2) }}
Due: {{ $invoice->due_date->format('M j, Y') }}

If you have questions, reply to this email and our team will help.
