<?php

namespace webhubworks\verifiedentries\controllers;

use craft\web\Controller;
use craft\controllers\EditUserTrait;
use craft\web\CpScreenResponseBehavior;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\web\Response;

class UsersController extends Controller
{
    use EditUserTrait;

    public const string SCREEN_VERIFIED_ENTRIES = 'verified-entries';

    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = true;

    public function actionIndex(?int $userId = null): Response
    {
        $user = $this->editedUser($userId);

        /** @var Response|CpScreenResponseBehavior $response */
        $response = $this->asEditUserScreen($user, self::SCREEN_VERIFIED_ENTRIES);

        $response->contentTemplate('verified-entries/_user.twig', [
            'sections' => VerifiedEntries::getInstance()->users->getSections($userId),
            'userId' => $userId,
        ]);

        return $response;
    }
}
