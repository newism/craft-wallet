<?php

namespace modules\walletevents\generators;

use craft\elements\User;
use newism\wallet\generators\GeneratorInterface;
use newism\wallet\models\Pass;

/**
 * Demo Event Ticket Generator — minimal reference implementation.
 *
 * Implements all required GeneratorInterface methods.
 * See the full craft-wallet-events plugin for a production implementation.
 */
class DemoEventTicketGenerator implements GeneratorInterface
{
    public static function handle(): string
    {
        return 'demo-event-ticket';
    }

    public static function displayName(): string
    {
        return 'Demo Event Tickets';
    }

    public function userCanCreatePass(User $user): bool
    {
        return false;
    }

    public function loadSources(array $sourceIds): array
    {
        return [];
    }

    public function getUserSettingsContentTemplate(User $user, array $passes): array
    {
        return ['wallet/users/_generator-stub', [
            'generatorName' => self::displayName(),
        ]];
    }

    public function createApplePass(Pass $pass): \Passbook\Pass
    {
        $eventTicket = new \Passbook\Type\EventTicket($pass->uid, 'Demo Event');
        // TODO: Populate with event-specific fields, colors, barcode, images
        return $eventTicket;
    }

    public function getGooglePassType(): string
    {
        return 'generic';
    }

    public function getGoogleClassSuffix(): string
    {
        return 'demo-event-ticket';
    }

    public function createGooglePassObject(Pass $pass): array
    {
        // TODO: Build Google pass object payload
        return [];
    }

    public function buildGooglePassClassPayload(string $classId): array
    {
        return [
            'multipleDevicesAndHoldersAllowedStatus' => 'ONE_USER_ALL_DEVICES',
        ];
    }
}
