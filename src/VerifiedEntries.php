<?php

namespace webhubworks\verifiedentries;

use Craft;
use craft\base\Element;
use craft\base\Event;
use craft\base\Plugin;
use craft\base\conditions\BaseCondition;
use craft\controllers\UsersController;
use craft\elements\Entry;
use craft\elements\User;
use craft\elements\conditions\entries\EntryCondition;
use craft\elements\db\EntryQuery;
use craft\enums\Color;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\DefineEditUserScreensEvent;
use craft\events\DefineHtmlEvent;
use craft\events\DefineMetadataEvent;
use craft\events\DefineRulesEvent;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterConditionRulesEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterElementSortOptionsEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Gc;
use craft\services\UserPermissions;
use craft\validators\DateTimeValidator;
use craft\web\UrlManager;
use webhubworks\verifiedentries\behaviors\EntryBehavior;
use webhubworks\verifiedentries\behaviors\EntryQueryBehavior;
use webhubworks\verifiedentries\controllers\UsersController as VerifiedEntriesUsersController;
use webhubworks\verifiedentries\elements\VerifiedEntry;
use webhubworks\verifiedentries\elements\actions\AssignReviewer;
use webhubworks\verifiedentries\elements\actions\VerifyEntry;
use webhubworks\verifiedentries\elements\conditions\ReviewerConditionRule;
use webhubworks\verifiedentries\elements\conditions\VerifiedConditionRule;
use webhubworks\verifiedentries\elements\conditions\VerifiedUntilDateConditionRule;
use webhubworks\verifiedentries\services\Notifications as NotificationsService;
use webhubworks\verifiedentries\services\SectionSettings as SectionSettingsService;
use webhubworks\verifiedentries\services\Users as VerifiedEntriesUsersService;
use webhubworks\verifiedentries\services\Verification as VerificationService;
use webhubworks\verifiedentries\widgets\EntriesToReview;
use webhubworks\verifiedentries\widgets\VerificationHealth;

/**
 * Verified Entries plugin
 *
 * @method static VerifiedEntries getInstance()
 * @author webhubworks <support@webhub.de>
 * @copyright webhubworks
 * @license https://craftcms.github.io/license/ Craft License
 * @property-read SectionSettingsService $sectionSettings
 * @property-read VerifiedEntriesUsersService $users
 * @property-read VerificationService $verification
 * @property-read NotificationsService $notifications
 */
