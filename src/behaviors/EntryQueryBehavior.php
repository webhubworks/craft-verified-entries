<?php

namespace webhubworks\verifiedentries\behaviors;

use craft\db\Query;
use craft\elements\db\EntryQuery;
use craft\helpers\Db;
use yii\base\Behavior;

class EntryQueryBehavior extends Behavior
{
    public ?bool $isVerified = null;
    public ?int $reviewerId = null;
    public ?string $verifiedUntil = null;

    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [
            EntryQuery::EVENT_BEFORE_PREPARE => 'beforePrepare',
        ];
    }

    public function beforePrepare(): void
    {
        /** @var Query $query */
        $query = $this->owner->query;
        /** @var Query $subQuery */
        $subQuery = $this->owner->subQuery;

        // Join our `users` table:
        if (!$this->hasJoin($query, 'veea')) {
            $query->leftJoin(['veea' => '{{%verifiedentries_entryattributes}}'], '[[veea.entryId]] = [[elements.id]]');
        }

        if (!$this->hasJoin($subQuery, 'veea')) {
            $subQuery->leftJoin(['veea' => '{{%verifiedentries_entryattributes}}'], '[[veea.entryId]] = [[elements.id]]');
        }

        // Select custom columns—Craft will attempt to assign anything defined here to the User element when populating it! Fortunately, your Behavior can also supply properties.
        $query->addSelect([
            'veea.verifiedUntilDate',
            'veea.reviewerId',
        ]);

        if ($this->isVerified !== null) {
            $this->isVerified($this->isVerified);
        }

        if ($this->reviewerId !== null) {
            $this->reviewerId($this->reviewerId);
        }

        if ($this->verifiedUntil !== null) {
            $this->verifiedUntilDate($this->verifiedUntil);
        }
    }

    private function hasJoin(Query $query, string $alias): bool
    {
        if (!$query->join)
            return false;

        foreach ($query->join as $join) {
            if (is_array($join[1])) {
                $aliases = array_keys($join[1]);
                if (in_array($alias, $aliases, true)) {
                    return true;
                }
            } else {
                return $query->isJoined($alias);
            }
        }

        return false;
    }

    public function isVerified(bool $value = true): EntryQuery
    {
        /** @var EntryQuery $query */
        $query = $this->owner;

        if ($value) {
            $query->andWhere('veea.verifiedUntilDate IS null')
                ->orWhere('veea.verifiedUntilDate >= NOW()');
        } else {
            $query->andWhere('veea.verifiedUntilDate IS NOT null')
                ->andWhere('veea.verifiedUntilDate < NOW()');
        }

        return $query;
    }

    public function reviewerId(int|array|null $value = null): EntryQuery
    {
        /** @var EntryQuery $query */
        $query = $this->owner;

        if (is_array($value) || is_int($value)) {
            $query->andWhere(['veea.reviewerId' => $value]);
        } elseif ($value === null) {
            $query->andWhere('veea.reviewerId IS null');
        }

        return $query;
    }

    public function verifiedUntilDate(mixed $value): EntryQuery
    {
        /** @var EntryQuery $query */
        $query = $this->owner;

        $query->andWhere(Db::parseDateParam('veea.verifiedUntilDate', $value));

        return $query;
    }
}
