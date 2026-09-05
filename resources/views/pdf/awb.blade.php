{{-- Air waybill labels. One A5 page per parcel; dompdf only ever sees data: URIs. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Air waybills</title>
    <style>
        @page { margin: 0; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; margin: 0; color: #000; }
        .label { padding: 12px; page-break-after: always; }
        .label:last-child { page-break-after: auto; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .rule { border-bottom: 1px solid #000; }
        .store-logo { max-height: 42px; }
        .courier-logo { max-height: 30px; }
        .courier-name { font-size: 16px; font-weight: bold; letter-spacing: 0.5px; }
        .qr { width: 90px; height: 90px; }
        .barcode { width: 92%; height: 52px; }
        .awb { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .block { padding: 6px 8px; height: 118px; }
        .caption { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #444; }
        .name { font-size: 13px; font-weight: bold; }
        .postcode { font-size: 17px; font-weight: bold; }
        .badge { background: #000; color: #fff; font-weight: bold; text-align: center; padding: 7px 0; font-size: 13px; }
        .cell { border: 1px solid #000; padding: 5px 6px; text-align: center; }
        .items td { padding: 4px 6px; border-bottom: 1px solid #ddd; font-size: 12px; font-weight: bold; }
        .ref { font-size: 10px; }
    </style>
</head>
<body>
@foreach ($labels as $label)
    @php($order = $label['order'])
    <div class="label">
        <table>
            <tr>
                <td style="width:60%;">
                    @if ($storeLogo)
                        <img src="{{ $storeLogo }}" class="store-logo" alt="">
                    @else
                        <span class="courier-name">{{ $shipper['name'] }}</span>
                    @endif
                    <br>
                    @if ($label['courierLogo'])
                        <img src="{{ $label['courierLogo'] }}" class="courier-logo" alt="">
                    @else
                        <span class="courier-name">{{ $order->courier_service }}</span>
                    @endif
                    @if ($label['reference'])
                        <div class="ref">Ref code: <b>{{ $label['reference'] }}</b></div>
                    @endif
                </td>
                <td style="text-align:right;">
                    <img src="{{ $label['qr'] }}" class="qr" alt="">
                </td>
            </tr>
        </table>

        <table style="margin-top:6px;">
            <tr>
                <td style="text-align:center;" class="rule">
                    <img src="{{ $label['barcode'] }}" class="barcode" alt="">
                    <div class="awb">{{ $order->awb_number }}</div>
                </td>
            </tr>
        </table>

        <table style="margin-top:6px;">
            <tr>
                <td style="width:70%;">
                    <table>
                        <tr>
                            <td class="block rule">
                                <span class="caption">Ship to</span><br>
                                <span class="name">{{ $order->customerFullName() }}</span><br>
                                {{ $order->address_1 }}<br>
                                @if (filled($order->address_2)){{ $order->address_2 }}<br>@endif
                                {{ $order->city }}, {{ $order->state }}<br>
                                Phone: <b>{{ $order->customer_phone }}</b>
                                <div class="postcode">{{ $order->postcode }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="block">
                                <span class="caption">Sender</span><br>
                                <span class="name">{{ $shipper['name'] }}</span><br>
                                {{ $shipper['address1'] }}<br>
                                {{ $shipper['address2'] }}<br>
                                {{ $shipper['city'] }}, {{ $shipper['state'] }}<br>
                                Phone: <b>{{ $shipper['phone'] }}</b>
                                <div class="postcode">{{ $shipper['postcode'] }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width:30%; padding-left:6px;">
                    <div class="cell">
                        <span class="caption">Order</span><br>
                        <b>{{ $order->reference() }}</b>
                    </div>
                    <div class="badge" style="margin-top:4px;">
                        {{ $order->payment_channel === \App\Models\Order::CHANNEL_COD ? 'COD' : 'PREPAID' }}
                    </div>
                    <div class="cell" style="margin-top:4px;">
                        <span class="caption">{{ $order->payment_channel === \App\Models\Order::CHANNEL_COD ? 'Collect' : 'Declared' }}</span><br>
                        <b>{{ $order->currency_sign }} {{ number_format((float) $order->myr_value_include_postage, 2) }}</b>
                    </div>
                    <div class="cell" style="margin-top:4px;">
                        <span class="caption">Weight</span><br>
                        <b>{{ number_format(max(1, (int) $label['lines']->sum('total_weight')) / 1000, 2) }} kg</b>
                    </div>
                    <div class="cell" style="margin-top:4px;">
                        <span class="caption">Pieces</span><br>
                        <b>{{ (int) $order->total_qty }}</b>
                    </div>
                </td>
            </tr>
        </table>

        <table class="items" style="margin-top:6px;">
            @foreach ($label['lines'] as $line)
                <tr>
                    <td style="width:80%;">{{ $line->product?->name ?? 'Item #'.$line->p_id }}</td>
                    <td style="width:20%; text-align:center;">x{{ (int) $line->quantity }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endforeach
</body>
</html>
