<?php

namespace webhubworks\verifiedentries\elements\conditions;

use craft\base\conditions\BaseElementSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\elements\User;

class ReviewerConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritDoc
     */
    protected function elementType(): string
    {
        return User::class;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return 'Reviewer';
    }

    /**
     * @inheritDoc
     */
    protected  function allowMultiple(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['reviewer', 'reviewerId'];
    }

    /**
     * @inheritDoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var EntryQuery $query */
        $query->reviewerId($this->getElementIds());
    }

    /**
     * @inheritDoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Entry $element */
        return $this->matchValue($element->reviewerId);
    }
}
