<?php

namespace webhubworks\verifiedentries\services;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use craft\i18n\Locale;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\helpers\Markdown;

class Notifications extends Component
{
    private static function getFormatter(string|null $locale): Formatter
    {
        return $locale
            ? Craft::$app->getI18n()->getLocaleById($locale)->getFormatter()
            : Craft::$app->getFormatter();
    }

    public static function sendExpiredNotification(User $reviewer, array|Collection $entries): void
    {
        $language = $reviewer->getPreferredLanguage();
        $formatter = self::getFormatter($language);

        $subject = Craft::t(
            'verified-entries',
            '{count, number} {count, plural, =1{entry awaits} other{entries await}} your verification',
            ['count' => count($entries)],
            $language
        );

        $body = "Hey {$reviewer->friendlyName},\n\n";

        $body .= Craft::t(
                'verified-entries',
                'the following entries have verification dates that have expired:',
                null,
                $language,
            ) . "\n\n";

        $link = UrlHelper::cpUrl('entries', [
            'site' => Craft::$app->sites->getPrimarySite()->handle,
            'source' => '*',
            'filters' => Verification::getFilterParams($reviewer->id),
        ]);

        $body .= "[" . Craft::t('verified-entries', 'View all', null, $language) . "]($link)\n\n";

        foreach ($entries as $entry) {
            $cpEditUrl = UrlHelper::cpUrl("entries/{$entry['sectionHandle']}/{$entry['entryId']}");
            $body .= "- **{$entry['title']}** "
                . "(" . Craft::t('verified-entries', 'Verified until', null, $language) . " " . $formatter->asDate($entry['verifiedUntilDate'], Locale::LENGTH_MEDIUM) . ")"
                . " [" . Craft::t('app', 'Edit', null, $language) . "]($cpEditUrl)\n";
        }

        $html = Markdown::process($body);

        Craft::$app->getMailer()->compose()
            ->setTo($reviewer->email)
            ->setSubject($subject)
            ->setHtmlBody($html)
            ->send();

    }

    public static function sendChangeNotification(Entry $entry): void
    {
        /** @var User|null $reviewer */
        $reviewer = $entry->reviewer;

        if (!$reviewer || !$reviewer->active) {
            Craft::info('Entry has no reviewer to notify');
            return;
        }

        $language = $reviewer->getPreferredLanguage();
        $formatter = self::getFormatter($language);

        $subject = Craft::t(
            'verified-entries',
            'Entry has been updated',
            null,
            $language
        );

        $body = "Hey {$reviewer->friendlyName}!\n\n";
        $body .= Craft::t('verified-entries', 'An entry you\'re assigned to review has been updated. Please take a moment to review the latest changes:', null, $language) . "\n\n";

        $body .= "**{$entry->title}**<br>";
        $body .= Craft::t('verified-entries', 'Verified until', null, $language) . " " . $formatter->asDate($entry->verifiedUntilDate, Locale::LENGTH_MEDIUM) . "\n\n";

        $cpEditUrl = UrlHelper::cpUrl("entries/{$entry->section->handle}/{$entry->id}");
        $body .= "[" . Craft::t('app', 'Show', null, $language) . "]($cpEditUrl)";

        $html = Markdown::process($body);

        Craft::$app->getMailer()->compose()
            ->setTo($reviewer->email)
            ->setSubject($subject)
            ->setHtmlBody($html)
            ->send();
    }
}
