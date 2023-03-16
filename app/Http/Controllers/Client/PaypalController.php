<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\AppBaseController;
use App\Mail\ClientMakePaymentMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Transaction;
use Flash;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalHttp\HttpException;

class PaypalController extends AppBaseController
{
    public function onBoard(Request $request)
    {
        try {
            $invoiceId = $request->get('invoiceId');
            $payable_amount = $request->get('amount');
            $transactionNotes = $request->get('transactionNotes');

            /** @var Invoice $invoice */
            $invoice = Invoice::whereId($invoiceId)->firstOrFail();
            $invoiceCurrencyId = $invoice->currency_id;

            $paypalClientID = getSettingValue('paypal_client_id');
            $paypalSecret = getSettingValue('paypal_secret');

            $clientId = $paypalClientID ?? config('payments.paypal.client_id');
            $clientSecret = $paypalSecret ?? config('payments.paypal.client_secret');

            $mode = config('payments.paypal.mode');

            if ($mode == 'live') {
                $environment = new ProductionEnvironment($clientId, $clientSecret);
            } else {
                $environment = new SandboxEnvironment($clientId, $clientSecret);
            }
            $client = new PayPalHttpClient($environment);
            $request = new OrdersCreateRequest();

            $request->prefer('return=representation');
            $request->body = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $invoiceId,
                        'description' => $transactionNotes,
                        'amount' => [
                            'value' => $payable_amount,
                            'currency_code' => getInvoiceCurrencyCode($invoiceCurrencyId),
                        ],
                    ],
                ],
                'application_context' => [
                    'cancel_url' => route('paypal.failed'),
                    'return_url' => route('paypal.success'),
                ],
            ];
            $order = $client->execute($request);

            return response()->json($order);
        } catch (\Exception $exception) {
            $error = json_decode($exception->getMessage(), true);
            if (isset($error['details']) && $error['details'][0]['issue'] == 'CURRENCY_NOT_SUPPORTED') {
                return $this->sendError(getInvoiceCurrencyCode($invoiceCurrencyId).' is not currently supported.');
            }

            if (isset($error['error_description'])) {
                return $this->sendError($error['error_description']);
            }
        }
    }

    public function failed()
    {
        Flash::error('Your Payment is Cancelled');

        return redirect()->route('client.invoices.index');
    }

    public function success(Request $request)
    {
        $clientId = getSettingValue('paypal_client_id');
        $clientSecret = getSettingValue('paypal_secret');
        $mode = config('payments.paypal.mode');

        if ($mode == 'live') {
            $environment = new ProductionEnvironment($clientId, $clientSecret);
        } else {
            $environment = new SandboxEnvironment($clientId, $clientSecret);
        }
        $client = new PayPalHttpClient($environment);

        // Here, OrdersCaptureRequest() creates a POST request to /v2/checkout/orders
        // $response->result->id gives the orderId of the order created above
        $request = new OrdersCaptureRequest($request->get('token'));
        $request->prefer('return=representation');
        try {
            // Call API with your client and get a response for your call
            $response = $client->execute($request);
            // If call returns body in response, you can get the deserialized version from the result attribute of the response
            $invoiceId = $response->result->purchase_units[0]->reference_id;
            $amount = $response->result->purchase_units[0]->amount->value;
            $transactionNotes = $response->result->purchase_units[0]->description;
            $transactionId = $response->result->id;
            $invoice = Invoice::whereId($invoiceId)->first();
            $client = Client::with('user')->whereId($invoice->client_id)->first();

            $transactionDetails = [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'user_id' => $client->user->id,
                'status' => 'paid',
                'meta' => json_encode($response),
            ];
            $transaction = Transaction::create($transactionDetails);

            $invoice = Invoice::with('payments')->findOrFail($invoiceId);
            if ($invoice->status == Payment::PARTIALLYPAYMENT) {
                $totalAmount = ($invoice->final_amount - $invoice->payments->sum('amount'));
            } else {
                $totalAmount = $invoice->final_amount;
            }

            $PaymentData = [
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'payment_mode' => Payment::PAYPAL,
                'payment_date' => Carbon::now(),
                'transaction_id' => $transaction->id,
                'notes' => $transactionNotes,
                'is_approved' => Payment::APPROVED,
            ];

            $invoicePayment = Payment::create($PaymentData);

            $invoiceData = [];
            $invoiceData['amount'] = $invoicePayment['amount'];
            $invoiceData['payment_date'] = $invoicePayment['payment_date'];
            $invoiceData['invoice_id'] = $invoicePayment['invoice_id'];
            $invoiceData['invoice'] = $invoicePayment->invoice;
            if (getSettingValue('mail_notification')) {
                Mail::to(getAdminUser()->email)->send(new ClientMakePaymentMail($invoiceData));
            }
            if (round($totalAmount, 2) == $amount) {
                $invoice->status = Payment::FULLPAYMENT;
                $invoice->save();
            } else {
                if (round($totalAmount, 2) != $amount) {
                    $invoice->status = Payment::PARTIALLYPAYMENT;
                    $invoice->save();
                }
            }
            Flash::success('Payment successfully done.');

            $adminUserId = getAdminUser()->id;
            $invoice = Invoice::find($invoiceId);
            $title = "Payment ".getInvoiceCurrencyIcon($invoice->currency_id).$amount." received successfully for #".$invoice->invoice_id.".";
            addNotification([
                Notification::NOTIFICATION_TYPE['Invoice Payment'],
                $adminUserId,
                $title,
            ]);
            if (! Auth()->check()) {
                return redirect(route('invoice-show-url', $invoice->invoice_id));
            }

            return redirect(route('client.invoices.index'));
        } catch (HttpException $ex) {
            echo $ex->statusCode;
            print_r($ex->getMessage());
        }
    }
}
