<!DOCTYPE HTML>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <link rel="icon" href="{{ asset('web/media/logos/favicon.ico') }}" type="image/png">
    <title>{{ __('messages.invoice.invoice_pdf') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('assets/css/invoice-pdf.css') }}" rel="stylesheet" type="text/css"/>
    <style>
        * {
            font-family: DejaVu Sans, Arial, "Helvetica", Arial, "Liberation Sans", sans-serif;
        }

        @if(getInvoiceCurrencyIcon($invoice->currency_id) == '€')
        .euroCurrency {
            font-family: Arial, "Helvetica", Arial, "Liberation Sans", sans-serif;
        }
        @endif
    </style>
</head>
<body>
@php $styleCss = 'style'; @endphp
<table width="100%">
    <tr>
        <td>
        <div class="main-heading ml-4 p-3" {{ $styleCss }}="font-size: 40px; background: {{ $invoice_template_color }}; color: white">INVOICE</div>
            <div class="logo">
                <img width="100px" src="{{ getLogoUrl() }}" alt="">
            </div>
            {{ html_entity_decode(getAppName()) }}<br>
            <b>Address:&nbsp;</b>{!! $setting['company_address'] !!}<br>
            <b>Mo:&nbsp;</b>{{ $setting['company_phone'] }}
        </td>
    </tr>
</table>
<br>
<table width="100%">
    <thead>
    <tr>
        <td class="vertical-align-top">
            <strong class="to-font-size">To:</strong><br>
            <b>Name:&nbsp;</b>{{ $client->user->full_name }}
            <br>
            <b>Email:&nbsp;</b>{{ $client->user->email }}<br>
            <strong>Invoice Id:&nbsp;</strong>#{{ $invoice->invoice_id }}<br>
            <strong>Invoice
                Date:&nbsp;</strong>{{\Carbon\Carbon::parse($invoice->invoice_date)->translatedFormat(currentDateFormat()) }}<br>
            <strong>Due Date:&nbsp;</strong> {{\Carbon\Carbon::parse($invoice->due_date)->translatedFormat(currentDateFormat()) }}
        </td>
    </tr>
    </thead>
</table>
<table width="100%">
    <tr class="invoice-items">
        <td colspan="2">
            <table class="items-table">
                <thead {{ $styleCss }}="border-top: 2px solid ">
                <tr class="tu">
                    <th>#</th>
                    <th>{{ __('messages.product.product') }}</th>
                    <th class="number-align">{{ __('messages.invoice.qty') }}</th>
                    <th class="number-align">{{ __('messages.product.unit_price') }}</th>
                    <th class="number-align">{{ __('messages.invoice.tax').' (in %)' }}</th>
                    <th class="number-align">{{ __('messages.invoice.amount') }}</th>
                </tr>
                </thead>
                <tbody {{ $styleCss }}="border-bottom: 2px solid">
                @if(isset($invoice) && !empty($invoice))
                    @foreach($invoice->invoiceItems as $key => $invoiceItems)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ isset($invoiceItems->product->name)?$invoiceItems->product->name:$invoiceItems->product_name??'N/A' }}</td>
                            <td class="number-align">{{  number_format($invoiceItems->quantity,2) }}</td>
                            <td class="number-align"><b class="euroCurrency">{{isset($invoiceItems->price) ? getInvoiceCurrencyAmount($invoiceItems->price,$invoice->currency_id,true): 'N/A' }}</b>
                            </td>
                            <td class="number-align">
                                @foreach($invoiceItems->invoiceItemTax as $keys => $tax)
                                    {{ $tax->tax ?? 'N/A' }}
                                    @if (!$loop->last)
                                        ,
                                    @endif
                                @endforeach
                            </td>
                            <td class="number-align"><b class="euroCurrency">{{isset($invoiceItems->total) ? getInvoiceCurrencyAmount($invoiceItems->total,$invoice->currency_id,true): 'N/A'}}</b></td>
                        </tr>
                        @endforeach
                        @endif
                        </tbody>
            </table>
        </td>
    </tr>
    <tr>
        <td></td>
        <td>
            <table class="invoice-footer">
                <tr>
                    <td class="font-weight-bold tu">Amount:</td>
                    <td class="text-nowrap">
                        <b class="euroCurrency">{{ getInvoiceCurrencyAmount($invoice->amount,$invoice->currency_id,true) }}</b>
                    </td>
                </tr>
                <tr>
                    <td class="font-weight-bold tu">Discount:</td>
                    <td class="text-nowrap">
                        @if($invoice->discount == 0)
                            <span>N/A</span>
                        @else
                            @if(isset($invoice) && $invoice->discount_type == \App\Models\Invoice::FIXED)
                                <b class="euroCurrency">{{isset($invoice->discount) ? getInvoiceCurrencyAmount($invoice->discount,$invoice->currency_id,true): 'N/A'}}</b>
                            @else
                                {{ $invoice->discount }}<span {{ $styleCss }}="font-family: DejaVu Sans">&#37;</span>
                            @endif
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="font-weight-bold tu">Tax:</td>
                    <td class="text-nowrap">
                        {!!   (numberFormat(array_sum($totalTax)) != 0 ) ? '<b class="euroCurrency">'.getInvoiceCurrencyAmount(array_sum($totalTax),$invoice->currency_id,true).'</b>'
                        :'N/A' !!}
                    </td>
                </tr>
                <tr>
                    <td class="font-weight-bold tu" >Total:
                    </td>
                    <td class="text-nowrap">
                    <b class="euroCurrency">{{ getInvoiceCurrencyAmount($invoice->final_amount,$invoice->currency_id,true) }}</b>
                    </td>
                </tr>
                <tr>
                    <td class="font-weight-bold tu">Total Due:</td>
                    <td class="text-nowrap" {{ $styleCss }}="color: red">
                    <b class="euroCurrency">{{ getInvoiceCurrencyAmount(getInvoiceDueAmount($invoice->id),$invoice->currency_id,true) }}</b>
                    </td>
                </tr>
                <tr>
                    <td class="font-weight-bold tu" {{ $styleCss }}="border-bottom: 2px solid {{ $invoice_template_color }};">Total Paid:</td>
                    <td class="text-nowrap" {{ $styleCss }}="color: green; border-bottom: 2px solid {{ $invoice_template_color }}">
                    <b class="euroCurrency">{{ getInvoiceCurrencyAmount(getInvoicePaidAmount($invoice->id),$invoice->currency_id,true) }}</b>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <table class="w-100">
                <tr>
                    <td>
                        <strong>{{ __('messages.client.notes') }} :</strong>
                        <p class="font-color-gray">{!! nl2br(($invoice->note ?? 'N/A')) !!}</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>{{ __('messages.invoice.terms') }} :</strong><br>
                        <p class="font-color-gray">{!! nl2br(($invoice->term ?? 'N/A')) !!}</p>
                    </td>
                </tr>
            </table>
            <br>
        </td>
    </tr>
    <tr>
        <td colspan="2" class="vertical-align-bottom">
            <strong>Regards: </strong><br>
            <br>{{ getAppName() }}
        </td>
    </tr>
</table>
</body>
</html>
