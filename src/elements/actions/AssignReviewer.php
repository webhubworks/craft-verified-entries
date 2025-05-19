<?php

namespace webhubworks\verifiedentries\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\Entry;
use craft\elements\User;

/**
 * Assign Reviewer element action
 */
class AssignReviewer extends ElementAction
{
    public ?int $reviewerId = null;

    public static function displayName(): string
    {
        return Craft::t('verified-entries', 'Assign Reviewer');
    }

    public function getTriggerHtml(): ?string
    {
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
            (() => {
                new Craft.ElementActionTrigger({
                    type: $type,

                    // Whether this action should be available when multiple elements are selected
                    bulk: true,

                    // Return whether the action should be available depending on which elements are selected
                    validateSelection: (selectedItems, elementIndex) => {
                      return true;
                    },

                    activate: (selectedItems, elementIndex) => {
                      elementIndex.setIndexBusy();
                      
                      Craft.createElementSelectorModal('craft\\\\elements\\\\User', {
                          multiSelect: false,
                          criteria: {
                              'status': 'active',
                              'can': 'verifyEntries',
                          },
                          onSelect: ([user]) => {
                              elementIndex.submitAction($type, { reviewerId: user.id })
                          },
                          onHide: () => {
                              elementIndex.setIndexAvailable();
                          }
                      })
                    },
                });
            })();
        JS, [static::class]);

        return null;
    }

    public function performAction(Craft\elements\db\ElementQueryInterface $query): bool
    {
        $elements = $query->all();
        $elementsService = \Craft::$app->getElements();

        $sucessCount = count(array_filter($elements, function (Entry $entry) use ($elementsService) {
            try {
                $entry->setReviewerId($this->reviewerId);
                $elementsService->saveElement($entry);
                return true;
            } catch (\Throwable) {
                return false;
            }
        }));

        if ($sucessCount !== count($elements)) {
            $this->setMessage('Could not assign Reviewer to all entries.');
            return false;
        }

        $this->setMessage('Entries assigned.');
        return true;
    }
}
