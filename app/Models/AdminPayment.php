<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\AdminPayment
 *
 * @property int $id
 * @property int $invoice_id
 * @property float $amount
 * @property string $payment_mode
 * @property string $payment_date
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Invoice $invoice
 *
 * @method static Builder|AdminPayment newModelQuery()
 * @method static Builder|AdminPayment newQuery()
 * @method static Builder|AdminPayment query()
 * @method static Builder|AdminPayment whereAmount($value)
 * @method static Builder|AdminPayment whereCreatedAt($value)
 * @method static Builder|AdminPayment whereId($value)
 * @method static Builder|AdminPayment whereInvoiceId($value)
 * @method static Builder|AdminPayment whereNotes($value)
 * @method static Builder|AdminPayment wherePaymentDate($value)
 * @method static Builder|AdminPayment wherePaymentMode($value)
 * @method static Builder|AdminPayment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AdminPayment extends Model
{
    use HasFactory;

    protected $table = 'admin_payments';

    protected $fillable = ['invoice_id', 'amount', 'payment_date', 'payment_id', 'payment_mode', 'notes'];

    public static $rules = [
        'invoice_id' => 'required',
        'amount' => 'required',
        'payment_mode' => 'required',
    ];

    protected $casts = [
        'invoice_id' => 'integer',
        'amount' => 'double',
        'payment_mode' => 'integer',
        'payment_id' => 'integer',
        'payment_date' => 'date',
        'notes' => 'string',
    ];

    /**
     * @return BelongsTo
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
    }
}
