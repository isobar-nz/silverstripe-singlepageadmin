<?php

namespace LittleGiant\SilverStripe\SinglePageAdmin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\CampaignAdmin\AddToCampaignHandler_FormAction;
use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Requirements;
use SilverStripe\Control\HTTPResponse;

/**
 * Defines the Single Page Administration interface for the CMS
 *
 * @package SinglePageAdmin
 * @author Stevie Mayhew
 */
class SinglePageAdmin extends LeftAndMain implements PermissionProvider
{
    /**
     * As of 4.0 all subclasses of LeftAndMain have to have a
     * $url_segment as a result of this, we need to hide the
     * item from the cms menu.
     *
     * @TODO: Figure out a way to hide the menu item - Ryan Potter 24/11/17
     */
    private static $url_segment = 'little-giant/single-page-admin';

    /**
     * @var string
     */
    private static $menu_title = 'Single Page Admin';

    /**
     * @var string
     */
    private static $url_rule = '/$Action/$ID/$OtherID';

    /**
     * @var string
     */
    private static $menu_icon_class = 'font-icon-edit-list';

    /**
     * @var array
     */
    private static $allowed_actions = [
        'EditForm',
    ];

    /**
     * Codes which are required from the current user to view this controller.
     * If multiple codes are provided, all of them are required.
     * All CMS controllers require "CMS_ACCESS_LeftAndMain" as a baseline check,
     * and fall back to "CMS_ACCESS_<class>" if no permissions are defined here.
     * See {@link canView()} for more details on permission checks.
     *
     * @config
     * @var array
     */
    private static $required_permission_codes;

    /**
     * A cached reference to the page record
     * @var SiteTree
     */
    protected $page;


    /**
     * Initialize requirements for this view
     */
    public function init()
    {
        parent::init();

        Requirements::javascript('silverstripe/cms: client/dist/js/bundle.js');
        Requirements::javascript('silverstripe/cms: client/dist/js/SilverStripeNavigator.js');
        Requirements::css('silverstripe/cms: client/dist/styles/bundle.css');
        Requirements::add_i18n_javascript('silverstripe/cms: client/lang', false, true);
    }

    /**
     * Helper function for getting the single page instance, existing or created
     * @return SiteTree
     */
    protected function findOrMakePage()
    {
        if ($this->page) {
            return $this->page;
        }

        $currentStage = Versioned::get_stage();
        Versioned::set_stage('Stage');

        $treeClass = $this->config()->get('tree_class');
        $page = $treeClass::get()->first();

        if (!$page || !$page->exists()) {
            $page = $treeClass::create();
            $page->Title = $treeClass;
            $page->write();
            $page->doPublish();
        }

        Versioned::set_stage($currentStage);

        return $this->page = $page;
    }

    /**
     * @param null $member
     * @return bool|int
     */
    public function canView($member = null)
    {
        if (!$member && $member !== false) {
            $member = Member::currentUser();
        }

        $codes = [];
        $extraCodes = $this->stat('required_permission_codes');

        if ($extraCodes !== false) { // allow explicit FALSE to disable subclass check
            if ($extraCodes) {
                $codes = array_merge($codes, (array)$extraCodes);
            } else {
                $codes[] = "CMS_ACCESS_$this->class";
            }
        }


        foreach ($codes as $code) {
            if (!Permission::checkMember($member, $code)) {
                return false;
            }
        }

        return parent::canView($member);
    }

    /**
     * @return array
     */
    public function providePermissions()
    {
        $perms = [];

        // Add any custom SinglePageAdmin subclasses.
        foreach (ClassInfo::subclassesFor(SinglePageAdmin::class) as $i => $class) {

            if ($class == SinglePageAdmin::class) {
                continue;
            }

            if (ClassInfo::classImplements($class, 'TestOnly')) {
                continue;
            }

            $title = _t("{$class}.MENUTITLE", LeftAndMain::menu_title($class));
            $perms["CMS_ACCESS_" . $class] = [
                'name'     => _t(
                    'CMSMain.ACCESS',
                    "Access to '{title}' section",
                    "Item in permission selection identifying the admin section. Example: Access to 'Files & Images'",
                    ['title' => $title]
                ),
                'category' => _t('Permission.CMS_ACCESS_CATEGORY', 'CMS Access'),
            ];
        }

        return $perms;
    }

