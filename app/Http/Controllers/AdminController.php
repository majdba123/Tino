<?php

namespace App\Http\Controllers;

use App\Models\{Clinic, Consultation, Order_Clinic,
                User_Subscription, Payment, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    const CACHE_TTL = 300;

    public function getDashboardStats()
    {
        return Cache::remember('dashboard_stats', self::CACHE_TTL, function () {
            return response()->json([
                'success' => true,
                'data' => $this->compileDashboardData(),
                'meta' => [
                    'generated_at' => now()->toDateTimeString(),
                    'cache_expires_at' => now()->addSeconds(self::CACHE_TTL)->toDateTimeString()
                ]
            ]);
        });
    }

    private function compileDashboardData(): array
    {
        return [
            'core_metrics' => $this->getCoreMetrics(),
            'time_analytics' => [
                'revenue' => $this->getRevenueAnalytics(),
                'consultations' => $this->getConsultationAnalytics(),
                'orders' => $this->getOrderAnalytics()
            ],
            'clinic_performance' => $this->getClinicPerformance(),
            'kpis' => $this->calculateKPIs(),
            'recent_activity' => $this->getRecentActivity()
        ];
    }

    private function getCoreMetrics(): array
    {
        try {
            return [
                'clinics' => [
                    'total' => Clinic::count(),
                    'active' => Clinic::where('status', 'active')->count(),
                    'inactive' => Clinic::where('status', '!=', 'active')->count(),
                    'types' => Clinic::groupBy('type')
                                ->selectRaw('type, count(*) as count')
                                ->get()
                                ->pluck('count', 'type')
                ],
                'consultations' => [
                    'total' => Consultation::count(),
                    'statuses' => [
                        'pending' => Consultation::where('status', Consultation::STATUS_PENDING)->count(),
                        'reviewed' => Consultation::where('status', Consultation::STATUS_REVIEWED)->count(),
                        'completed' => Consultation::where('status', Consultation::STATUS_CLOSED)->count()
                    ]
                ],
                'orders' => [
                    'total' => Order_Clinic::count(),
                    'statuses' => [
                        'completed' => Order_Clinic::where('status', 'completed')->count(),
                        'canceled' => Order_Clinic::where('status', 'canceled')->count(),
                        'in_progress' => Order_Clinic::whereNotIn('status', ['completed', 'canceled'])->count()
                    ],
                    'avg_value' => Order_Clinic::avg('final_price')
                ],
                'subscriptions' => [
                    'active' => User_Subscription::active()->count(),
                    'expired' => User_Subscription::where('is_active', false)->count(),
                    'trialing' => User_Subscription::where('remaining_calls', '>', 0)->count()
                ],
                'revenue' => [
                    'total' => Payment::where('status', Payment::STATUS_PAID)->sum('amount'),
                    'monthly' => Payment::where('status', Payment::STATUS_PAID)
                                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                                    ->sum('amount'),
                    'annual' => Payment::where('status', Payment::STATUS_PAID)
                                    ->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()])
                                    ->sum('amount')
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch core metrics: ' . $e->getMessage());
            return [];
        }
    }

    private function getRevenueAnalytics(): array
    {
        $currentMonth = Payment::where('status', Payment::STATUS_PAID)
                        ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                        ->sum('amount');

        $previousMonth = Payment::where('status', Payment::STATUS_PAID)
                        ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
                        ->sum('amount');

        return [
            'current_month' => $currentMonth,
            'previous_month' => $previousMonth,
            'change_percentage' => $this->safePercentageChange($previousMonth, $currentMonth),
            'methods' => Payment::groupBy('payment_method')
                            ->selectRaw('payment_method, sum(amount) as total')
                            ->get()
                            ->pluck('total', 'payment_method')
        ];
    }

    private function getConsultationAnalytics(): array
    {
        return [
            'response_time' => [
                'avg_hours' => Consultation::where('status', '!=', Consultation::STATUS_PENDING)
                                    ->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, updated_at)')),
                'distribution' => $this->getResponseTimeDistribution()
            ],
            'conversion_rate' => $this->calculateConversionRate()
        ];
    }

    private function getOrderAnalytics(): array
    {
        return [
            'fulfillment_time' => [
                'avg_hours' => Order_Clinic::where('status', 'completed')
                                    ->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, updated_at)')),
                'distribution' => $this->getOrderFulfillmentDistribution() // تم تغيير اسم الدالة هنا
            ],
            'discount_impact' => [
                'total_discounts' => Order_Clinic::sum('discount_amount'),
                'discounted_orders' => Order_Clinic::where('have_discount', true)->count(),
                'avg_discount' => Order_Clinic::where('have_discount', true)->avg('discount_percent')
            ]
        ];
    }

    private function getOrderFulfillmentDistribution(): array
    {
        return DB::table('order__clinics')
            ->selectRaw('
                CASE
                    WHEN TIMESTAMPDIFF(HOUR, created_at, updated_at) < 1 THEN "0-1h"
                    WHEN TIMESTAMPDIFF(HOUR, created_at, updated_at) < 6 THEN "1-6h"
                    WHEN TIMESTAMPDIFF(HOUR, created_at, updated_at) < 24 THEN "6-24h"
                    ELSE "24h+"
                END as time_range,
                COUNT(*) as count
            ')
            ->where('status', 'completed')
            ->groupBy('time_range')
            ->orderBy('time_range')
            ->get()
            ->pluck('count', 'time_range')
            ->toArray();
    }

    private function getClinicPerformance(): array
    {
        $topClinics = Clinic::query()
            ->withCount(['Order_Clinic as total_orders', 'user_review as total_reviews'])
            ->withAvg('user_review', 'rating')
            ->orderByDesc('total_orders')
            ->limit(10)
            ->get()
            ->map(function ($clinic) {
                return [
                    'id' => $clinic->id,
                    'name' => $clinic->address,
                    'orders' => $clinic->total_orders,
                    'reviews' => $clinic->total_reviews,
                    'rating' => round($clinic->user_review_avg_rating, 2),
                    'revenue' => $clinic->Order_Clinic()->sum('final_price')
                ];
            });

        return [
            'top_by_orders' => $topClinics,
            'top_by_revenue' => $topClinics->sortByDesc('revenue')->values()->take(5),
            'top_by_rating' => $topClinics->sortByDesc('rating')->values()->take(5)
        ];
    }

    private function calculateKPIs(): array
    {
        return [
            'customer_retention' => $this->calculateRetentionRate(),
            'order_conversion' => $this->calculateConversionRate(),
            'clinic_utilization' => $this->calculateClinicUtilization(),
            'subscription_health' => $this->calculateSubscriptionHealth()
        ];
    }

    private function getRecentActivity(): array
    {
        return [
            'orders' => Order_Clinic::with(['clinic:id,address', 'consultation:id,description'])
                ->select('id', 'clinic_id', 'consultation_id', 'status', 'final_price', 'created_at')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
            'payments' => Payment::with(['user:id,name', 'userSubscription.subscription:id,name'])
                ->select('id', 'user_id', 'user_subscription_id', 'amount', 'status', 'created_at')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
            'consultations' => Consultation::with(['user:id,name', 'pet:id,name'])
                ->select('id', 'user_id', 'pet_id', 'status', 'created_at')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
        ];
    }

    private function safePercentageChange($old, $new): float
    {
        if ($old == 0) return 0;
        return round((($new - $old) / $old) * 100, 2);
    }



    private function getResponseTimeDistribution(): array
    {
        return DB::table('consultations')
            ->selectRaw('
                CASE
                    WHEN TIMESTAMPDIFF(HOUR, created_at, updated_at) < 1 THEN "0-1h"
                    WHEN TIMESTAMPDIFF(HOUR, created_at, updated_at) < 6 THEN "1-6h"
                    WHEN TIMESTAMPDIFF(HOUR, created_at, updated_at) < 24 THEN "6-24h"
                    ELSE "24h+"
                END as time_range,
                COUNT(*) as count
            ')
            ->where('status', '!=', Consultation::STATUS_PENDING)
            ->groupBy('time_range')
            ->orderBy('time_range')
            ->get()
            ->pluck('count', 'time_range')
            ->toArray();
    }

    private function calculateConversionRate(): float
    {
        $consultations = Consultation::count();
        $orders = Order_Clinic::count();

        return $this->safePercentageChange($consultations, $orders);
    }

    private function calculateRetentionRate(): float
    {
        $repeatUsers = User::has('consultations', '>', 1)->count();
        $totalUsers = User::has('consultations')->count();

        return $totalUsers > 0 ? round(($repeatUsers / $totalUsers) * 100, 2) : 0;
    }

    private function calculateClinicUtilization(): float
    {
        $activeClinics = Clinic::where('status', 'active')->count();
        $utilizedClinics = Clinic::has('Order_Clinic')->count();

        return $activeClinics > 0 ? round(($utilizedClinics / $activeClinics) * 100, 2) : 0;
    }

    private function calculateSubscriptionHealth(): array
    {
        $total = User_Subscription::count();
        $active = User_Subscription::active()->count();

        return [
            'active_rate' => $total > 0 ? round(($active / $total) * 100, 2) : 0,
            'avg_remaining' => User_Subscription::avg('remaining_calls'),
            'near_expiry' => User_Subscription::where('end_date', '<=', now()->addDays(7))->count()
        ];
    }
}
