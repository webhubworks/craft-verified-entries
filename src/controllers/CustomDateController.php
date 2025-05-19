<?php

namespace webhubworks\verifiedentries\controllers;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\i18n\Formatter;
use craft\web\Controller;
use yii\web\Response;

/**
 * Custom Date controller
 */
class CustomDateController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * verified-entries/custom-date action
     */
    public function actionIndex(): Response
    {
        $response = $this->asCpModal()
            ->action('verified-entries/custom-date/validate')
            ->contentTemplate('verified-entries/_modals/_date.twig');

        return $response;
    }

    public function actionValidate(): Response
    {
        $this->requirePostRequest();

        $date = DateTimeHelper::toDateTime($this->request->getBodyParam('verifiedUntilDate'));

        if (!$date) {
            return $this->asFailure(
                Craft::t('verified-entries', 'No date provided.'),
                [
                    'errors' => [
                        'verifiedUntilDate' => [
                            Craft::t('app', '{attribute} cannot be blank.', ['attribute' => 'Date'])
                        ]
                    ]
                ]
            );
        }

        if ($date < DateTimeHelper::now()) {
            return $this->asFailure(
                Craft::t('verified-entries', 'Could not set verification date.'),
                [
                    'errors' => [
                        'verifiedUntilDate' => [
                            Craft::t('verified-entries', 'Date must be in the future.'),
                        ]
                    ]
                ]
            );
        }

        $formatter = new Formatter();

        return $this->asJson([
            'date' => $date->format('Y-m-d'),
            'label' => $formatter->asDate($date),
        ]);
    }
}
