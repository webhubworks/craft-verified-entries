<?php

namespace webhubworks\verifiedentries\services;

use Craft;
use craft\db\Query;
use craft\elements\conditions\entries\EntryCondition;
use craft\elements\Entry;
use craft\elements\User;
use craft\errors\SiteNotFoundException;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use webhubworks\verifiedentries\elements\conditions\ReviewerConditionRule;
use webhubworks\verifiedentries\elements\conditions\VerifiedConditionRule;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\base\Component;

class Verification extends Component
{
    public static array $INTERVALS = [
        "P7D",
        "P30D",
        "P90D",
        "P1Y"
    ];

    public const SPECIFIC_DATE = 'specific-date';

    public const INDEFINITELY = 'indefinitely';

    public static function getOptions(?\DateTime $currentValue = null, ?int $sectionId = null): array
    {
        $formatter = new Formatter();

        if ($sectionId !== null) {
            [$reviewerId, $defaultPeriod] = VerifiedEntries::getInstance()->sectionSettings->getDefaultSettingsForSection($sectionId);
        }

        $options = [];

        if ($currentValue) {
            $options[] = [
                'label' => $formatter->asDate($currentValue),
                'value' => $currentValue->format('Y-m-d'),
            ];
        }

        foreach (self::$INTERVALS as $interval) {
            $dateInterval = new \DateInterval($interval);
            $date = DateTimeHelper::now()->add($dateInterval);

            if ($currentValue && $date->format('Y-m-d') === $currentValue->format('Y-m-d')) {
                continue;
            }

            $options[] = [
                'label' => $formatter->asDate($date),
                'value' => $date->format('Y-m-d'),
                'data' => [
                    'hint' => implode(' ', [
                        DateTimeHelper::humanDuration($dateInterval),
                        $interval === $defaultPeriod ? Craft::t('verified-entries', '(Standard)') : ''
                    ])
                ],
            ];
        }

        $options[] = [
            'label' => Craft::t('verified-entries', 'Indefinitely'),
            'value' => false,
        ];

        return $options;
    }

    public static function getDefaultOptions(): array
    {
        $options = [];

        foreach (self::$INTERVALS as $interval) {
            $dateInterval = new \DateInterval($interval);

            $options[] = [
                'label' => DateTimeHelper::humanDuration($dateInterval),
                'value' => $interval,
            ];
        }

        $options[] = [
            'label' => Craft::t('verified-entries', 'Indefinitely'),
            'value' => self::INDEFINITELY,
        ];

        return $options;
    }

    public static function getSelectOptions(): array
    {
        $options = self::getDefaultOptions();

        $options[] = [
            'label' => Craft::t('verified-entries', 'Specific Date'),
            'value' => self::SPECIFIC_DATE,
        ];

        return $options;
    }

    public static function getAddOptionFn(): string
    {
        return <<<JS
            (createOption, selectize) => {
                const modal = new Craft.CpModal('verified-entries/custom-date');
        
                modal.on('submit', ({response}) => {
                    const {date, label} = response.data;
        
                    createOption({
                        text: label,
                        value: date,
                    });
        
                    setTimeout(() => {
                        selectize.setValue(date);
                    }, 10);
                });
        
                modal.on('close', () => {
                    if (selectize.lastValidValue === '__add__') {
                        selectize.lastValidValue = '';
                    }
                    selectize.focus();
                    Garnish.uiLayerManager.removeLayerAtIndex(1)
                });
            }
        JS;
    }

    /**
     * @throws SiteNotFoundException
     */
    public static function checkExpiredVerifications(): array
    {
        $enabledSections = (new Query())
            ->select(['sectionId', 'reviewerId'])
            ->from('{{%verifiedentries_sections}}')
            ->where(['=', 'enabled', true])
            ->collect();

        $primarySite = Craft::$app->sites->getPrimarySite();

        // Find entries where verification date is in the past
        $expiredEntries = (new Query())
            ->select([
                'veea.entryId',
                'veea.reviewerId',
                'veea.verifiedUntilDate',
                'entries.sectionId',
                'es.title',
                'sections.handle AS sectionHandle',
            ])
            ->from(['veea' => '{{%verifiedentries_entryattributes}}'])
            ->leftJoin('{{%elements}}', '[[elements.id]] = [[veea.entryId]] AND [[elements.enabled]] = true')
            ->leftJoin(
                '{{%elements_sites}} es',
                '[[es.elementId]] = [[veea.entryId]] AND [[es.siteId]] = :siteId',
                ['siteId' => $primarySite->id]
            )
            ->leftJoin('{{%entries}}', '[[entries.id]] = [[veea.entryId]]')
            ->innerJoin('{{%sections}}', '[[sections.id]] = [[entries.sectionId]]')
            ->where(['<', 'veea.verifiedUntilDate', Db::prepareDateForDb(new \DateTime())])
            ->andWhere('elements.canonicalId IS null')
            ->andWhere(['entries.sectionId' => $enabledSections->pluck('sectionId')])
            ->all();

        if (!empty($expiredEntries)) {
            // Log the expired entries
            Craft::warning(
                Craft::t(
                    'verified-entries',
                    'Found {count} entries with expired verification dates',
                    ['count' => count($expiredEntries)]
                ),
                'verified-entries'
            );

            self::notifyAboutExpiredEntries($expiredEntries);
        }

        return $expiredEntries;
    }

    private static function notifyAboutExpiredEntries(array $expiredEntries): void
    {
        $entriesPerReviewer = collect($expiredEntries)
            ->groupBy('reviewerId');

        foreach ($entriesPerReviewer as $reviewerId => $entries) {
            /** @var User $reviewer */
            $reviewer = User::find()
                ->id($reviewerId)
                ->status('active')
                ->one();

            if (!$reviewer) {
                Craft::warning(
                    "Could not notify reviewer ($reviewerId) about expired entries",
                    'verified-entries'
                );
                continue;
            }

            Notifications::sendExpiredNotification($reviewer, $entries);
        }
    }

    public static function getFilterParams(?int $reviewerId = null): string
    {
        $condition = new EntryCondition(Entry::class);

        $verifiedRule = new VerifiedConditionRule();
        $verifiedRule->value = false;
        $condition->addConditionRule($verifiedRule);

        if ($reviewerId !== null) {
            $reviewerRule = new ReviewerConditionRule();
            $reviewerRule->setElementIds([$reviewerId]);
            $condition->addConditionRule($reviewerRule);
        }

        $config = [
            'condition' => $condition->getConfig()
        ];

        return UrlHelper::buildQuery($config);
    }
}
