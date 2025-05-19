<?php

namespace webhubworks\verifiedentries\controllers;

use Craft;
use craft\helpers\AdminTable;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use webhubworks\verifiedentries\services\Verification;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\web\Response;

class EntriesController extends Controller
{
    public function actionIndex(): Response
    {
        return $this->renderTemplate('verified-entries/index.twig');
    }

    public function actionRequestPeriod(): Response
    {
        $periodOptions = Verification::getSelectOptions();

        $response = $this->asCpModal()
            ->action('verified-entries/entries/obtain-date')
            ->contentTemplate('verified-entries/_modals/_period.twig', [
                'periodOptions' => $periodOptions,
            ]);

        return $response;
    }

    public function actionObtainDate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $verifcationPeriod = $this->request->getRequiredBodyParam('verificationPeriod');

        if ($verifcationPeriod === Verification::SPECIFIC_DATE) {
            $inputDate = $this->request->getRequiredBodyParam('specificDate');
            $date = DateTimeHelper::toDateTime($inputDate);
        } elseif ($verifcationPeriod === Verification::INDEFINITELY) {
            $date = null;
        } else {
            $interval = new \DateInterval($verifcationPeriod);
            $date = DateTimeHelper::now()->add($interval);
        }

        if ($date === false) {
            return $this->asFailure(Craft::t('verified-entries', 'Not a valid date.'));
        }

        return $this->asJson([
            'date' => $date->format('Y-m-d'),
        ]);
    }

    public function actionTableData(?int $userId = null): Response
    {
        $this->requireAcceptsJson();

        $page = (int)$this->request->getParam('page', 1);
        $limit = (int)$this->request->getParam('per_page', 100);
        $orderBy = match ($this->request->getParam('sort.0.field')) {
            '__slot:handle' => 'handle',
            default => 'name',
        };

        $sortDir = match ($this->request->getParam('sort.0.direction')) {
            'desc' => SORT_DESC,
            default => SORT_ASC,
        };

        [$results, $total] = VerifiedEntries::getInstance()->users->getPaginatedEntries(
            $page,
            $limit,
            $sortDir,
            $orderBy,
            $userId,
        );

        $pagination = AdminTable::paginationLinks($page, $total, $limit);

        return $this->asSuccess(data: [
            'pagination' => $pagination,
            'data' => $results,
        ]);
    }
}
