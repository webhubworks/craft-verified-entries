<?php

namespace webhubworks\verifiedentries\console\controllers;

use Craft;
use craft\console\Controller;
use craft\errors\SiteNotFoundException;
use webhubworks\verifiedentries\services\Verification;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\console\ExitCode;

/**
 * Check Expired Verifications controller
 */
class CheckExpiredVerificationsController extends Controller
{
    public $defaultAction = 'index';

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'index':
                // $options[] = '...';
                break;
        }
        return $options;
    }

    /**
     * verified-entries/check-expired-verifications command
     * @throws SiteNotFoundException
     */
    public function actionIndex(): int
    {
        $this->stdout("Checking verification dates of all entries in enabled sections...\n");

        $expiredEntries = Verification::checkExpiredVerifications();

        if (count($expiredEntries) === 0) {
            $this->stdout('No expired entries.');
        } else {
            foreach ($expiredEntries as $entry) {
                $this->stdout("Entry [{$entry['entryId']}] expired on {$entry['verifiedUntilDate']}. ");

                if ($entry['reviewerId']) {
                    $this->stdout("Sending a notification to User [{$entry['reviewerId']}].");
                } else {
                    $this->stdout("No reviewer is assigned.");
                }

                $this->stdout("\n");
            }
        }

        return ExitCode::OK;
    }
}
