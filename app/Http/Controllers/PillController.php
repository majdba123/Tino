<?php

namespace App\Http\Controllers;

use App\Models\Pill;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;  // Note: 'Pdf' (capital P) is the class name
class PillController extends Controller
{
    /**
     * Display a listing of the resource.
     */
public function index(Request $request)
{
    $query = Pill::query();

    // Filter by order_clinic_id
    if ($request->has('order_clinic_id')) {
        $query->where('order_clinic_id', $request->order_clinic_id);
    }

    // Filter by price range
    if ($request->has('min_price')) {
        $query->where('price_order', '>=', $request->min_price);
    }
    if ($request->has('max_price')) {
        $query->where('price_order', '<=', $request->max_price);
    }

    // Filter by discount status
    if ($request->has('have_discount')) {
        $query->where('have_discount', filter_var($request->have_discount, FILTER_VALIDATE_BOOLEAN));
    }

    // Filter by discount percentage
    if ($request->has('discount_percent')) {
        $query->where('discount_percent', $request->discount_percent);
    }

    // Filter by discount amount range
    if ($request->has('min_discount_amount')) {
        $query->where('discount_amount', '>=', $request->min_discount_amount);
    }
    if ($request->has('max_discount_amount')) {
        $query->where('discount_amount', '<=', $request->max_discount_amount);
    }

    // Filter by final price range
    if ($request->has('min_final_price')) {
        $query->where('final_price', '>=', $request->min_final_price);
    }
    if ($request->has('max_final_price')) {
        $query->where('final_price', '<=', $request->max_final_price);
    }

    // Filter by date range
    if ($request->has('start_date')) {
        $query->whereDate('issued_at', '>=', $request->start_date);
    }
    if ($request->has('end_date')) {
        $query->whereDate('issued_at', '<=', $request->end_date);
    }

    // Get paginated results (optional)
    $pills = $query->get();
    // Or for pagination:
    // $pills = $query->paginate($request->per_page ?? 15);

    return response()->json([
        'success' => true,
        'data' => $pills,
        'filters' => $request->all() // Optional: return applied filters
    ]);
}

    /**
     * Display the specified pill.
     */
    public function show($id)
    {
        $pill = Pill::find($id);

        if (!$pill) {
            return response()->json([
                'success' => false,
                'message' => 'Pill not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $pill
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function showPdfApi($id)
    {
        // Find the pill or fail
        $pill = Pill::findOrFail($id);

        // Generate the PDF
        $pdf = Pdf::loadView('pills.show_pdf', compact('pill'));

        // Return the PDF as a response
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="pill-details-'.$pill->id.'.pdf"');
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pill $pill)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Pill $pill)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pill $pill)
    {
        //
    }
}