class VerifiedEntries extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public bool $hasCpSettings = true;

    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'notifications' => NotificationsService::class,
                'sectionSettings' => SectionSettingsService::class,
                'users' => VerifiedEntriesUsersService::class,
                'verification' => VerificationService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->name = Craft::t('verified-entries', 'Verified Entries');

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->registerCpRoutes();
        }

        Event::on(
            EntryCondition::class,
            BaseCondition::EVENT_REGISTER_CONDITION_RULES,
            function (RegisterConditionRulesEvent $event) {
                $event->conditionRules[] = VerifiedConditionRule::class;
                $event->conditionRules[] = VerifiedUntilDateConditionRule::class;
                $event->conditionRules[] = ReviewerConditionRule::class;
            }
        );

        Event::on(
            Gc::class,
            Gc::EVENT_RUN,
            function (\yii\base\Event $event) {
                VerificationService::checkExpiredVerifications();
            }
        );

        Craft::$app->onInit(function () {
            $this->attachEventHandlers();
        });
    }

    private function attachEventHandlers(): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return;
        }

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_RULES,
            function (DefineRulesEvent $event) {
                $event->rules[] = [['reviewerId'], 'number', 'integerOnly' => true];
                $event->rules[] = [['verifiedUntilDate'], DateTimeValidator::class];
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_BEHAVIORS,
            function ($event) {
               $event->behaviors['verified-entries.entry'] = EntryBehavior::class;
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_METADATA,
            function (DefineMetadataEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                if (!$entry->getHasVerifiedUntilDate()) {
                    $status = Cp::statusIndicatorHtml('unverified', [
                        'color' => Color::Gray,
                    ]) . Html::tag('span', Craft::t('verified-entries', 'Unverified'));;
                } elseif ($entry->isVerified) {
                    $status = Cp::statusIndicatorHtml('live', [
                        'color' => Color::Teal,
                    ]) . Html::tag('span', Craft::t('verified-entries', 'Verified'));;
                } else {
                    $status = Cp::statusIndicatorHtml( 'expired', [
                        'color' => Color::Red,
                    ]) . Html::tag('span', Craft::t('verified-entries', 'Expired'));
                }

                $event->metadata[Craft::t('verified-entries', 'Verification')] = $status;
            }
        );

        Event::on(
            EntryQuery::class,
            EntryQuery::EVENT_DEFINE_BEHAVIORS,
            function ($event) {
                /** @var EntryQuery $query */

                $event->behaviors[] = EntryQueryBehavior::class;
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_SIDEBAR_HTML,
            function (DefineHtmlEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;
                $currentUser = Craft::$app->user;

                if (!$entry->getIsSectionEnabledForVerification()
                    || !$currentUser->getIsAdmin()
                    || !$currentUser->checkPermission('verifyEntries')
                ) {
                    return;
                }

                if (!$entry->isVerified) {
                    $event->html .= Html::beginTag('div', [
                            'class' => ['meta', 'warning'],
                        ]) .
                        Html::tag('p', Craft::t('verified-entries', 'Entry has expired and is due to be verified.')) .
                        Html::endTag('div');
                }

                $event->html .= Craft::$app->getView()->renderTemplate('verified-entries/_sidebar.twig', [
                    'verifiedUntilDate' => $entry->verifiedUntilDate,
                    'isVerified' => $entry->isVerified,
                    'reviewer' => $entry->reviewer,
                    'options' => VerificationService::getOptions($entry->verifiedUntilDate, $entry->sectionId),
                    'addOptionFn' => VerificationService::getAddOptionFn(),
                ]);
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_REGISTER_SORT_OPTIONS,
            function (RegisterElementSortOptionsEvent $event) {
                $event->sortOptions[] = [
                    'label' => Craft::t('verified-entries', 'Verified until'),
                    'orderBy' => 'verifiedUntilDate',
                    'defaultDir' => 'desc',
                ];
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_REGISTER_TABLE_ATTRIBUTES,
            function (RegisterElementTableAttributesEvent $event) {
                $event->tableAttributes['verifiedUntilDate'] = [
                    'label' => Craft::t('verified-entries', 'Verified until')
                ];

                $event->tableAttributes['isVerified'] = [
                    'label' => Craft::t('verified-entries', 'Verification'),
                ];

                $event->tableAttributes['reviewer'] = [
                    'label' => Craft::t('verified-entries', 'Reviewer'),
                ];
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_ATTRIBUTE_HTML,
            function (DefineAttributeHtmlEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                switch ($event->attribute) {
                    case "isVerified":
                        if ($entry->isVerified) {
                            $event->html = Cp::statusLabelHtml([
                                'color' => Color::Teal,
                                'label' => Craft::t('verified-entries', 'Verified')
                            ]);
                        } else {
                            $event->html = Cp::statusLabelHtml([
                                'color' => Color::Red,
                                'label' => Craft::t('verified-entries', 'Expired'),
                            ]);
                        }
                        break;
                    case "verifiedUntilDate":
                        if ($entry->verifiedUntilDate === null) {
                            $event->html = Craft::t('verified-entries', 'Indefinitely');
                        }
//                        else {
//                            $difference = date_diff(DateTimeHelper::now(), $entry->verifiedUntilDate);
//
//                            $event->html = DateTimeHelper::humanDuration($difference, false);
//                        }
                        break;
                    case "reviewer":
                        if ($entry->reviewer) {
                            $event->html = Cp::elementChipHtml($entry->reviewer);
                        }
                        break;
                }
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML,
            function (DefineAttributeHtmlEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;
                $currentUser = Craft::$app->user;
                $canVerifyEntries = $currentUser->getIsAdmin() || $currentUser->checkPermission('verifyEntries');


                if ($event->attribute === 'reviewer') {
                    $event->html = Cp::elementSelectHtml([
                        'id' => 'reviewerId',
                        'name' => 'reviewerId',
                        'label' => Craft::t('verified-entries', 'Reviewer'),
                        'single' => true,
                        'elementType' => User::class,
                        'elements' => $entry->reviewer ? [$entry->reviewer] : null,
                        'criteria' => [
                            'status' => 'active',
                            'can' => 'verifyEntries',
                        ],
                        'disabled' => !$canVerifyEntries,
                    ]);
                    return;
                }

                if ($event->attribute === 'verifiedUntilDate') {
                    $event->html = Cp::selectizeFieldHtml([
                        'id' => 'verifiedUntilDate',
                        'name' => 'verifiedUntilDate',
                        'options' => VerificationService::getOptions($entry->verifiedUntilDate),
                        'selectizeOptions' => [
                            'allowEmptyOption' => false,
                            'autocomplete' => false,
                        ],
                        'value' => $entry->verifiedUntilDate ? $entry->verifiedUntilDate->format('Y-m-d') : false,
                        'addOptionLabel' => 'specificDate',
                        'addOptionFn' => VerificationService::getAddOptionFn(),
                        'disabled' => !$canVerifyEntries,
                    ]);
                }
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_ACTIONS,
            function (RegisterElementActionsEvent $event) {
                $currentUser = Craft::$app->user;

                if ($currentUser->getIsAdmin() || $currentUser->checkPermission('verifyEntries')) {
                    $event->actions[] = VerifyEntry::class;
                    $event->actions[] = AssignReviewer::class;
                }
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                if (!$entry->getHasVerifiedUntilDate() || !$entry->enabled) {
                    return;
                }

                if (ElementHelper::isRevision($entry)) {
                    $reviewerId = $entry->reviewerId;
                    $creatorId = $entry->getBehavior('revision')->creatorId;

                    if ($reviewerId !== null && $reviewerId !== $creatorId) {
                        NotificationsService::sendChangeNotification($entry);
                    }
                }
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_BEFORE_SAVE,
            function (ModelEvent $event) {
                if (!$event->isNew) {
                    return;
                }

                /** @var Entry $entry */
                $entry = $event->sender;
                if ($entry->sectionId === null) { // ToDo: In which cases is this null and why?
                    return;
                }
                [$reviewerId, $defaultPeriod] = $this->sectionSettings->getDefaultSettingsForSection($entry->sectionId);

                if ($entry->reviewerId === null && $reviewerId) {
                    $entry->setReviewerId($reviewerId);
                }

                if ($entry->verifiedUntilDate === null && $defaultPeriod) {
                    $dateInterval = new \DateInterval($defaultPeriod);
                    $verifiedUntilDate = DateTimeHelper::now()->add($dateInterval);
                    $entry->setVerifiedUntilDate($verifiedUntilDate);
                }
            }
        );

        Event::on(
            UsersController::class,
            UsersController::EVENT_DEFINE_EDIT_SCREENS,
            function (DefineEditUserScreensEvent $event) {
                if (!$event->editedUser->can('verifyEntries')) {
                    return;
                }

                $event->screens[VerifiedEntriesUsersController::SCREEN_VERIFIED_ENTRIES] = [
                    'label' => Craft::t('verified-entries', 'Verified Entries'),
                ];
            }
        );

        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            [$this, 'registerWidgets']
        );

        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = VerifiedEntry::class;
        });

        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            [$this, 'registerPermissions']
        );
    }

    public function getCpNavItem(): ?array
    {
        $nav = parent::getCpNavItem();
        $currentUser = Craft::$app->user;

        $nav['subnav']['overview'] = [
            'label' => Craft::t('app', 'Dashboard'),
            'url' => 'verified-entries',
        ];

        if ($currentUser->getIsAdmin() || $currentUser->checkPermission('manageVerificationSettings')) {
            $nav['subnav']['settings'] = [
                'label' => Craft::t('app', 'Settings'),
                'url' => 'verified-entries/settings',
            ];
        }

        return $nav;
    }

    private function registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $currentUser = Craft::$app->user;

                $event->rules['verified-entries'] = 'verified-entries/entries/index';

                if ($currentUser->getIsAdmin() || $currentUser->checkPermission('manageVerificationSettings')) {
                    $event->rules['verified-entries/settings'] = 'verified-entries/section-settings/index';
                }

                // User edit screen
                $event->rules['myaccount/verified-entries'] = 'verified-entries/users/index';
                $event->rules['users/<userId:\d+>/verified-entries'] = 'verified-entries/users/index';
            }
        );
    }

    public function registerPermissions(RegisterUserPermissionsEvent $event): void
    {
        $event->permissions[] = [
            'heading' => Craft::t('verified-entries', 'Verified Entries'),
            'permissions' => [
                'manageVerificationSettings' => [
                    'label' => Craft::t('verified-entries', 'Manage Verification Settings'),
                ],
                'verifyEntries' => [
                    'label' => Craft::t('verified-entries', 'Verify entries'),
                ]
            ],
        ];
    }

    public function registerWidgets(RegisterComponentTypesEvent $event): void
    {
        $currentUser = Craft::$app->user;

        $event->types[] = VerificationHealth::class;

        if ($currentUser->checkPermission('verifyEntries')) {
            $event->types[] = EntriesToReview::class;
        }
    }

    public function getSettingsResponse(): null
    {
        // Redirect to our settings page
        Craft::$app->controller->redirect(
            UrlHelper::cpUrl('verified-entries/settings')
        );

        return null;
    }
}
