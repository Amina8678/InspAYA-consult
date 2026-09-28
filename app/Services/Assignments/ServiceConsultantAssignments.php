<?php

namespace App\Services\Assignments;

use App\Models\Consultant;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * The one place that writes the service_consultant pivot (FR-TEAM-02,
 * FR-ADM-05/07). Services assign consultants and consultants assign services;
 * both go through here, so the two sides always agree:
 *
 * - a pair is either absent, "supporting" (is_lead false) or "lead";
 * - within a service, consultants are ordered by the consultants' own display
 *   order (pivot sort_order is renumbered after every change, including when a
 *   consultant is moved up or down).
 */
class ServiceConsultantAssignments
{
    public const ROLES = ['none', 'supporting', 'lead'];

    /**
     * Set a service's consultants. $roles: consultant id => none|supporting|lead.
     * Consultants not listed are removed.
     *
     * @param  array<int|string, string>  $roles
     */
    public function setForService(Service $service, array $roles): void
    {
        DB::transaction(function () use ($service, $roles) {
            $chosen = array_filter($roles, fn ($role) => $role !== 'none');

            $service->consultants()->sync(collect($chosen)
                ->mapWithKeys(fn ($role, $id) => [(int) $id => ['is_lead' => $role === 'lead', 'sort_order' => 0]])
                ->all());

            $this->renumber([$service->id]);
        });
    }

    /**
     * Set a consultant's services. $roles: service id => none|supporting|lead.
     * Only the listed services change; other consultants on those services
     * are untouched.
     *
     * @param  array<int|string, string>  $roles
     */
    public function setForConsultant(Consultant $consultant, array $roles): void
    {
        DB::transaction(function () use ($consultant, $roles) {
            $current = $consultant->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all();
            $chosen = array_filter($roles, fn ($role) => $role !== 'none');

            $consultant->services()->sync(collect($chosen)
                ->mapWithKeys(fn ($role, $id) => [(int) $id => ['is_lead' => $role === 'lead']])
                ->all());

            $this->renumber(array_unique(array_merge($current, array_map('intval', array_keys($chosen)))));
        });
    }

    /**
     * After a consultant moves in the display order, reorder every service
     * that includes them.
     */
    public function consultantMoved(Consultant $consultant): void
    {
        $this->renumber($consultant->services()->pluck('services.id')->all());
    }

    /**
     * @return array<int, string> consultant id => lead|supporting
     */
    public function rolesForService(Service $service): array
    {
        if (! $service->exists) {
            return [];
        }

        return $service->consultants()->get(['consultants.id'])
            ->mapWithKeys(fn (Consultant $c) => [$c->id => $c->pivot->is_lead ? 'lead' : 'supporting'])
            ->sortKeys()
            ->all();
    }

    /**
     * @return array<int, string> service id => lead|supporting
     */
    public function rolesForConsultant(Consultant $consultant): array
    {
        if (! $consultant->exists) {
            return [];
        }

        return $consultant->services()->get(['services.id'])
            ->mapWithKeys(fn (Service $s) => [$s->id => $s->pivot->is_lead ? 'lead' : 'supporting'])
            ->sortKeys()
            ->all();
    }

    /**
     * Pivot sort_order 1..n per service, following the consultants' order.
     *
     * @param  list<int|string>  $serviceIds
     */
    private function renumber(array $serviceIds): void
    {
        foreach ($serviceIds as $serviceId) {
            $ordered = DB::table('service_consultant')
                ->join('consultants', 'consultants.id', '=', 'service_consultant.consultant_id')
                ->where('service_consultant.service_id', $serviceId)
                ->orderBy('consultants.sort_order')
                ->orderBy('consultants.id')
                ->pluck('consultants.id');

            foreach ($ordered as $index => $consultantId) {
                DB::table('service_consultant')
                    ->where('service_id', $serviceId)
                    ->where('consultant_id', $consultantId)
                    ->update(['sort_order' => $index + 1]);
            }
        }
    }
}
