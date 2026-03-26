<?php

namespace newism\wallet\migrations;

use craft\db\Migration;
use craft\db\Table;

/**
 * Install migration for the Wallet plugin.
 *
 * Single unified passes table. Platform identifiers (passTypeIdentifier, serialNumber)
 * are derived from uid + settings, not stored as columns.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%wallet_apple_device_passes}}');
        $this->dropTableIfExists('{{%wallet_apple_devices}}');
        $this->dropTableIfExists('{{%wallet_passes}}');

        return true;
    }

    /**
     * Creates the tables.
     */
    protected function createTables(): void
    {
        // Unified Passes table
        // Platform identifiers derived from uid + settings, not stored as columns
        $this->createTable('{{%wallet_passes}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'generatorHandle' => $this->string(255)->notNull(),
            'sourceId' => $this->integer()->null(),
            'sourceIndex' => $this->integer()->null(),
            'authToken' => $this->string(255)->notNull(),
            'applePassJson' => $this->text()->null(),
            'googlePassJson' => $this->text()->null(),
            'lastUpdatedAt' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Apple Devices table
        $this->createTable('{{%wallet_apple_devices}}', [
            'id' => $this->primaryKey(),
            'deviceLibraryIdentifier' => $this->string(255)->notNull(),
            'pushToken' => $this->string(255)->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Junction table for devices and passes (many-to-many)
        $this->createTable('{{%wallet_apple_device_passes}}', [
            'deviceId' => $this->integer()->notNull(),
            'passId' => $this->integer()->notNull(),
            'PRIMARY KEY([[deviceId]], [[passId]])',
        ]);
    }

    /**
     * Creates the indexes.
     */
    protected function createIndexes(): void
    {
        // Unified passes indexes
        $this->createIndex(null, '{{%wallet_passes}}', ['userId'], false);
        $this->createIndex(null, '{{%wallet_passes}}', ['generatorHandle'], false);
        $this->createIndex(null, '{{%wallet_passes}}', ['userId', 'generatorHandle', 'sourceId', 'sourceIndex'], false);

        // Apple Devices indexes
        $this->createIndex(null, '{{%wallet_apple_devices}}', ['deviceLibraryIdentifier'], true);

        // Junction table indexes
        $this->createIndex(null, '{{%wallet_apple_device_passes}}', ['passId'], false);
    }

    /**
     * Adds the foreign keys.
     */
    protected function addForeignKeys(): void
    {
        // Unified pass -> User foreign key
        $this->addForeignKey(
            null,
            '{{%wallet_passes}}',
            ['userId'],
            Table::USERS,
            ['id'],
            'CASCADE',
            'CASCADE'
        );

        // Junction table -> Device foreign key
        $this->addForeignKey(
            null,
            '{{%wallet_apple_device_passes}}',
            ['deviceId'],
            '{{%wallet_apple_devices}}',
            ['id'],
            'CASCADE',
            'CASCADE'
        );

        // Junction table -> Pass foreign key
        $this->addForeignKey(
            null,
            '{{%wallet_apple_device_passes}}',
            ['passId'],
            '{{%wallet_passes}}',
            ['id'],
            'CASCADE',
            'CASCADE'
        );
    }
}
