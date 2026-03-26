<?php

namespace newism\wallet\jobs;

use Craft;
use craft\elements\User;
use craft\queue\BaseJob;
use Exception;
use newism\wallet\models\Pass;
use newism\wallet\Wallet;

/**
 * Queue job to update wallet passes when user data changes.
 *
 * Regenerates pass content for both platforms and only pushes
 * updates to Apple devices when the content actually changed.
 */
class UpdateUserPassesJob extends BaseJob
{
    public int $userId;

    public function execute($queue): void
    {
        $user = User::find()->id($this->userId)->one();
        if (!$user) {
            Craft::warning("UpdateUserPassesJob: User {$this->userId} not found", Wallet::LOG);
            return;
        }

        $passes = Pass::find()->userId($this->userId)->all();
        if (empty($passes)) {
            return;
        }

        $appleService = Wallet::getInstance()->getApplePassService();
        $googleService = Wallet::getInstance()->getGooglePassService();

        foreach ($passes as $pass) {
            // Apple: regenerate pass JSON and check for changes
            try {
                $appleChanged = $appleService->updateApplePassJson($pass);
                if ($appleChanged) {
                    Craft::info("UpdateUserPassesJob: Apple pass changed for pass {$pass->id}, sending push", Wallet::LOG);
                    $appleService->sendPushNotifications($pass);
                }
            } catch (Exception $e) {
                Craft::warning("UpdateUserPassesJob: Apple update failed for pass {$pass->id}: {$e->getMessage()}", Wallet::LOG);
            }

            // Google: create or update the pass object via API (idempotent)
            try {
                $googleService->createOrUpdatePassObject($pass);
            } catch (Exception $e) {
                Craft::warning("UpdateUserPassesJob: Google update failed for pass {$pass->id}: {$e->getMessage()}", Wallet::LOG);
            }
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('app', 'Updating wallet passes for user {userId}', [
            'userId' => $this->userId,
        ]);
    }
}
