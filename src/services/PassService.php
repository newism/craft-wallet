<?php

namespace newism\wallet\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use Exception;
use newism\wallet\db\WalletTable;
use newism\wallet\models\AddToWalletResult;
use newism\wallet\models\Pass;
use newism\wallet\Wallet;
use yii\base\InvalidArgumentException;
use yii\web\ForbiddenHttpException;

/**
 * Pass Service
 */
class PassService extends Component
{
    public function savePass(Pass $pass): bool
    {
        $isNew = !$pass->id;

        if (!$pass->validate()) {
            return false;
        }

        $db = Craft::$app->getDb();
        $data = Db::prepareValuesForDb($pass->toArray());

        if (!$isNew) {
            $db->createCommand()->update(
                WalletTable::PASSES,
                $data,
                ['id' => $pass->id],
            )->execute();
        } else {
            unset($data['id']);
            $db->createCommand()->insert(
                WalletTable::PASSES,
                $data,
            )->execute();
            $pass->id = (int)$db->getLastInsertID();
        }

        return true;
    }

    public function deletePass(Pass $pass): int
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $canDelete = $currentUser->id === $pass->userId
            ? $currentUser->can(Wallet::PERMISSION_DELETE_PASSES)
            : $currentUser->can(Wallet::PERMISSION_DELETE_OTHER_USERS_PASSES);

        if (!$canDelete) {
            throw new ForbiddenHttpException();
        }

        $wallet = Wallet::getInstance();

        // Apple: void the pass before deleting so devices show it as voided
        // (Apple never auto-removes passes — 401 just orphans them)
        try {
            $wallet->getApplePassService()->voidPass($pass);
        } catch (Exception $e) {
            Craft::warning("Failed to void Apple pass {$pass->id}: {$e->getMessage()}", Wallet::LOG);
        }

        // Google: set state to INACTIVE to hide from wallet, then delete
        try {
            $wallet->getGooglePassService()->deletePassObject($pass);
        } catch (Exception $e) {
            Craft::warning("Failed to delete Google pass object for pass {$pass->id}: {$e->getMessage()}", Wallet::LOG);
        }

        // Delete the DB record
        return Db::delete(WalletTable::PASSES, [
            'id' => $pass->id,
        ]);
    }

    public function addToWallet(Pass $pass, string $platform): AddToWalletResult
    {
        $wallet = Wallet::getInstance();

        return match ($platform) {
            'apple' => AddToWalletResult::forApple(
                pkPass: $wallet->getApplePassService()->generatePkpass($pass),
            ),
            'google' => AddToWalletResult::forGoogle(
                redirectUrl: $wallet->getGooglePassService()->generateAddToWalletUrl($pass),
            ),
            default => throw new InvalidArgumentException("Unsupported platform: $platform"),
        };
    }
}