    /**
     * @param null $id
     * @param null $fields
     * @return $this|null|\SilverStripe\Forms\Form
     */
    public function getEditForm($id = null, $fields = null)
    {
        $page = $this->findOrMakePage();
        $fields = $page->getCMSFields();

        $fields->push(new HiddenField('PreviewURL', 'Preview URL', RootURLController::get_homepage_link()));
        $fields->push($navField = new LiteralField('SilverStripeNavigator', $this->getSilverStripeNavigator()));
        $navField->setAllowHTML(true);

        $currentStage = Versioned::get_stage();
        Versioned::set_stage('Stage');

        // Check record exists
        if (!$page) {
            return $this->EmptyForm();
        }

        // Check if this record is viewable
        if ($page && !$page->canView()) {
            $response = Security::permissionFailure($this);
            $this->setResponse($response);

            return null;
        }

        $negotiator = $this->getResponseNegotiator();
        $form = SinglePageCMSForm::create(
            $this,
            "EditForm",
            $fields,
            $this->getCMSActions()
        )->setHTMLID('Form_EditForm');
        $form->addExtraClass('cms-edit-form fill-height flexbox-area-grow');
        $form->loadDataFrom($page);
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');
        $form->setValidationResponseCallback(function (ValidationResult $errors) use ($negotiator, $form) {
            $request = $this->getRequest();
            if ($request->isAjax() && $negotiator) {
                $result = $form->forTemplate();

                return $negotiator->respond($request, [
                    'CurrentForm' => function () use ($result) {
                        return $result;
                    },
                ]);
            }

            return null;
        });

        $this->extend('updateEditForm', $form);

        Versioned::set_stage($currentStage);

        return $form;

    }

    /**
     * @param null $request
     * @return null|\SilverStripe\Forms\Form|SinglePageAdmin
     */
    public function EditForm($request = null)
    {
        return $this->getEditForm();
    }

    /**
     * @desc This function is necessary for for some module functionality that relies on the controller having the current page ID implemented
     * @return mixed
     */
    public function currentPageID()
    {
        return $this->findOrMakePage()->ID;
    }

    /**
     * @desc Used for preview controls, mainly links which switch between different states of the page.
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    public function getSilverStripeNavigator()
    {
        return $this->renderWith('SinglePageAdmin_SilverStripeNavigator');
    }

    /**
     * @return mixed
     */
    public function getResponseNegotiator()
    {
        $neg = parent::getResponseNegotiator();
        $controller = $this;
        $neg->setCallback('CurrentForm', function () use (&$controller) {
            return $controller->renderWith($this->getTemplatesWithSuffix('_EditForm'));
        });

        return $neg;
    }

    /**
     * @return mixed
     */
    public function LinkPreview()
    {
        $page = $this->findOrMakePage();
        $baseLink = ($page && $page instanceof SiteTree) ? $page->Link('?stage=Stage') : Director::absoluteBaseURL();

        return $baseLink;
    }

