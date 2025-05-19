<?php

namespace webhubworks\verifiedentries\widgets;

use Craft;
use craft\base\Widget;
use craft\elements\Entry;
use craft\helpers\Cp;
use craft\helpers\Html;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\base\Exception;

/**
 * Expired Entries Widget widget type
 */
class EntriesToReview extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('verified-entries', 'Entries to Review');
    }

    protected static function allowMultipleInstances(): bool
    {
        return false;
    }

    public static function isSelectable(): bool
    {
        return true;
    }

    public int $limit = 10;

    public static function icon(): ?string
    {
        return 'badge-check';
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['limit'], 'number', 'integerOnly' => true];
        return $rules;
    }

    public function getSettingsHtml(): ?string
    {
        return Cp::textFieldHtml([
            'label' => Craft::t('app', 'Limit'),
            'id' => 'limit',
            'name' => 'limit',
            'value' => $this->limit,
            'size' => 2,
            'errors' => $this->getErrors('limit'),
        ]);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws Exception
     * @throws LoaderError
     */
    public function getBodyHtml(): ?string
    {
        $userId = Craft::$app->getUser()->getId();
        $enabledSections = VerifiedEntries::getInstance()->sectionSettings->getEnabledSections();

        $entries = Entry::find()
            ->status('live')
            ->sectionId($enabledSections)
            ->reviewerId($userId)
            ->isVerified(false)
            ->unique()
            ->limit($this->limit)
            ->all();

        if (empty($entries)) {
            return Html::tag('div', Craft::t('verified-entries', 'There are no entries up for review.'), [
                'class' => ['zilch', 'small'],
            ]);
        }

        return Craft::$app->getView()->renderTemplate('verified-entries/_widgets/review.twig',
            [
                'entries' => $entries,
            ]);
    }
}
