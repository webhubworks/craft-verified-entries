<?php

namespace webhubworks\verifiedentries\services;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use webhubworks\verifiedentries\migrations\Install;
use yii\base\Component;
use yii\db\Exception;

/**
 * Section Settings service
 */
class SectionSettings extends Component
{
    public function getAllSectionSettings(): array
    {
        return (new Query())
            ->from(Install::ENTRYATTRIBUTES_SECTIONS)
            ->indexBy('sectionId')
            ->all();
    }

    public function getAllSectionsWithSettings(): array
    {
       $rows = (new Query())
            ->select([
                's.id',
                's.uid',
                's.name',
                's.handle',
                's.type',
                'ves.reviewerId',
                'ves.enabled',
                'ves.defaultPeriod'
            ])
            ->from(['s' => '{{%sections}}'])
            ->leftJoin('{{%verifiedentries_sections}} ves', '[[ves.sectionId]] = [[s.id]]')
            ->all();

        // Collect all unique reviewer IDs
        $reviewerIds = array_unique(array_filter(array_column($rows, 'reviewerId')));

        // Fetch User models in one go
        $reviewers = [];
        if (!empty($reviewerIds)) {
            $reviewerElements = \craft\elements\User::find()
                ->id($reviewerIds)
                ->status(null)
                ->all();

            foreach ($reviewerElements as $user) {
                $reviewers[$user->id] = $user;
            }
        }

        // Replace reviewerId with actual User model (if found)
        foreach ($rows as &$row) {
            $row['reviewer'] = $reviewers[$row['reviewerId']] ?? null;
        }

        return $rows;
    }

    /**
     * @throws Exception
     */
    public function saveSectionSettings(int $sectionId, array $settings): void
    {
        $enabled = !empty($settings['enabled']);
        $defaultPeriod = $settings['defaultPeriod'] ?? null;

        $reviewerId = $settings['reviewerId'] ?? null;
        if (is_array($reviewerId)) {
            $reviewerId = reset($reviewerId) ?: null;
        } else {
            $reviewerId = $reviewerId ?: null;
        }

        Db::upsert('{{%verifiedentries_sections}}', [
            'sectionId' => $sectionId,
            'reviewerId' => $reviewerId,
            'enabled' => $enabled,
            'defaultPeriod' => $defaultPeriod,
        ], [
            'reviewerId' => $reviewerId,
            'enabled' => $enabled,
            'defaultPeriod' => $defaultPeriod,
        ]);
    }

    public function getIsEnabledForSection(int $sectionId): bool
    {
        $result = (new Query())
            ->select('enabled')
            ->from('{{%verifiedentries_sections}}')
            ->where(['sectionId' => $sectionId])
            ->one();

        if (!$result) {
            return false;
        }

        return boolval($result['enabled']);
    }

    public function getEnabledSections(): array
    {
        return (new Query())
            ->select('sectionId')
            ->from('{{%verifiedentries_sections}}')
            ->where(['enabled' => true])
            ->column();
    }

    public function getDefaultSettingsForSection(int $sectionId): ?array
    {
        $result = (new Query())
            ->select(['enabled', 'reviewerId', 'defaultPeriod'])
            ->from('{{%verifiedentries_sections}}')
            ->where(['sectionId' => $sectionId])
            ->one();

        if (!$result || !$result['enabled']) {
            return null;
        }

        return [
            $result['reviewerId'],
            $result['defaultPeriod'],
        ];
    }
}
