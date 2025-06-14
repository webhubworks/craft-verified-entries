<?php

namespace webhubworks\verifiedentries\widgets;

use Craft;
use craft\base\Widget;
use craft\elements\Entry;
use craft\web\assets\d3\D3Asset;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Verification Health widget type
 */
class VerificationHealth extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('verified-entries', 'Verification Health');
    }

    public static function isSelectable(): bool
    {
        return true;
    }

    protected static function allowMultipleInstances(): bool
    {
        return false;
    }

    public static function icon(): ?string
    {
        return 'heart';
    }

    public function getBodyHtml(): ?string
    {
        $enabledSectionIds = VerifiedEntries::getInstance()->sectionSettings->getEnabledSections();

        $totalEntryCount = Entry::find()
            ->status('live')
            ->section('*')
            ->count();

        $verifiedEntryCount = Entry::find()
            ->status('live')
            ->sectionId($enabledSectionIds)
            ->isVerified(true)
            ->count();

        $expiredEntryCount = Entry::find()
            ->status('live')
            ->sectionId($enabledSectionIds)
            ->isVerified(false)
            ->count();

        return Craft::$app->getView()->renderTemplate('verified-entries/_widgets/health.twig', [
            'totalCount' => $totalEntryCount,
            'verifiedCount' => $verifiedEntryCount,
            'expiredCount' => $expiredEntryCount,
        ]);
    }
}
