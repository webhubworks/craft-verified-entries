<?php

namespace webhubworks\verifiedentries\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\elements\Entry;

/**
 * Install migration.
 */
class Install extends Migration
{
    const ENTRYATTRIBUTES_TABLE = '{{%verifiedentries_entryattributes}}';

    const ENTRYATTRIBUTES_SECTIONS = '{{%verifiedentries_sections}}';

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTable(self::ENTRYATTRIBUTES_TABLE, [
            'id' => $this->primaryKey(),
            'entryId' => $this->integer()->notNull()->unique(),
            'reviewerId' => $this->integer()->null(),
            'verifiedUntilDate' => $this->dateTime()->null(),
        ]);

        $this->addForeignKey(null, self::ENTRYATTRIBUTES_TABLE, ['entryId'], Table::ENTRIES, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, self::ENTRYATTRIBUTES_TABLE, ['reviewerId'], Table::USERS, ['id'], 'SET NULL');

        $this->createTable(self::ENTRYATTRIBUTES_SECTIONS, [
            'id' => $this->primaryKey(),
            'sectionId' => $this->integer()->notNull()->unique(),
            'reviewerId' => $this->integer()->null(),
            'enabled' => $this->boolean()->defaultValue(false),
            'defaultPeriod' => $this->string()->null(),
        ]);

        $this->addForeignKey(null, self::ENTRYATTRIBUTES_SECTIONS, ['sectionId'], Table::SECTIONS, ['id'], 'CASCADE');
        $this->addForeignKey(null, self::ENTRYATTRIBUTES_SECTIONS, ['reviewerId'], Table::USERS, ['id'], 'SET NULL');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(self::ENTRYATTRIBUTES_TABLE);
        $this->dropTableIfExists(self::ENTRYATTRIBUTES_SECTIONS);

        return true;
    }
}
