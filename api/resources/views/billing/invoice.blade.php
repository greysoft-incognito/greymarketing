<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $number }}</title>
    <style>
        @page { margin: 48px 52px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #2b2b2e; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }
        .muted { color: #737478; }
        .brand { color: #843A4C; }
        .logo td { width: 9px; height: 9px; padding: 0; }
        .dot { display: block; width: 9px; height: 9px; border-radius: 5px; background: #737478; }
        .dot-brand { background: #843A4C; }
        .app { font-size: 15px; font-weight: bold; padding-left: 8px; }
        .title { font-size: 24px; font-weight: bold; text-align: right; }
        .paid { display: inline-block; margin-top: 4px; padding: 2px 10px; border-radius: 10px; background: #e7f4ec; color: #1f7a45; font-size: 10px; font-weight: bold; letter-spacing: 0.5px; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #737478; margin-bottom: 3px; }
        .panel { background: #f4f4f5; border-radius: 8px; padding: 14px 16px; }
        .items th { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #737478; text-align: left; padding: 8px 0; border-bottom: 1px solid #e4e4e7; }
        .items td { padding: 12px 0; border-bottom: 1px solid #e4e4e7; }
        .right, .items th.right { text-align: right; }
        .totals td { padding: 4px 0; }
        .total td { padding-top: 10px; font-size: 14px; font-weight: bold; }
        .footer { margin-top: 40px; font-size: 10px; color: #737478; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <table style="width: auto;">
                    <tr>
                        <td>
                            <table class="logo" style="width: auto;">
                                <tr><td><span class="dot"></span></td><td style="padding-left: 3px;"><span class="dot dot-brand"></span></td></tr>
                                <tr><td style="padding-top: 3px;"><span class="dot"></span></td><td style="padding: 3px 0 0 3px;"><span class="dot"></span></td></tr>
                            </table>
                        </td>
                        <td class="app">{{ $seller['name'] }}</td>
                    </tr>
                </table>
                <div class="muted" style="margin-top: 10px;">
                    @if ($seller['address'])
                        {!! nl2br(e($seller['address'])) !!}<br>
                    @endif
                    @if ($seller['email'])
                        {{ $seller['email'] }}
                    @endif
                </div>
            </td>
            <td style="text-align: right;">
                <div class="title">Invoice</div>
                <div class="muted">{{ $number }}</div>
                <div class="paid">PAID</div>
            </td>
        </tr>
    </table>

    <table style="margin-top: 32px;">
        <tr>
            <td style="width: 50%; padding-right: 8px;">
                <div class="panel">
                    <div class="label">Billed to</div>
                    <div style="font-weight: bold;">{{ $customer['workspace'] }}</div>
                    @if ($customer['name'])
                        <div>{{ $customer['name'] }}</div>
                    @endif
                    @if ($customer['email'])
                        <div class="muted">{{ $customer['email'] }}</div>
                    @endif
                </div>
            </td>
            <td style="width: 50%; padding-left: 8px;">
                <div class="panel">
                    <table>
                        <tr><td class="muted">Issued</td><td class="right">{{ $issued_at->toFormattedDateString() }}</td></tr>
                        <tr><td class="muted">Paid</td><td class="right">{{ $paid_at->toFormattedDateString() }}</td></tr>
                        <tr><td class="muted">Paid with</td><td class="right">{{ $method }}</td></tr>
                        <tr><td class="muted">Reference</td><td class="right">{{ $reference }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 32px;">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div style="font-weight: bold;">{{ $item['description'] }}</div>
                    @if ($item['period'])
                        <div class="muted">{{ $item['period'] }}</div>
                    @endif
                </td>
                <td class="right">{{ $amount }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals" style="margin-top: 12px;">
        <tr>
            <td style="width: 55%;"></td>
            <td>
                <table>
                    <tr><td class="muted">Subtotal</td><td class="right">{{ $amount }}</td></tr>
                    @if ($discount)
                        <tr><td class="muted">Discount</td><td class="right">-{{ $discount }}</td></tr>
                    @endif
                    <tr class="total"><td>Total paid</td><td class="right brand">{{ $total }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        Thank you for using {{ $seller['name'] }}. SMS and email sends are billed separately by your own providers.
    </div>
</body>
</html>
