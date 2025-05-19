<?php

namespace webhubworks\verifiedentries\elements;

use Craft;
use craft\base\Element;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\web\CpScreenResponseBehavior;
use webhubworks\verifiedentries\behaviors\EntryQueryBehavior;
use webhubworks\verifiedentries\elements\conditions\VerifiedEntryCondition;
use webhubworks\verifiedentries\elements\db\VerifiedEntryQuery;
use webhubworks\verifiedentries\services\Verification;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\web\Response;

/**
 * Verified Entry element type
 */
class VerifiedEntry extends Entry
{
    public static function refHandle(): ?string
    {
        return 'verifiedEntry';
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'section',
            'postDate',
            'isVerified',
            'verifiedUntilDate',
            'reviewer',
        ];
    }

    protected static function defineSources(string $context = null): array
    {
        /** @var  $verificationService */
        $plugin = VerifiedEntries::getInstance();
        $enabledSectionIds = $plugin->sectionSettings->getEnabledSections();

        $currentUser = Craft::$app->user;
        $reviewers = User::find()
            ->can('verifyEntries')
            ->id(['not', $currentUser->id])
            ->all();

        $sources = [
            [
                'key' => 'expired',
                'label' => Craft::t('verified-entries', 'Expired'),
                'criteria' => [
                    'isVerified' => false,
                    'sectionId' => $enabledSectionIds,
                    'status' => 'live',
                ]
            ],
            [
                'key' => 'upcoming',
                'label' => Craft::t('app', 'Pending'),
                'criteria' => [
                    'isVerified' => true,
                    'sectionId' => $enabledSectionIds,
                    'status' => 'live',
                    'verifiedUntil' => '< ' . (DateTimeHelper::nextMonth())->format('Y-m-d'),
                ]
            ],
            [
                'key' => 'verified',
                'label' => Craft::t('verified-entries', 'Verified'),
                'criteria' => [
                    'isVerified' => true,
                    'sectionId' => $enabledSectionIds,
                    'status' => 'live',
                ]
            ],
            [
                'heading' => Craft::t('verified-entries', 'Reviewer'),
            ],
            [
                'key' => 'mine',
                'label' => $currentUser->getIdentity()->friendlyName,
                'criteria' => [
                    'reviewerId' => $currentUser->id,
                    'status' => 'live',
                    'sectionId' => $enabledSectionIds,
                ]
            ]
        ];

        foreach ($reviewers as $reviewer) {
            /** @var User $reviewer */
            $sources[] = [
                'key' => 'reviewer-' . $reviewer->id,
                'label' => $reviewer->friendlyName,
                'criteria' => [
                    'reviewerId' => $reviewer->id,
                    'status' => 'live',
                    'sectionId' => $enabledSectionIds,
                ],
            ];
        }

        return $sources;
    }
}
