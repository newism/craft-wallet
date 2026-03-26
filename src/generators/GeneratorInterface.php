<?php

namespace newism\wallet\generators;

use craft\elements\User;
use newism\wallet\models\Pass;

/**
 * Interface for wallet pass generators.
 *
 * Each generator defines a type of pass (membership, event ticket, etc.)
 * and knows how to build pass data for a given user and optional source.
 *
 * The interface is always-plural: generators return arrays of pass data,
 * even for single-pass cases like membership cards. This avoids special-casing
 * single vs multiple throughout the codebase.
 */
interface GeneratorInterface
{
    /**
     * Returns the unique handle for this generator (e.g. 'membership', 'event-ticket').
     * Stored in the `generator` column on pass tables.
     */
    public static function handle(): string;

    /**
     * Returns a human-readable display name (e.g. 'Membership Card', 'Event Ticket').
     */
    public static function displayName(): string;

    public function userCanCreatePass(User $user): bool;

    /**
     * Creates and populates the Apple pass object for this generator.
     *
     * Returns the appropriate Passbook type (StoreCard, EventTicket, Coupon,
     * BoardingPass, Generic) fully populated with content (fields, colors,
     * barcode, images, etc.). The service will force-set passTypeIdentifier,
     * webServiceURL, and authenticationToken after this returns.
     *
     * Use $pass->uid as the serial number when constructing the pass type.
     *
     * @param Pass $pass The pass (provides user, sourceId, uid for serialNumber)
     * @return \Passbook\Pass The fully populated Apple pass object
     */
    public function createApplePass(Pass $pass): \Passbook\Pass;

    /**
     * Returns the Google Wallet pass type for API endpoints.
     *
     * Determines which Google Wallet API resource to use:
     * - 'generic' → genericClass / genericObject (membership, loyalty, etc.)
     * - 'eventTicket' → eventTicketClass / eventTicketObject (event tickets)
     *
     * @see https://developers.google.com/wallet/tickets/events
     * @see https://developers.google.com/wallet/generic
     */
    public function getGooglePassType(): string;

    /**
     * Returns the Google Wallet class suffix for this generator.
     * Each generator type gets its own Google pass class.
     */
    public function getGoogleClassSuffix(): string;

    /**
     * Builds the Google Wallet pass object payload for this generator.
     *
     * Returns the full object payload array populated with content (cardTitle,
     * header, barcode, images, etc.). The service will force-set `id`, `classId`,
     * and `state` after this returns.
     *
     * @param Pass $pass The pass (provides user, sourceId, uid)
     * @return array The Google pass object payload
     */
    public function createGooglePassObject(Pass $pass): array;

    /**
     * Returns the template and variables for this generator's section
     * in the CP user settings "Wallet Passes" tab.
     *
     * Return [templatePath, variables] — spread directly into ->contentTemplate(...).
     * The generator owns the entire section: eligible items, existing passes, buttons.
     *
     * @param User $user The user being edited
     * @param Pass[] $passes Existing passes for this user + generator (pre-queried)
     * @return array{0: string, 1: array} [template, variables]
     */
    public function getUserSettingsContentTemplate(User $user, array $passes): array;

    /**
     * Batch-loads source objects for the given source IDs.
     *
     * Called by PassQuery when with(['source']) is used. Returns a map
     * of sourceId => source object. The source can be any type — Craft
     * elements, Yii models, etc. If the source implements Chippable,
     * it will be rendered as a chip in the CP.
     *
     * @param int[] $sourceIds The source IDs to load
     * @return array<int, mixed> Map of sourceId => source object
     */
    public function loadSources(array $sourceIds): array;

    /**
     * Builds the Google Wallet pass class payload for this generator.
     *
     * Called by the `wallet/setup/google-class` console command to register
     * each generator's class template with Google. The command iterates all
     * registered generators and creates/updates a class for each.
     *
     * Return the full class payload array. The plugin will force-set
     * `id` and `callbackOptions` after this method returns.
     *
     * @param string $classId The full class ID (issuerId.classSuffix)
     * @return array The Google pass class payload
     */
    public function buildGooglePassClassPayload(string $classId): array;
}
