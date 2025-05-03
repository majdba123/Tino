<?php

namespace App\Services;

use App\Models\Subscription;
use Illuminate\Support\Str;

class SubscriptionService
{
    public function createSubscription(array $data): Subscription
    {
        $data['slug'] = Str::slug($data['name']);

        return Subscription::create($data);
    }

    public function updateSubscription(Subscription $subscription, array $data): Subscription
    {
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $subscription->update($data);

        return $subscription->fresh();
    }

    public function deleteSubscription(Subscription $subscription): bool
    {
        return $subscription->delete();
    }

    public function activateSubscription(Subscription $subscription): Subscription
    {
        $subscription->update(['is_active' => true]);

        return $subscription->fresh();
    }

    public function deactivateSubscription(Subscription $subscription): Subscription
    {
        $subscription->update(['is_active' => false]);

        return $subscription->fresh();
    }

    public function getActiveSubscriptions()
    {
        return Subscription::active()->get();
    }

    public function getSubscriptions(array $filters = [])
    {
        $query = Subscription::query();

        // Apply filters
        foreach ($filters as $field => $value) {
            if (in_array($field, $this->getFilterableFields())) {
                $this->applyFilter($query, $field, $value);
            }
        }

        return $query->get();
    }

    protected function getFilterableFields(): array
    {
        return [
            'name',
            'price_min',
            'price_max',
            'type',
            'is_active'
        ];
    }

    protected function applyFilter($query, string $field, $value)
    {
        if ($value === null || $value === '') {
            return;
        }

        switch ($field) {
            case 'name':
                $query->where('name', 'like', "%{$value}%");
                break;

            case 'price_min':
                $query->where('price', '>=', $value);
                break;

            case 'price_max':
                $query->where('price', '<=', $value);
                break;

            case 'type':
                $query->where('type', $value);
                break;

            case 'is_active':
                if ($value === 'all') {
                    break;
                }
                $query->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
                break;
        }
    }
}
