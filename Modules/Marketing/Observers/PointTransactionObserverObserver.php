<?php

namespace Modules\Marketing\Observers;

use Modules\Marketing\Entities\LoyaltyTier;
use Modules\Marketing\Entities\PointTransactionObserver;

class PointTransactionObserverObserver
{
    /**
     * Handle the PointTransactionObserver "created" event.
     */
    public function created(PointTransactionObserver $pointtransactionobserver): void {
        $user = $pointtransactionobserver->user;

        if (!$user){
            return;
        }

        $user->increment('point_balance', $pointtransactionobserver->points);
        $this->checkAndUpgradeTier($user);
    }

    protected function checkAndUpgradeTier(User $user): void
    {
        $currentBalance = $user->fresh()->point_balance; //

        $newTier = LoyaltyTier::where('min_points', '<=', $currentBalance)
            ->orderBy('min_points', 'DESC')
            ->first();

        if ($newTier && $user->loyalty_tier_id != $newTier->id) { //

            $user->loyalty_tier_id = $newTier->id; //
            $user->save();

            // event(new UserTierUpgraded($user, $newTier));
        }
    }

    /**
     * Handle the PointTransactionObserver "updated" event.
     */
    public function updated(PointTransactionObserver $pointtransactionobserver): void {}

    /**
     * Handle the PointTransactionObserver "deleted" event.
     */
    public function deleted(PointTransactionObserver $pointtransactionobserver): void {}

    /**
     * Handle the PointTransactionObserver "restored" event.
     */
    public function restored(PointTransactionObserver $pointtransactionobserver): void {}

    /**
     * Handle the PointTransactionObserver "force deleted" event.
     */
    public function forceDeleted(PointTransactionObserver $pointtransactionobserver): void {}
}
