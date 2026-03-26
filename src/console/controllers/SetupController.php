<?php

namespace newism\wallet\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use Exception;
use newism\wallet\Wallet;
use yii\console\ExitCode;

/**
 * Wallet setup console commands.
 *
 * Usage:
 *   php craft wallet/setup/google-class - Create or update the Google Wallet pass class
 *   php craft wallet/setup/env-base64   - Base64-encode credentials and update .env
 */
class SetupController extends Controller
{
    /**
     * Creates or updates Google Wallet pass classes for all registered generators.
     *
     * Each generator defines its own class template via buildGooglePassClassPayload().
     * Run this during initial setup and whenever you update a generator's class template.
     *
     * @return int
     */
    public function actionGoogleClass(): int
    {
        $generators = Wallet::getInstance()->getGeneratorService()->getGenerators();
        $service = Wallet::getInstance()->getGooglePassService();
        $hasErrors = false;

        $this->stdout("Setting up Google Wallet pass classes for " . count($generators) . " generator(s)...\n", Console::FG_CYAN);

        foreach ($generators as $handle => $generator) {
            $this->stdout("\n  [{$handle}] {$generator::displayName()}...", Console::FG_CYAN);

            try {
                $classSuffix = $generator->getGoogleClassSuffix();
                $classId = $service->getIssuerId() . '.' . $classSuffix;
                $classPayload = $generator->buildGooglePassClassPayload($classId);

                $googlePassType = $generator->getGooglePassType();
                $class = $service->createOrUpdatePassClassFromPayload($classId, $classPayload, $googlePassType, $generator);

                $this->stdout(" ✓ {$class['id']}\n", Console::FG_GREEN);
            } catch (Exception $e) {
                $this->stderr(" ✗ {$e->getMessage()}\n", Console::FG_RED);
                $hasErrors = true;
            }
        }

        $this->stdout("\n");

        if ($hasErrors) {
            $this->stderr("Some classes failed to create/update. Check the errors above.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("All pass classes created/updated successfully.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    private const DELIMITER_START = '###> newism/craft-wallet ###';
    private const DELIMITER_END = '###< newism/craft-wallet ###';

    /**
     * Base64-encodes wallet credentials and writes them to .env.
     *
     * Reads the Apple P12 certificate and Google service account JSON from
     * the paths configured in the settings model (apple.p12Path and
     * google.serviceAccountJsonPath), base64-encodes them, and appends or
     * updates the values in the project's .env file using delimiters.
     *
     * @return int
     */
    public function actionEnvBase64(): int
    {
        $rootPath = Craft::getAlias('@root');
        $envPath = $rootPath . '/.env';

        if (!file_exists($envPath)) {
            $this->stderr("Error: .env file not found at {$envPath}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $settings = Wallet::getInstance()->getSettings();
        $vars = [];

        // Apple P12 certificate
        $p12Path = $settings->apple->p12Path ? Craft::getAlias($settings->apple->p12Path) : null;
        if ($p12Path && file_exists($p12Path)) {
            $vars['WALLET_APPLE_P12_BASE64'] = base64_encode(file_get_contents($p12Path));
            $this->stdout("Encoded: {$settings->apple->p12Path}\n", Console::FG_GREEN);
        } else {
            $vars['WALLET_APPLE_P12_BASE64'] = '';
            $this->stdout("Skipped: apple.p12Path " . ($p12Path ? "(not found at {$p12Path})" : "(not configured)") . "\n", Console::FG_YELLOW);
        }

        // Google service account JSON
        $saPath = $settings->google->serviceAccountJsonPath ? Craft::getAlias($settings->google->serviceAccountJsonPath) : null;
        if ($saPath && file_exists($saPath)) {
            $vars['WALLET_GOOGLE_SERVICE_ACCOUNT_JSON_BASE64'] = base64_encode(file_get_contents($saPath));
            $this->stdout("Encoded: {$settings->google->serviceAccountJsonPath}\n", Console::FG_GREEN);
        } else {
            $vars['WALLET_GOOGLE_SERVICE_ACCOUNT_JSON_BASE64'] = '';
            $this->stdout("Skipped: google.serviceAccountJsonPath " . ($saPath ? "(not found at {$saPath})" : "(not configured)") . "\n", Console::FG_YELLOW);
        }

        // Build the delimited block
        $block = self::DELIMITER_START . "\n";
        foreach ($vars as $key => $value) {
            $block .= "{$key}=\"{$value}\"\n";
        }
        $block .= self::DELIMITER_END;

        // Read current .env
        $envContents = file_get_contents($envPath);

        // Check if delimited block already exists
        $pattern = '/' . preg_quote(self::DELIMITER_START, '/') . '.*?' . preg_quote(self::DELIMITER_END, '/') . '/s';

        if (preg_match($pattern, $envContents)) {
            // Replace existing block
            $envContents = preg_replace($pattern, $block, $envContents);
            $this->stdout("\nUpdated existing wallet block in .env\n", Console::FG_CYAN);
        } else {
            // Append new block
            $envContents = rtrim($envContents) . "\n\n" . $block . "\n";
            $this->stdout("\nAppended wallet block to .env\n", Console::FG_CYAN);
        }

        file_put_contents($envPath, $envContents);
        $this->stdout("Done!\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
