<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Transaction;
use App\TransactionSellLine;
use App\TransactionPayment;
use App\Contact;
use App\Utils\TransactionUtil;
use App\Utils\ProductUtil;
use App\Utils\BusinessUtil;
use DB;
use Carbon\Carbon;

class SyncController extends Controller
{
    protected $transactionUtil;
    protected $productUtil;
    protected $businessUtil;

    public function __construct(
        TransactionUtil $transactionUtil,
        ProductUtil $productUtil,
        BusinessUtil $businessUtil
    ) {
        $this->transactionUtil = $transactionUtil;
        $this->productUtil = $productUtil;
        $this->businessUtil = $businessUtil;
    }

    public function syncTransaction(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');

            if (!$business_id || !$user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $data = $request->all();

            DB::beginTransaction();

            $transaction_data = [
                'business_id' => $business_id,
                'location_id' => $data['location_id'] ?? null,
                'type' => 'sell',
                'status' => 'final',
                'contact_id' => $data['contact_id'] ?? null,
                'customer_group_id' => $data['customer_group_id'] ?? null,
                'invoice_no' => null,
                'ref_no' => $data['ref_no'] ?? null,
                'transaction_date' => !empty($data['transaction_date']) 
                    ? Carbon::parse($data['transaction_date']) 
                    : Carbon::now(),
                'total_before_tax' => $data['total_before_tax'] ?? 0,
                'tax_id' => $data['tax_id'] ?? null,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'discount_type' => $data['discount_type'] ?? 'fixed',
                'discount_amount' => $data['discount_amount'] ?? 0,
                'shipping_charges' => $data['shipping_charges'] ?? 0,
                'additional_notes' => $data['additional_notes'] ?? null,
                'final_total' => $data['final_total'] ?? 0,
                'created_by' => $user_id,
                'is_suspend' => 0,
                'is_quotation' => 0,
                'payment_status' => $data['payment_status'] ?? 'paid',
                'offline_synced' => 1,
                'offline_created_at' => !empty($data['created_at']) 
                    ? Carbon::createFromTimestamp($data['created_at'] / 1000) 
                    : null,
            ];

            $transaction = Transaction::create($transaction_data);

            $invoice_total = [
                'total_before_tax' => $transaction_data['total_before_tax'],
                'tax' => $transaction_data['tax_amount'],
                'discount' => $transaction_data['discount_amount'],
                'final_total' => $transaction_data['final_total'],
            ];

            $transaction->invoice_no = $this->transactionUtil->generateInvoiceNumber(
                $transaction->id,
                $business_id,
                $transaction->status,
                $transaction->location_id
            );
            $transaction->save();

            if (!empty($data['sell_lines'])) {
                foreach ($data['sell_lines'] as $line) {
                    $sell_line = [
                        'transaction_id' => $transaction->id,
                        'product_id' => $line['product_id'],
                        'variation_id' => $line['variation_id'] ?? null,
                        'quantity' => $line['quantity'],
                        'unit_price_before_discount' => $line['unit_price_before_discount'] ?? $line['unit_price'],
                        'unit_price' => $line['unit_price'],
                        'unit_price_inc_tax' => $line['unit_price_inc_tax'] ?? $line['unit_price'],
                        'item_tax' => $line['item_tax'] ?? 0,
                        'tax_id' => $line['tax_id'] ?? null,
                        'discount_amount' => $line['discount_amount'] ?? 0,
                        'lot_no_line_id' => $line['lot_no_line_id'] ?? null,
                    ];

                    TransactionSellLine::create($sell_line);

                    $this->productUtil->decreaseProductQuantity(
                        $line['product_id'],
                        $line['variation_id'] ?? null,
                        $transaction->location_id,
                        $line['quantity']
                    );
                }
            }

            if (!empty($data['payments'])) {
                foreach ($data['payments'] as $payment) {
                    $payment_data = [
                        'transaction_id' => $transaction->id,
                        'business_id' => $business_id,
                        'amount' => $payment['amount'],
                        'method' => $payment['method'] ?? 'cash',
                        'paid_on' => !empty($payment['paid_on']) 
                            ? Carbon::parse($payment['paid_on']) 
                            : Carbon::now(),
                        'created_by' => $user_id,
                        'payment_ref_no' => $payment['payment_ref_no'] ?? null,
                        'card_number' => $payment['card_number'] ?? null,
                        'card_holder_name' => $payment['card_holder_name'] ?? null,
                        'card_transaction_number' => $payment['card_transaction_number'] ?? null,
                        'card_type' => $payment['card_type'] ?? null,
                        'card_month' => $payment['card_month'] ?? null,
                        'card_year' => $payment['card_year'] ?? null,
                        'card_security' => $payment['card_security'] ?? null,
                        'cheque_number' => $payment['cheque_number'] ?? null,
                        'bank_account_number' => $payment['bank_account_number'] ?? null,
                        'note' => $payment['note'] ?? null,
                    ];

                    TransactionPayment::create($payment_data);
                }
            }

            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction synced successfully',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'invoice_no' => $transaction->invoice_no,
                    'final_total' => $transaction->final_total,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Sync transaction error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPendingCount(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            
            if (!$business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'pending_count' => 0
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
