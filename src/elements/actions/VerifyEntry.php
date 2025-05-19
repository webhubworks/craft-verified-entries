<?php

namespace webhubworks\verifiedentries\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\Entry;

/**
 * Verify Entry element action
 */
class VerifyEntry extends ElementAction
{
    public string|\DateTime|null $date = null;

    public static function displayName(): string
    {
        return Craft::t('verified-entries', 'Verify Entry');
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
                      
                      const modal = new Craft.CpModal('verified-entries/entries/request-period');
                      
                      modal.on('submit', ({response}) => {
                          elementIndex.submitAction($type, response.data);
                          elementIndex.setIndexAvailable();
                      });
                      
                      modal.on('close', () => {
                          elementIndex.setIndexAvailable();
                      })
                    },
                });
            })();
        JS, [static::class]);

        return null;
    }

    /**
     * @throws \Exception
     */
    public function performAction(Craft\elements\db\ElementQueryInterface $query): bool
    {
        $elements = $query->all();
        $elementsService = \Craft::$app->getElements();

        $successCount = count(array_filter($elements, function (Entry $entry) use ($elementsService) {
            try {
                $entry->setVerifiedUntilDate($this->date);
                $elementsService->saveElement($entry);
                return true;
            } catch (\Throwable) {
                return false;
            }
        }));

        if ($successCount !== count($elements)) {
            $this->setMessage(Craft::t('verified-entries', 'Could not verify all entries.'));
            return false;
        }

        $this->setMessage(Craft::t('verified-entries', 'Entries verified.'));
        return true;
    }
}
