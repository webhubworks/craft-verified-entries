<?php

namespace webhubworks\verifiedentries\behaviors;

use craft\base\Element;
use craft\elements\Entry;
use craft\elements\User;
use craft\events\ModelEvent;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use DateTime;
use webhubworks\verifiedentries\migrations\Install;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\base\Behavior;
use yii\db\Exception;

/**
 * EntryQueryBehavior
 *
 * @property Entry $owner
 */
class EntryBehavior extends Behavior
{
    private ?DateTime $_verifiedUntilDate = null;
    private ?int $_reviewerId = null;
    private ?User $_reviewer = null;
    private bool $_isVerified = false;

    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [
            Element::EVENT_AFTER_SAVE => 'afterSave',
        ];
    }

    /**
     * Saves additional attributes, in response to the parent User element being saved.
     *
     * {@see Db::upsert()} is used to simplify the process of inserting *or* updating a record. Also notice the reference to `$this->owner` when getting the ID! This refers to the Entry element the Behavior is attached to.
     *
     * @param ModelEvent $event
     * @throws Exception
     */
    public function afterSave(ModelEvent $event): void
    {
        /** @var Entry $entry */
        $entry = $event->sender;

        Db::upsert(Install::ENTRYATTRIBUTES_TABLE, [
            'entryId' => $entry->id,
            'reviewerId' => $this->_reviewerId,
            'verifiedUntilDate' => Db::prepareDateForDb($this->_verifiedUntilDate),
        ], [
            'reviewerId' => $this->_reviewerId,
            'verifiedUntilDate' => Db::prepareDateForDb($this->_verifiedUntilDate),
        ]);
    }

    /**
     * Sets the verified until date.
     *
     * @param mixed $value The property value
     */
    public function setVerifiedUntilDate(mixed $value)
    {
        $verifiedUntilDate = DateTimeHelper::toDatetime($value, true);

        if ($verifiedUntilDate) {
            $this->_verifiedUntilDate = $verifiedUntilDate;
        } else {
            $this->_verifiedUntilDate = null;
        }
    }

    public function getVerifiedUntilDate(): ?DateTime
    {
        return $this->_verifiedUntilDate;
    }

    public function getReviewerId(): ?int
    {
        return $this->_reviewerId;
    }

    public function setReviewerId(mixed $value)
    {
        if (!$value) {
            $this->_reviewerId = null;
        } elseif (is_string($value)) {
            $this->_reviewerId = (int) $value;
        } elseif (is_int($value)) {
            $this->_reviewerId = $value;
        }
    }

    public function getReviewer(): ?\craft\elements\User
    {
        if (!$this->_reviewerId) {
            return null;
        }

        return \Craft::$app->users->getUserById($this->_reviewerId);
    }

    public function getHasVerifiedUntilDate(): bool
    {
        return $this->_verifiedUntilDate !== null;
    }

    public function getIsVerified(): bool
    {
        if ($this->_verifiedUntilDate === null) {
            return true;
        }

        return $this->_verifiedUntilDate > new DateTime();
    }

    public function getIsSectionEnabledForVerification(): bool
    {
        $sectionId = $this->owner->sectionId;

        return VerifiedEntries::getInstance()
            ->sectionSettings
            ->getIsEnabledForSection($sectionId);
    }
}