    /**
     * @return FieldList
     * @TODO: classes
     */
    protected function getCMSActions()
    {
        $page = $this->findOrMakePage();

        // Get status of page
        $isPublished = $page->isPublished();
        $isOnDraft = $page->isOnDraft();
        $stagesDiffer = $page->stagesDiffer('Stage', 'Live');

        // Check permissions
        $canPublish = $page->canPublish();
        $canUnpublish = $page->canUnpublish();
        $canEdit = $page->canEdit();

        // Major actions appear as buttons immediately visible as page actions.
        $majorActions = CompositeField::create()->setName('MajorActions');
        $majorActions->setFieldHolderTemplate(get_class($majorActions) . '_holder_buttongroup');

        // "save", supports an alternate state that is still clickable, but notifies the user that the action is not needed.
        $noChangesClasses = 'btn-outline-primary font-icon-tick';
        if ($canEdit && $isOnDraft) {
            $majorActions->push(
                FormAction::create('save', _t(__CLASS__ . '.BUTTONSAVED', 'Saved'))
                    ->addExtraClass($noChangesClasses)
                    ->setAttribute('data-btn-alternate-add', 'btn-primary font-icon-save')
                    ->setAttribute('data-btn-alternate-remove', $noChangesClasses)
                    ->setUseButtonTag(true)
                    ->setAttribute('data-text-alternate', _t('SilverStripe\\CMS\\Controllers\\CMSMain.SAVEDRAFT', 'Save draft'))
            );
        }

        // "publish", as with "save", it supports an alternate state to show when action is needed.
        if ($canPublish && $isOnDraft) {
            $majorActions->push(
                $publish = FormAction::create('publish', _t(__CLASS__ . '.BUTTONPUBLISHED', 'Published'))
                    ->addExtraClass($noChangesClasses)
                    ->setAttribute('data-btn-alternate-add', 'btn-primary font-icon-rocket')
                    ->setAttribute('data-btn-alternate-remove', $noChangesClasses)
                    ->setUseButtonTag(true)
                    ->setAttribute('data-text-alternate', _t(__CLASS__ . '.BUTTONSAVEPUBLISH', 'Save & publish'))
            );

            // Set up the initial state of the button to reflect the state of the underlying SiteTree object.
            if ($stagesDiffer) {
                $publish->addExtraClass('btn-primary font-icon-rocket');
                $publish->setTitle(_t(__CLASS__ . '.BUTTONSAVEPUBLISH', 'Save & publish'));
                $publish->removeExtraClass($noChangesClasses);
            }
        }

        // Minor options are hidden behind a drop-up and appear as links (although they are still FormActions).
        $rootTabSet = new TabSet('ActionMenus');
        $moreOptions = new Tab(
            'MoreOptions',
            _t(__CLASS__ . '.MoreOptions', 'More options', 'Expands a view for more buttons')
        );
        $moreOptions->addExtraClass('popover-actions-simulate');
        $rootTabSet->push($moreOptions);
        $rootTabSet->addExtraClass('ss-ui-action-tabset action-menus noborder');

        // Add to campaign option if not-archived and has publish permission
        if (($isPublished || $isOnDraft) && $canPublish) {
            $moreOptions->push(
                AddToCampaignHandler_FormAction::create()
                    ->removeExtraClass('btn-primary')
                    ->addExtraClass('btn-secondary')
            );
        }

        // Rollback
        if ($isOnDraft && $isPublished && $canEdit && $stagesDiffer) {
            $moreOptions->push(
                FormAction::create('rollback', _t(__CLASS__ . '.BUTTONCANCELDRAFT', 'Cancel draft changes'))
                    ->setDescription(_t(
                        'SilverStripe\\CMS\\Model\\SiteTree.BUTTONCANCELDRAFTDESC',
                        'Delete your draft and revert to the currently published page'
                    ))
                    ->addExtraClass('btn-secondary')
            );
        }

        // Unpublish
        if ($isPublished && $canPublish && $isOnDraft && $canUnpublish) {
            $moreOptions->push(
                FormAction::create('unpublish', _t(__CLASS__ . '.BUTTONUNPUBLISH', 'Unpublish'), 'delete')
                    ->setDescription(_t(__CLASS__ . '.BUTTONUNPUBLISHDESC', 'Remove this page from the published site'))
                    ->addExtraClass('btn-secondary')
            );
        }

        $actions = new FieldList([$majorActions, $rootTabSet]);
        $this->extend('updateCMSActions', $actions);

        return $actions;
    }

    /**
     * @param array $data
     * @param \SilverStripe\Forms\Form $form
     * @return mixed
     */
    public function save($data, $form)
    {
        $currentStage = Versioned::get_stage();
        Versioned::set_stage('Stage');
        $value = $this->doSave($data, $form);
        Versioned::set_stage($currentStage);

        return $value;
    }

