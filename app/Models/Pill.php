<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pill extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_clinic_id',
        'invoice_number',
        'clinic_note',
        'price_order',
        'have_discount',
        'discount_percent',
        'discount_amount',
        'tax_percent',
        'tax_amount',
        'final_price',
        'issued_at',
        'service_date',
        'invoiceServices', // JSON field
        'insurance_info',
        'payment_notes'
    ];

    protected $casts = [
        'have_discount' => 'boolean',
        'issued_at' => 'datetime',
        'service_date' => 'date',
        'invoiceServices' => 'array' // Add this line to cast JSON to array
    ];

    public function orderClinic()
    {
        return $this->belongsTo(Order_Clinic::class, 'order_clinic_id');
    }

    public function getInvoiceData()
    {
        $order = $this->orderClinic;
        $consultation = $order->consultation;
        $clinic = $order->clinic;
        $pet = $consultation->pet;
        $user = $pet->user;

        return [
            'clinic' => [
                'name' => $clinic->user->name,
                'address' => $clinic->address,
                'phone' => $clinic->phone,
                'email' => $clinic->user->email,
                'tax_number' => $clinic->tax_number
            ],
            'client' => [
                'name' => $user->name,
                'address' => $user->address,
                'client_number' => $user->id,
                'insurance_info' => $this->insurance_info
            ],
            'pet' => [
                'type' => $pet->type,
                'name' => $pet->name,
                'breed' => $pet->breed ?? 'N/A',
                'gender' => $pet->gender,
                'name_cheap' => $pet->name_cheap,
                'birth_date' => $pet->birth_date
            ],
            'invoice' => [
                'number' => $this->invoice_number,
                'date' => $this->issued_at,
                'service_date' => $this->service_date,
                'invoiceServices' => $this->getFormattedServices(), // Use formatted method
                'subtotal' => $this->price_order,
                'discount' => $this->have_discount ? [
                    'percent' => $this->discount_percent,
                    'amount' => $this->discount_amount
                ] : null,
                'tax' => [
                    'percent' => $this->tax_percent,
                    'amount' => $this->tax_amount
                ],
                'total' => $this->final_price,
                'payment_terms' => $clinic->payment_terms,
                'bank_info' => $clinic->bank_account_info,
                'notes' => $this->payment_notes
            ]
        ];
    }

    /**
     * Format the invoice services for display
     */
    public function getFormattedServices()
    {
        if (empty($this->invoiceServices)) {
            return [];
        }

        return array_map(function ($service) {
            return [
                'name' => $service['name'],
                'price' => number_format($service['price'], 2),
                'quantity' => $service['quantity'] ?? 1,
                'total' => number_format($service['total'] ?? $service['price'], 2)
            ];
        }, $this->invoiceServices);
    }
}
