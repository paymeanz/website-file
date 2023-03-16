<?php

namespace App\Http\Controllers;

use App\Exports\AdminTransactionsExport;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laracasts\Flash\Flash;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentController extends AppBaseController
{
    /**
     * @param  Request  $request
     * @return Application|Factory|View
     *
     * @throws Exception
     */
    public function index(Request $request)
    {
        $paymentModeArr = Payment::PAYMENT_MODE;
        unset($paymentModeArr[Payment::ALL]);

        return view('transactions.index', compact('paymentModeArr'));
    }

    /**
     * @return BinaryFileResponse
     */
    public function exportTransactionsExcel(): BinaryFileResponse
    {
        return Excel::download(new AdminTransactionsExport(), 'transaction.xlsx');
    }

    /**
     * @return JsonResponse
     */
    public function showPaymentNotes($id): JsonResponse
    {
        $paymentNotes = Payment::where('id', $id)->first();

        return $this->sendResponse($paymentNotes->notes, 'Note retrieved successfully.');
    }

    /**
     * @param  Request  $request
     * @return mixed
     */
    public function changeTransactionStatus(Request $request)
    {
        $input = $request->all();

        /** @var Payment $payment */
        $payment = Payment::whereId($input['id'])->wherePaymentMode(Payment::MANUAL)->with('invoice')->firstOrFail();

        if ($input['status'] == Payment::MANUAL) {
            $payment->update([
                'is_approved' => $input['status'],
            ]);
            $this->updatePayment($payment);

            return $this->sendSuccess('Manual Payment Approved successfully.');
        }

        $payment->update([
            'is_approved' => $input['status'],
        ]);
        $this->updatePayment($payment);

        return $this->sendSuccess('Manual Payment Denied successfully.');
    }

    /**
     * @param  Payment  $payment
     * @return void
     */
    private function updatePayment(Payment $payment): void
    {
        $paymentInvoice = $payment->invoice;
        $totalPayment = Payment::whereInvoiceId($paymentInvoice->id)->whereIsApproved(Payment::APPROVED)->sum('amount');
        $status = Invoice::PARTIALLY;
        if ($payment->amount == $paymentInvoice->final_amount || $totalPayment == $paymentInvoice->final_amount) {
            $status = $payment->is_approved == Payment::APPROVED ? Invoice::PAID : Invoice::UNPAID;
        } elseif ($totalPayment == 0) {
            $status = Invoice::UNPAID;
        }
        $paymentInvoice->update([
            'status' => $status,
        ]);
    }

    public function downloadAttachment($transactionId)
    {
        /** @var Payment $transaction */
        $transaction = Payment::with('media')->findOrFail($transactionId);
        $attachment = $transaction->media->first();

        if (getLogInUser()->hasrole(Role::CLIENT)) {
            if ($transaction->invoice->client->user_id !== getLogInUserId()) {
                Flash::error('Seems, you are not allowed to access this record.');

                return redirect()->back();
            }
        }

        if ($attachment) {
            return $attachment;
        }

        return null;
    }
}