    /**
     * @param $data
     * @param $form
     * @return mixed
     */
    public function publish($data, $form)
    {
        $data['__publish__'] = '1';

        return $this->doSave($data, $form);
    }

    /**
     * @desc Save the page
     * @param $data
     * @param $form
     * @return mixed|HTTPResponse
     */
    public function doSave($data, $form)
    {
        $request = $this->getRequest();

        $page = $this->findOrMakePage();
        $controller = Controller::curr();
        $publish = isset($data['__publish__']);

        // Check publishing permissions
        $doPublish = !empty($publish);
        if ($page && $doPublish && !$page->canPublish()) {
            return Security::permissionFailure($this);
        }

        // save form data into record
        $form->saveInto($page, true);
        $page->write();
        $this->extend('onAfterSave', $page);

        // Set the response message
        // If the 'Save & Publish' button was clicked, also publish the page
        if ($doPublish) {
            $page->publishRecursive();
            $message = _t(
                'SilverStripe\\CMS\\Controllers\\CMSMain.PUBLISHED',
                "Published '{title}' successfully.",
                ['title' => $page->Title]
            );
        } else {
            $message = _t(
                'SilverStripe\\CMS\\Controllers\\CMSMain.SAVED',
                "Saved '{title}' successfully.",
                ['title' => $page->Title]
            );
        }

        $this->getResponse()->addHeader('X-Status', rawurlencode($message));

        return $this->edit($controller->getRequest());
    }

    /**
     * @desc Unpublish the page
     * @return mixed
     */
    public function unPublish()
    {
        $currentStage = Versioned::get_stage();
        Versioned::set_stage('Live');

        $page = $this->findOrMakePage();

        // This way our ID won't be unset
        $clone = clone $page;
        $clone->delete();

        Versioned::set_stage($currentStage);

        return $this->edit(Controller::curr()->getRequest());
    }

    /**
     * @param $data
     * @param $form
     * @return HTTPResponse
     */
    public function rollback($data, $form)
    {
        $page = $this->findOrMakePage();

//        $page->extend('onBeforeRollback', $page->ID, $page->Version);

        $id = (isset($page->ID)) ? (int)$page->ID : null;
        $version = (isset($page->Version)) ? (int)$page->Version : null;

        /** @var DataObject|Versioned $record */
        $record = Versioned::get_latest_version($this->config()->get('tree_class'), $id);
        if ($record && !$record->canEdit()) {
            return Security::permissionFailure($this);
        }

        if ($version) {
            $record->doRollbackTo($version);
            $message = _t(
                __CLASS__ . '.ROLLEDBACKVERSIONv2',
                "Rolled back to version #{version}.",
                ['version' => $page->Version]
            );
        } else {
            $record->doRevertToLive();
            $message = _t(
                __CLASS__ . '.ROLLEDBACKPUBv2',
                "Rolled back to published version."
            );
        }

        $this->getResponse()->addHeader('X-Status', rawurlencode($message));

        // Can be used in different contexts: In normal page edit view, in which case the redirect won't have any effect.
        // Or in history view, in which case a revert causes the CMS to re-load the edit view.
        // The X-Pjax header forces a "full" content refresh on redirect.
        $url = Controller::curr()->getRequest();
        $this->getResponse()->addHeader('X-ControllerURL', $url->getURL()); // @TODO: Redirect to the base url of the form - 24/11/17 Ryan Potter
        $this->getRequest()->addHeader('X-Pjax', 'Content');
        $this->getResponse()->addHeader('X-Pjax', 'Content');

        return $this->getResponseNegotiator()->respond($this->getRequest());
    }


    /**
     * @param $request
     * @return mixed
     */
    public function edit($request)
    {
        $controller = Controller::curr();
        $form = $this->EditForm($request);

        $return = $this->customise([
            'Backlink' => $controller->hasMethod('Backlink') ? $controller->Backlink() : $controller->Link(),
            'EditForm' => $form,
        ])->renderWith($this->getTemplatesWithSuffix('_Content'));

        if ($request->isAjax()) {
            return $return;
        } else {
            return $controller->customise([
                'Content' => $return,
            ]);
        }
    }

}
