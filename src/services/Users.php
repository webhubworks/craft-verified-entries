<?php

namespace webhubworks\verifiedentries\services;

use Craft;
use craft\db\Query;
use craft\elements\conditions\entries\EntryCondition;
use craft\gql\types\DateTime;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use craft\records\Entry;
use webhubworks\verifiedentries\elements\conditions\VerifiedConditionRule;
use yii\base\Component;

/**
 * User service
 */
class Users extends Component
{
    public function getSections(?int $userId = null): array
    {
        if ($userId === null) {
            $userId = Craft::$app->getUser()->getIdentity()->id;
        }

        $sections = (new Query())
            ->select([
                'ves.id',
                'ves.sectionId',
                'ves.defaultPeriod',
                's.name',
                's.type',
                's.handle',
            ])
            ->from(['ves' => '{{%verifiedentries_sections}}'])
            ->innerJoin('{{%sections}} s', '[[s.id]] = [[ves.sectionId]]')
            ->where(['ves.enabled' => true])
            ->andWhere(['ves.reviewerId' => $userId])
            ->all();

        $sections = array_map(function ($section) {
            return [
                ...$section,
                'defaultPeriod' => DateTimeHelper::humanDuration($section['defaultPeriod']),
                'url' => $section['type'] == 'single'
                    ? UrlHelper::cpUrl('entries/singles')
                    : UrlHelper::cpUrl('entries/' . $section['handle'], ['filters' => Verification::getFilterParams()]),

            ];
        }, $sections);

        return $sections;
    }

    public function getEntries(?int $userId = null, ?int $siteId = null): array
    {
        $entries = $this->_createEntryQuery($userId, $siteId)->all();
        return $this->transformEntries($entries);
    }

    public function getPaginatedEntries(
        int $page,
        int $limit,
        int $sortDir = SORT_ASC,
        string $orderBy = 'verifiedUntilDate',
        ?int $userId = null,
        ?int $siteId = null,
    ): array {
        $offset = ($page - 1) * $limit;

        $query = $this->_createEntryQuery($userId, $siteId)
            ->orderBy([$orderBy => $sortDir]);

        $total = $query->count();

        $query->limit($limit);
        $query->offset($offset);

        $entries = $this->transformEntries($query->all());

        return [$entries, $total];
    }

    private function _createEntryQuery(?int $userId = null, ?int $siteId = null): Query
    {
        if ($userId === null) {
            $userId = Craft::$app->getUser()->getIdentity()->id;
        }

        if ($siteId == null) {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }

        return (new Query())
            ->select([
                'veea.id',
                'veea.entryId',
                'veea.reviewerId',
                'veea.verifiedUntilDate',
                'entries.sectionId',
                'es.title',
                'es.slug',
                'es.dateUpdated',
                'sections.name AS sectionName',
                'sections.handle AS sectionHandle',
            ])
            ->from(['veea' => '{{%verifiedentries_entryattributes}}'])
            ->rightJoin('{{%elements}}', '[[elements.id]] = [[veea.entryId]] AND [[elements.enabled]] = true')
            ->leftJoin(
                '{{%elements_sites}} es',
                '[[es.elementId]] = [[veea.entryId]] AND [[es.siteId]] = :siteId',
                ['siteId' => $siteId]
            )
            ->leftJoin('{{%entries}}', '[[entries.id]] = [[veea.entryId]]')
            ->leftJoin('{{%sections}}', '[[sections.id]] = [[entries.sectionId]]')
            ->where(['veea.reviewerId' => $userId])
            ->andWhere('elements.canonicalId IS null');
    }

    private function transformEntries(array $entries): array
    {
        $formatter = new Formatter();

        return array_map(function ($entry) use ($formatter) {
            $verifiedUntilDate = DateTimeHelper::toDateTime($entry['verifiedUntilDate']);

            $isVerified = $verifiedUntilDate && $verifiedUntilDate > new \DateTime();

            return [
                ...$entry,
                'isVerified' => $isVerified ? 'Verified' : 'Expired',
                'verifiedUntilDate' => $verifiedUntilDate ? $formatter->asDate($verifiedUntilDate) : 'Indefinitely',
                'dateUpdated' => $formatter->asDate(DateTimeHelper::toDateTime($entry['dateUpdated'])),
                'url' => UrlHelper::cpUrl('entries/' . $entry['sectionHandle'] . '/' . $entry['entryId'] . '-' . $entry['slug']),
            ];
        }, $entries);
    }
}
